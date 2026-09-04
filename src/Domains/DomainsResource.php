<?php

declare(strict_types=1);

namespace MailGazelle\Domains;

use MailGazelle\Exceptions\MailGazelleException;
use MailGazelle\Http\HttpClient;

/**
 * List the product domains associated with the current API token.
 */
final class DomainsResource
{
    /**
     * @internal
     */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * List domains and their verification statuses.
     *
     * @return list<Domain>
     *
     * @throws MailGazelleException
     */
    public function list(): array
    {
        $response = $this->http->request('GET', '/domains');
        $items = self::items($response);

        $domains = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $domains[] = Domain::fromArray($item);
            }
        }

        return $domains;
    }

    /**
     * @param array<mixed> $payload
     *
     * @return list<mixed>
     */
    private static function items(array $payload): array
    {
        if ($payload === []) {
            return [];
        }

        if (array_is_list($payload)) {
            return $payload;
        }

        foreach (['data', 'domains'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_is_list($payload[$key]) ? $payload[$key] : array_values($payload[$key]);
            }
        }

        return [$payload];
    }
}
