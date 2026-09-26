<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The assembled raw MIME is larger than 10 MB (`message_too_large`, typically HTTP 422).
 *
 * The message is not queued.
 */
final class MessageTooLargeException extends MailGazelleException
{
}
