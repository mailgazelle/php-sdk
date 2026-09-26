<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The send would pass the team's monthly email, daily email, or monthly
 * attachment allowance (`quota_exceeded`, typically HTTP 422).
 *
 * The message states which limit was hit. The message is not queued.
 */
final class QuotaExceededException extends MailGazelleException
{
}
