<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * Decoded attachments exceed the 7 MB limit (`attachment_too_large`, typically HTTP 422).
 */
final class AttachmentTooLargeException extends MailGazelleException
{
}
