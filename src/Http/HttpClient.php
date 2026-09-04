<?php

declare(strict_types=1);

namespace MailGazelle\Http;

use MailGazelle\Exceptions\ApiException;
use MailGazelle\Exceptions\ErrorMapper;
use MailGazelle\Exceptions\MailGazelleException;

/**
 * Authenticates requests, encodes JSON, and maps API errors to exceptions.
 *
 * @internal
 */
final class HttpClient
{
    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $baseUrl,
        private readonly array $defaultHeaders,
        private readonly float $timeout,
        private readonly float $connectTimeout,
    ) {
    }

    /**
     * Send an authenticated JSON request and return the decoded object/list.
     *
     * @param array<string, mixed>|null $json Request body, encoded as JSON when not null.
     *
     * @return array<mixed>
     *
     * @throws MailGazelleException When the transport fails or the API returns an error.
     */
    public function request(string $method, string $path, ?array $json = null): array
    {
        $body = null;
        $headers = $this->defaultHeaders;

        if ($json !== null) {
            try {
                $body = json_encode($json, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new ApiException(
                    'Failed to encode the request body as JSON.',
                    'invalid_request',
                    0,
                    [],
                    $exception,
                );
            }
            $headers['Content-Type'] = 'application/json';
        }

        $response = $this->transport->send(new Request(
            method: strtoupper($method),
            url: $this->url($path),
            headers: $headers,
            body: $body,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
        ));

        if ($response->statusCode < 200 || $response->statusCode >= 300) {
            throw ErrorMapper::fromResponse($response);
        }

        return $this->decodeSuccess($response);
    }

    public function url(string $path): string
    {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * @return array<mixed>
     */
    private function decodeSuccess(Response $response): array
    {
        if ($response->body === '') {
            return [];
        }

        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ApiException(
                'Failed to decode the API response.',
                'invalid_response',
                $response->statusCode,
                [],
                $exception,
            );
        }

        if (!is_array($decoded)) {
            throw new ApiException(
                'API returned a non-object JSON payload.',
                'invalid_response',
                $response->statusCode,
            );
        }

        return $decoded;
    }
}
