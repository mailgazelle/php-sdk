<?php

declare(strict_types=1);

namespace MailGazelle\Emails;

use MailGazelle\Exceptions\AttachmentTooLargeException;
use MailGazelle\Exceptions\HtmlTooLargeException;
use MailGazelle\Exceptions\ValidationException;
use MailGazelle\ValueObjects\Address;
use MailGazelle\ValueObjects\Attachment;

/**
 * Immutable builder for a `POST /emails` payload.
 *
 * Either HTML or plain text is required. Optional fields are omitted from the
 * JSON body so Mail Gazelle can apply product defaults.
 */
final class Email
{
    public const MAX_HTML_BYTES = 512 * 1024;

    public const MAX_ATTACHMENTS = 10;

    public const MAX_ATTACHMENT_BYTES = 7 * 1024 * 1024;

    /**
     * @param array<string, string> $tags
     * @param list<Attachment> $attachments
     */
    private function __construct(
        private Address $to,
        private ?string $subject = null,
        private ?string $text = null,
        private ?string $html = null,
        private ?Address $from = null,
        private ?Address $replyTo = null,
        private array $tags = [],
        private ?string $idempotencyKey = null,
        private array $attachments = [],
    ) {
    }

    /**
     * Start a message addressed to exactly one recipient.
     *
     * @throws ValidationException When the address is invalid.
     */
    public static function to(string $email, ?string $name = null): self
    {
        return new self(to: new Address($email, $name));
    }

    /**
     * Set the subject line.
     */
    public function subject(string $subject): self
    {
        $copy = clone $this;
        $copy->subject = $subject;

        return $copy;
    }

    /**
     * Set the plain-text body.
     */
    public function text(string $text): self
    {
        $copy = clone $this;
        $copy->text = $text;

        return $copy;
    }

    /**
     * Set the HTML body.
     *
     * Mail Gazelle rejects HTML larger than 512 KB.
     */
    public function html(string $html): self
    {
        $copy = clone $this;
        $copy->html = $html;

        return $copy;
    }

    /**
     * Override the product default From address.
     *
     * The domain must be the product's primary or sending host.
     *
     * @throws ValidationException When the address is invalid.
     */
    public function from(string $email, ?string $name = null): self
    {
        $copy = clone $this;
        $copy->from = new Address($email, $name);

        return $copy;
    }

    /**
     * Override the product default Reply-To address.
     *
     * @throws ValidationException When the address is invalid.
     */
    public function replyTo(string $email, ?string $name = null): self
    {
        $copy = clone $this;
        $copy->replyTo = new Address($email, $name);

        return $copy;
    }

    /**
     * Add a single tag. Keys and values are sanitized to `[A-Za-z0-9_-]`.
     *
     * @throws ValidationException When the key is empty after sanitization.
     */
    public function tag(string $key, string $value): self
    {
        $key = self::sanitizeTagPart($key);
        if ($key === '') {
            throw new ValidationException(
                'Tag keys must contain at least one letter, number, underscore, or hyphen.',
                'validation_error',
                422,
            );
        }

        $copy = clone $this;
        $copy->tags[$key] = self::sanitizeTagPart($value);

        return $copy;
    }

    /**
     * Replace all tags. Keys and values are sanitized to `[A-Za-z0-9_-]`.
     *
     * @param array<string, string> $tags
     *
     * @throws ValidationException When any key is empty after sanitization.
     */
    public function tags(array $tags): self
    {
        $copy = clone $this;
        $copy->tags = [];
        foreach ($tags as $key => $value) {
            $copy = $copy->tag((string) $key, (string) $value);
        }

        return $copy;
    }

    /**
     * Set an idempotency key unique per team.
     *
     * Repeating the same key returns the original message and does not send again.
     */
    public function idempotencyKey(string $key): self
    {
        $copy = clone $this;
        $copy->idempotencyKey = $key;

        return $copy;
    }

    /**
     * Attach a file. A message may have at most 10 attachments totaling 7 MB decoded.
     *
     * @throws ValidationException When the attachment limit is exceeded.
     */
    public function attach(Attachment $attachment): self
    {
        if (count($this->attachments) >= self::MAX_ATTACHMENTS) {
            throw new ValidationException(
                sprintf('A message may include at most %d attachments.', self::MAX_ATTACHMENTS),
                'validation_error',
                422,
            );
        }

        $copy = clone $this;
        $copy->attachments[] = $attachment;

        return $copy;
    }

    /**
     * Validate documented limits and return the JSON body for `POST /emails`.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     * @throws HtmlTooLargeException
     * @throws AttachmentTooLargeException
     */
    public function toPayload(): array
    {
        $this->assertReady();

        $payload = [
            'to' => [$this->to->toArray()],
            'subject' => $this->subject,
        ];

        if ($this->html !== null) {
            $payload['html'] = $this->html;
        }

        if ($this->text !== null) {
            $payload['text'] = $this->text;
        }

        if ($this->from !== null) {
            $payload['from'] = $this->from->toArray();
        }

        if ($this->replyTo !== null) {
            $payload['reply_to'] = $this->replyTo->toArray();
        }

        if ($this->tags !== []) {
            $payload['tags'] = $this->tags;
        }

        if ($this->idempotencyKey !== null && $this->idempotencyKey !== '') {
            $payload['idempotency_key'] = $this->idempotencyKey;
        }

        if ($this->attachments !== []) {
            $payload['attachments'] = array_map(
                static fn (Attachment $attachment): array => $attachment->toArray(),
                $this->attachments,
            );
        }

        return $payload;
    }

    /**
     * @throws ValidationException
     * @throws HtmlTooLargeException
     * @throws AttachmentTooLargeException
     */
    private function assertReady(): void
    {
        if ($this->subject === null || trim($this->subject) === '') {
            throw new ValidationException('A subject is required.', 'validation_error', 422);
        }

        $hasHtml = $this->html !== null && $this->html !== '';
        $hasText = $this->text !== null && $this->text !== '';
        if (!$hasHtml && !$hasText) {
            throw new ValidationException(
                'Either html or text is required.',
                'validation_error',
                422,
            );
        }

        if ($hasHtml && strlen((string) $this->html) > self::MAX_HTML_BYTES) {
            throw new HtmlTooLargeException(
                sprintf('HTML must be at most %d bytes.', self::MAX_HTML_BYTES),
                'html_too_large',
                422,
            );
        }

        $total = 0;
        foreach ($this->attachments as $attachment) {
            $total += $attachment->decodedSize;
        }

        if ($total > self::MAX_ATTACHMENT_BYTES) {
            throw new AttachmentTooLargeException(
                sprintf('Decoded attachments must total at most %d bytes.', self::MAX_ATTACHMENT_BYTES),
                'attachment_too_large',
                422,
            );
        }
    }

    private static function sanitizeTagPart(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '', $value);
    }
}
