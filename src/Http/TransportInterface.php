<?php

declare(strict_types=1);

namespace MailGazelle\Http;

use MailGazelle\Exceptions\TransportException;

/**
 * Pluggable HTTP transport used by the SDK.
 *
 * Customer applications may inject Guzzle, Symfony HttpClient, or a test
 * double by implementing this single method. The default implementation is
 * {@see CurlTransport}.
 */
interface TransportInterface
{
    /**
     * Send one HTTP request and return the raw response.
     *
     * Implementations must not interpret Mail Gazelle error codes. Network,
     * timeout, and TLS failures should be thrown as {@see TransportException}.
     *
     * @throws TransportException When the request cannot be completed.
     */
    public function send(Request $request): Response;
}
