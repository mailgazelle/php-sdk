<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * The product or team cannot send (`product_not_ready`, typically HTTP 403).
 *
 * Covers a disabled or paused team, a product that is not `ready`, and a team
 * with no SES tenant yet. Same code as before; there is no separate pause error.
 */
final class ProductNotReadyException extends MailGazelleException
{
}
