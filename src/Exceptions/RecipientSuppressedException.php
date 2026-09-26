<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * A to, cc, or bcc address is on a suppression list (`recipient_suppressed`, typically HTTP 422).
 *
 * Mail Gazelle stores the message as rejected and does not send it. The reject
 * does not consume the monthly email or attachment quota.
 */
final class RecipientSuppressedException extends MailGazelleException
{
}
