<?php

declare(strict_types=1);

namespace MailGazelle\Emails;

/**
 * Attachment metadata returned by `GET /emails/{id}`.
 *
 * File bytes are never included in this response.
 */
final readonly class AttachmentMeta
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        private string $filename,
        private ?string $contentType,
        private ?int $size,
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

        $size = $data['size'] ?? null;

        return new self(
            filename: isset($data['filename']) && is_string($data['filename']) ? $data['filename'] : '',
            contentType: isset($data['content_type']) && is_string($data['content_type']) ? $data['content_type'] : null,
            size: is_int($size) ? $size : (is_numeric($size) ? (int) $size : null),
            raw: $data,
        );
    }

    /**
     * Original filename supplied when the message was created.
     */
    public function filename(): string
    {
        return $this->filename;
    }

    /**
     * MIME type when the API returned one.
     */
    public function contentType(): ?string
    {
        return $this->contentType;
    }

    /**
     * Decoded size in bytes when the API returned one.
     */
    public function size(): ?int
    {
        return $this->size;
    }

    /**
     * Full decoded metadata object, including any undocumented keys.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }
}
