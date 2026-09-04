<?php

declare(strict_types=1);

namespace MailGazelle;

use MailGazelle\Domains\DomainsResource;
use MailGazelle\Emails\EmailsResource;
use MailGazelle\Http\CurlTransport;
use MailGazelle\Http\HttpClient;
use MailGazelle\Http\TransportInterface;

/**
 * Entry point for the Mail Gazelle PHP SDK.
 *
 * Construct one client per API token and reuse it. HTTP is performed through
 * {@see TransportInterface}; applications may inject their own transport.
 */
final class Client
{
    /**
     * SDK release version used in the default User-Agent.
     */
    public const VERSION = '1.0.0';

    /**
     * Public API root documented in the Mail Gazelle integration contract.
     */
    public const DEFAULT_BASE_URL = 'https://mailgazelle.com/api/v1';

    private readonly HttpClient $http;

    private ?EmailsResource $emails = null;

    private ?DomainsResource $domains = null;

    /**
     * @param string $apiToken Bearer token minted for a ready product (`tes_…`).
     * @param string|null $baseUrl Override the default API root. A trailing slash is ignored.
     * @param TransportInterface|null $transport Custom HTTP transport. Defaults to {@see CurlTransport}.
     * @param float $timeout Total request timeout in seconds.
     * @param float $connectTimeout Connection timeout in seconds.
     * @param string|null $userAgentSuffix Optional suffix appended to the default User-Agent.
     *
     * @throws \InvalidArgumentException When the token is empty or a timeout is not positive.
     */
    public function __construct(
        string $apiToken,
        ?string $baseUrl = null,
        ?TransportInterface $transport = null,
        float $timeout = 30.0,
        float $connectTimeout = 10.0,
        ?string $userAgentSuffix = null,
    ) {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            throw new \InvalidArgumentException('A Mail Gazelle API token is required.');
        }

        if ($timeout <= 0 || $connectTimeout <= 0) {
            throw new \InvalidArgumentException('Timeouts must be greater than zero.');
        }

        $normalizedBaseUrl = trim((string) ($baseUrl ?? self::DEFAULT_BASE_URL));
        if ($normalizedBaseUrl === '') {
            $normalizedBaseUrl = self::DEFAULT_BASE_URL;
        }

        $userAgent = 'mailgazelle-php-sdk/' . self::VERSION;
        if ($userAgentSuffix !== null && trim($userAgentSuffix) !== '') {
            $userAgent .= ' ' . trim($userAgentSuffix);
        }

        $this->http = new HttpClient(
            transport: $transport ?? new CurlTransport(),
            baseUrl: rtrim($normalizedBaseUrl, '/'),
            defaultHeaders: [
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'User-Agent' => $userAgent,
            ],
            timeout: $timeout,
            connectTimeout: $connectTimeout,
        );
    }

    /**
     * Send and inspect transactional emails.
     */
    public function emails(): EmailsResource
    {
        return $this->emails ??= new EmailsResource($this->http);
    }

    /**
     * List the product domains associated with this token.
     */
    public function domains(): DomainsResource
    {
        return $this->domains ??= new DomainsResource($this->http);
    }
}
