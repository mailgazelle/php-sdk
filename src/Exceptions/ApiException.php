<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * An API error that does not map to a more specific exception type.
 *
 * Used for unknown error codes, unexpected HTTP statuses, and invalid JSON payloads.
 */
final class ApiException extends MailGazelleException
{
}
