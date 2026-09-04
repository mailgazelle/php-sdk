<?php

declare(strict_types=1);

namespace MailGazelle\Emails;

/**
 * Message statuses documented by the Mail Gazelle send path.
 *
 * Additional statuses may appear on {@see EmailRecord::status()} as raw strings
 * when the API adds new values.
 */
enum EmailStatus: string
{
    case Queued = 'queued';
    case Rejected = 'rejected';
}
