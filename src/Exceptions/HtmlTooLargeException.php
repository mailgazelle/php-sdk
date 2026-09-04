<?php

declare(strict_types=1);

namespace MailGazelle\Exceptions;

/**
 * HTML body exceeds the 512 KB limit (`html_too_large`, typically HTTP 422).
 */
final class HtmlTooLargeException extends MailGazelleException
{
}
