<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The request failed field or business validation (`validation_error`, typically HTTP 422).
 */
final class ValidationException extends MailGazelleException
{
}
