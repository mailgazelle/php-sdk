<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * An attachment is missing, has an invalid filename, or is not valid base64
 * (`attachment_invalid`, typically HTTP 422).
 */
final class AttachmentException extends MailGazelleException
{
}
