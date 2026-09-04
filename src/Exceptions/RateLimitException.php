<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The token exceeded 60 requests per minute (`rate_limited`, typically HTTP 429).
 */
final class RateLimitException extends MailGazelleException
{
}
