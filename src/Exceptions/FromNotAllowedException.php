<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The From address is not allowed for this product (`from_not_allowed`, typically HTTP 422).
 */
final class FromNotAllowedException extends MailGazelleException
{
}
