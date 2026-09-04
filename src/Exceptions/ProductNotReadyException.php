<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The product or team cannot send (`product_not_ready`, typically HTTP 403).
 */
final class ProductNotReadyException extends MailGazelleException
{
}
