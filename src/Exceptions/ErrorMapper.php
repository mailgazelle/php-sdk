<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

use MailGazelle\Http\Response;

/**
 * Maps an HTTP error response onto a typed {@see MailGazelleException}.
 *
 * @internal
 */
final class ErrorMapper
{
    /**
     * Build an exception from a non-success API response.
     *
     * Prefers the `code` field in the error envelope. When that field is
     * missing, falls back to the HTTP status.
     */
    public static function fromResponse(Response $response): MailGazelleException
    {
        $payload = self::decode($response->body);
        $code = isset($payload['code']) && is_string($payload['code']) && $payload['code'] !== ''
            ? $payload['code']
            : self::codeFromStatus($response->statusCode);
        $message = isset($payload['message']) && is_string($payload['message']) && $payload['message'] !== ''
            ? $payload['message']
            : 'Unexpected Mail Gazelle API error.';
        $details = [];
        if (isset($payload['details']) && is_array($payload['details'])) {
            $details = self::stringKeyed($payload['details']);
        }

        return self::create($code, $message, $response->statusCode, $details);
    }

    /**
     * Instantiate the exception class that corresponds to an API error code.
     *
     * @param array<string, mixed> $details
     */
    public static function create(
        string $code,
        string $message,
        int $httpStatus = 0,
        array $details = [],
        ?\Throwable $previous = null,
    ): MailGazelleException {
        return match ($code) {
            'unauthenticated' => new AuthenticationException($message, $code, $httpStatus, $details, $previous),
            'product_not_ready' => new ProductNotReadyException($message, $code, $httpStatus, $details, $previous),
            'validation_error' => new ValidationException($message, $code, $httpStatus, $details, $previous),
            'from_not_allowed' => new FromNotAllowedException($message, $code, $httpStatus, $details, $previous),
            'recipient_suppressed' => new RecipientSuppressedException($message, $code, $httpStatus, $details, $previous),
            'attachment_invalid' => new AttachmentException($message, $code, $httpStatus, $details, $previous),
            'attachment_too_large' => new AttachmentTooLargeException($message, $code, $httpStatus, $details, $previous),
            'html_too_large' => new HtmlTooLargeException($message, $code, $httpStatus, $details, $previous),
            'rate_limited' => new RateLimitException($message, $code, $httpStatus, $details, $previous),
            default => new ApiException($message, $code, $httpStatus, $details, $previous),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function decode(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? self::stringKeyed($decoded) : [];
    }

    private static function codeFromStatus(int $status): string
    {
        return match ($status) {
            401 => 'unauthenticated',
            403 => 'product_not_ready',
            422 => 'validation_error',
            429 => 'rate_limited',
            default => 'unknown_error',
        };
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
