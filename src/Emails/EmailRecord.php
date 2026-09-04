<?php

declare(strict_types=1);

namespace MailGazelle\Emails;

/**
 * Status record returned by `GET /emails/{id}`.
 *
 * Does not include HTML, text, or attachment bytes. Unknown JSON keys are
 * preserved on {@see toArray()} so later API fields do not break older clients.
 */
final readonly class EmailRecord
{
    /**
     * @param list<AttachmentMeta> $attachments
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private string $id,
        private string $status,
        private ?string $messageId,
        private ?string $lastEventType,
        private ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private ?\DateTimeImmutable $sentAt,
        private array $attachments,
        private array $raw,
    ) {
    }

    /**
     * @param array<mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $data = self::unwrap($payload);

        $attachments = [];
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            foreach ($data['attachments'] as $item) {
                if (is_array($item)) {
                    $attachments[] = AttachmentMeta::fromArray($item);
                }
            }
        }

        return new self(
            id: self::stringValue($data, 'id') ?? '',
            status: self::stringValue($data, 'status') ?? '',
            messageId: self::stringValue($data, 'message_id')
                ?? self::stringValue($data, 'provider_message_id')
                ?? self::stringValue($data, 'ses_message_id'),
            lastEventType: self::stringValue($data, 'last_event_type')
                ?? self::stringValue($data, 'last_event'),
            createdAt: self::timestamp($data, 'created_at'),
            updatedAt: self::timestamp($data, 'updated_at'),
            sentAt: self::timestamp($data, 'sent_at'),
            attachments: $attachments,
            raw: $data,
        );
    }

    /**
     * Mail Gazelle message identifier.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Current status as returned by the API (for example `queued` or `rejected`).
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Documented status enum when the value is known to this SDK version.
     */
    public function statusEnum(): ?EmailStatus
    {
        return EmailStatus::tryFrom($this->status);
    }

    /**
     * Provider message identifier when the platform has accepted the send.
     */
    public function messageId(): ?string
    {
        return $this->messageId;
    }

    /**
     * Most recent delivery-event type when one has been recorded.
     */
    public function lastEventType(): ?string
    {
        return $this->lastEventType;
    }

    /**
     * Creation timestamp when present on the payload.
     */
    public function createdAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Last-update timestamp when present on the payload.
     */
    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Send timestamp when present on the payload.
     */
    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    /**
     * Attachment metadata (filename, content type, size). File bytes are not returned.
     *
     * @return list<AttachmentMeta>
     */
    public function attachments(): array
    {
        return $this->attachments;
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
    private static function unwrap(array $payload): array
    {
        if (isset($payload['data']) && is_array($payload['data']) && !array_is_list($payload['data'])) {
            $payload = $payload['data'];
        }

        $data = [];
        foreach ($payload as $key => $value) {
            $data[(string) $key] = $value;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function stringValue(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function timestamp(array $data, string $key): ?\DateTimeImmutable
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
