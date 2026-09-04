<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The recipient is on a suppression list (`recipient_suppressed`, typically HTTP 422).
 *
 * Mail Gazelle stores the message as rejected and does not send it.
 */
final class RecipientSuppressedException extends MailGazelleException
{
}
