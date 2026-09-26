<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The API token is missing, invalid, or belongs to an archived product
 * (`unauthenticated`, typically HTTP 401).
 */
final class AuthenticationException extends MailGazelleException
{
}
