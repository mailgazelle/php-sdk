<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * Base exception for every Mail Gazelle SDK and API failure.
 *
 * Catch this type when the caller only needs to know that a Mail Gazelle
 * operation failed. Prefer the more specific subclasses for recovery logic.
 */
abstract class MailGazelleException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $details Optional structured error details from the API.
     */
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly int $status = 0,
        private readonly array $details = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Human-readable error message from the API or the SDK.
     */
    public function message(): string
    {
        return $this->getMessage();
    }

    /**
     * Machine-readable API error code (for example `validation_error`).
     */
    public function code(): string
    {
        return $this->errorCode;
    }

    /**
     * HTTP status associated with the failure, or `0` when no response was received.
     */
    public function httpStatus(): int
    {
        return $this->status;
    }

    /**
     * Optional structured details from the API error envelope.
     *
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }
}
