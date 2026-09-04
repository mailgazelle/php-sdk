<?php

declare(strict_types=1);

namespace MailGazelle\Http;

use MailGazelle\Exceptions\TransportException;

/**
 * Default HTTP transport backed by PHP's cURL extension.
 *
 * This is the zero-dependency transport used when a customer application
 * does not inject its own {@see TransportInterface}.
 */
final class CurlTransport implements TransportInterface
{
    /**
     * @inheritDoc
     */
    public function send(Request $request): Response
    {
        $handle = curl_init($request->url);
        if ($handle === false) {
            throw new TransportException(
                'Failed to initialize a cURL handle.',
                'transport_error',
            );
        }

        $method = $request->method;
        if ($method === '') {
            throw new TransportException('HTTP method must not be empty.', 'transport_error');
        }

        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => static function (mixed $ch, string $header) use (&$responseHeaders): int {
                $length = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
            CURLOPT_TIMEOUT_MS => max(1, (int) round($request->timeout * 1000)),
            CURLOPT_CONNECTTIMEOUT_MS => max(1, (int) round($request->connectTimeout * 1000)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($request->body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $request->body);
        }

        $body = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false || $errno !== 0) {
            throw new TransportException(
                $error !== '' ? $error : 'The HTTP request failed.',
                'transport_error',
                0,
                ['errno' => $errno],
            );
        }

        return new Response($status, (string) $body, $responseHeaders);
    }
}
