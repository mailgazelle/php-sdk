<?php

declare(strict_types=1);

namespace MailGazelle\Domains;

/**
 * A product domain and its verification statuses from `GET /domains`.
 *
 * Unknown JSON keys are preserved on {@see toArray()} so later API fields
 * do not break older clients.
 */
final readonly class Domain
{
    /**
     * @param array<string, string> $verificationStatuses
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private string $name,
        private ?string $type,
        private array $verificationStatuses,
        private array $raw,
    ) {
    }

    /**
     * @param array<mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = [];
        foreach ($payload as $key => $value) {
            $data[(string) $key] = $value;
        }

        $name = $data['name'] ?? $data['domain'] ?? $data['host'] ?? '';
        $type = $data['type'] ?? $data['kind'] ?? null;

        return new self(
            name: is_string($name) ? $name : '',
            type: is_string($type) && $type !== '' ? $type : null,
            verificationStatuses: self::statuses($data),
            raw: $data,
        );
    }

    /**
     * Domain hostname (primary, sending, or MAIL FROM host).
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Domain role when the API returned one (for example `primary` or `sending`).
     */
    public function type(): ?string
    {
        return $this->type;
    }

    /**
     * Verification status map (check name to status string).
     *
     * @return array<string, string>
     */
    public function verificationStatuses(): array
    {
        return $this->verificationStatuses;
    }

    /**
     * Full decoded payload so later API fields remain accessible.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private static function statuses(array $data): array
    {
        $candidates = $data['verification'] ?? $data['verification_statuses'] ?? $data['statuses'] ?? null;
        if (!is_array($candidates)) {
            $statuses = [];
            foreach (['dkim', 'mail_from', 'spf', 'dmarc'] as $key) {
                if (isset($data[$key]) && is_string($data[$key])) {
                    $statuses[$key] = $data[$key];
                }
            }

            return $statuses;
        }

        $statuses = [];
        foreach ($candidates as $key => $value) {
            if (is_string($value)) {
                $statuses[(string) $key] = $value;
            } elseif (is_array($value) && isset($value['status']) && is_string($value['status'])) {
                $name = isset($value['name']) && is_string($value['name']) ? $value['name'] : (string) $key;
                $statuses[$name] = $value['status'];
            }
        }

        return $statuses;
    }
}
