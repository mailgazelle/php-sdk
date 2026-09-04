<?php

declare(strict_types=1);

namespace MailGazelle\Http;

/**
 * Outgoing HTTP request passed to a {@see TransportInterface} implementation.
 */
final readonly class Request
{
    /**
     * @param array<string, string> $headers Header names mapped to values (without the colon).
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public ?string $body,
        public float $timeout,
        public float $connectTimeout,
    ) {
    }
}
