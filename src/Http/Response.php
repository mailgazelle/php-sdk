<?php

declare(strict_types=1);

namespace MailGazelle\Http;

/**
 * HTTP response returned by a {@see TransportInterface} implementation.
 */
final readonly class Response
{
    /**
     * @param array<string, string> $headers Header names mapped to values. Names are lower-cased.
     */
    public function __construct(
        public int $statusCode,
        public string $body,
        public array $headers = [],
    ) {
    }
}
