<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * Decoded attachments exceed the size limit (`attachment_too_large`, typically HTTP 422).
 *
 * The SDK rejects a decoded total above the 7 MB platform ceiling. A team's plan
 * may set a lower limit; the API message then states that limit.
 */
final class AttachmentTooLargeException extends MailGazelleException
{
}
