<?php

declare(strict_types=1);

namespace MailGazelle\Emails;

/**
 * Immediate `202` result of `POST /emails`.
 *
 * The message is persisted and queued; delivery happens asynchronously.
 */
final readonly class QueuedEmail
{
    /**
     * @param array<string, mixed> $raw Decoded API payload, including any undocumented keys.
     */
    public function __construct(
        private string $id,
        private string $status,
        private array $raw,
    ) {
    }

    /**
     * Build a queued result from a decoded API object.
     *
     * @param array<mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = self::stringKeyed($payload);

        return new self(
            id: isset($data['id']) && is_string($data['id']) ? $data['id'] : '',
            status: isset($data['status']) && is_string($data['status']) ? $data['status'] : EmailStatus::Queued->value,
            raw: $data,
        );
    }

    /**
     * Mail Gazelle message identifier used with {@see EmailsResource::get()}.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Queue status returned by the API. Successful creates use `queued`.
     */
    public function status(): string
    {
        return $this->status;
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
     * @param array<mixed> $payload
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $payload): array
    {
        $result = [];
        foreach ($payload as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
