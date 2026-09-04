<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The HTTP transport failed before a usable API response was received.
 *
 * Typical causes are DNS failures, connection timeouts, and TLS errors.
 */
final class TransportException extends MailGazelleException
{
}
