<?php

declare(strict_types=1);

namespace MailGazelle\Emails;

use MailGazelle\Exceptions\AttachmentException;
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

    public const MAX_ATTACHMENT_BYTES = 7 * 1024 * 1024;

    public const MAX_RECIPIENTS = 50;

    public const MAX_TAGS = 48;

    public const MAX_TAG_NAME_LENGTH = 64;

    public const MAX_TAG_VALUE_LENGTH = 256;

    public const MAX_HEADERS = 50;

    public const MAX_HEADER_VALUE_LENGTH = 8192;

    /**
     * @param list<Address> $to
     * @param list<Address> $cc
     * @param list<Address> $bcc
     * @param list<Address> $replyTo
     * @param array<string, string> $headers
     * @param array<string, string> $tags
     * @param list<Attachment> $attachments
     */
    private function __construct(
        private array $to,
        private ?string $subject = null,
        private ?string $text = null,
        private ?string $html = null,
        private ?Address $from = null,
        private array $cc = [],
        private array $bcc = [],
        private array $replyTo = [],
        private bool $omitReplyTo = false,
        private array $headers = [],
        private array $tags = [],
        private ?string $idempotencyKey = null,
        private array $attachments = [],
    ) {
    }

    /**
     * Start a message addressed to one recipient.
     *
     * Add further recipients with {@see addTo()}, {@see cc()}, and {@see bcc()}.
     * Together they may include at most 50 addresses.
     *
     * @throws ValidationException When the address is invalid.
     */
    public static function to(string $email, ?string $name = null): self
    {
        return new self(to: [new Address($email, $name)]);
    }

    /**
     * Add another To address.
     *
     * @throws ValidationException When the address is invalid or the recipient limit is exceeded.
     */
    public function addTo(string $email, ?string $name = null): self
    {
        return $this->appendRecipient('to', new Address($email, $name));
    }

    /**
     * Add a Cc address.
     *
     * @throws ValidationException When the address is invalid or the recipient limit is exceeded.
     */
    public function cc(string $email, ?string $name = null): self
    {
        return $this->appendRecipient('cc', new Address($email, $name));
    }

    /**
     * Add a Bcc address.
     *
     * Bcc is stored and delivered, and omitted from the visible MIME headers.
     *
     * @throws ValidationException When the address is invalid or the recipient limit is exceeded.
     */
    public function bcc(string $email, ?string $name = null): self
    {
        return $this->appendRecipient('bcc', new Address($email, $name));
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
     * Mail Gazelle rejects HTML larger than 512 KB. HTML and text may be sent together.
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
     * Add a Reply-To address.
     *
     * Serialized as an array. Omit this method to keep the product default.
     * A later call clears {@see withoutReplyTo()}.
     *
     * @throws ValidationException When the address is invalid.
     */
    public function replyTo(string $email, ?string $name = null): self
    {
        $copy = clone $this;
        $copy->omitReplyTo = false;
        $copy->replyTo[] = new Address($email, $name);

        return $copy;
    }

    /**
     * Omit Reply-To by sending an empty `reply_to` array.
     *
     * Clears any addresses added with {@see replyTo()}.
     */
    public function withoutReplyTo(): self
    {
        $copy = clone $this;
        $copy->replyTo = [];
        $copy->omitReplyTo = true;

        return $copy;
    }

    /**
     * Add or replace one custom header.
     *
     * Names must contain only letters, numbers, and hyphens. Values cannot
     * contain line breaks and must be at most 8192 characters. At most 50
     * headers. The API ignores `From`, `To`, `Cc`, `Bcc`, `Reply-To`,
     * `Sender`, `Subject`, `Content-Type`, and `Return-Path`. `Message-ID`
     * is kept when it is present.
     *
     * @throws ValidationException When the header is invalid or the limit is exceeded.
     */
    public function header(string $name, string $value): self
    {
        self::assertHeader($name, $value);

        $copy = clone $this;
        $isNew = !array_key_exists($name, $copy->headers);
        if ($isNew && count($copy->headers) >= self::MAX_HEADERS) {
            throw new ValidationException(
                sprintf('A message can include at most %d headers.', self::MAX_HEADERS),
                'validation_error',
                422,
            );
        }

        $copy->headers[$name] = $value;

        return $copy;
    }

    /**
     * Replace all custom headers.
     *
     * @param array<string, string> $headers
     *
     * @throws ValidationException When any header is invalid or the limit is exceeded.
     */
    public function headers(array $headers): self
    {
        $copy = clone $this;
        $copy->headers = [];
        foreach ($headers as $name => $value) {
            $copy = $copy->header((string) $name, (string) $value);
        }

        return $copy;
    }

    /**
     * Add a single tag.
     *
     * Names and values must match `[A-Za-z0-9_-]`. Names are at most 64
     * characters, values at most 256. At most 48 tags. `team_id` and
     * `product_id` are reserved. Invalid tags are rejected, not stripped.
     *
     * @throws ValidationException When the tag is invalid or the limit is exceeded.
     */
    public function tag(string $key, string $value): self
    {
        self::assertTagPart($key, self::MAX_TAG_NAME_LENGTH);
        self::assertTagPart($value, self::MAX_TAG_VALUE_LENGTH);
        if (in_array($key, ['team_id', 'product_id'], true)) {
            throw new ValidationException(
                'The tag names team_id and product_id are reserved.',
                'validation_error',
                422,
            );
        }

        $copy = clone $this;
        $isNew = !array_key_exists($key, $copy->tags);
        if ($isNew && count($copy->tags) >= self::MAX_TAGS) {
            throw new ValidationException(
                sprintf('A message can include at most %d tags.', self::MAX_TAGS),
                'validation_error',
                422,
            );
        }

        $copy->tags[$key] = $value;

        return $copy;
    }

    /**
     * Replace all tags.
     *
     * @param array<string, string> $tags
     *
     * @throws ValidationException When any tag is invalid or the limit is exceeded.
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
     * A replay is not checked against the monthly quota.
     */
    public function idempotencyKey(string $key): self
    {
        $copy = clone $this;
        $copy->idempotencyKey = $key;

        return $copy;
    }

    /**
     * Attach a file.
     *
     * How many files a message may include is set by the team's plan (the
     * default is 10). The decoded total cannot exceed the 7 MB platform ceiling.
     * Duplicate content ids on one message are rejected.
     *
     * @throws AttachmentException When the content id is already used on this message.
     */
    public function attach(Attachment $attachment): self
    {
        if ($attachment->contentId !== null) {
            foreach ($this->attachments as $existing) {
                if ($existing->contentId === $attachment->contentId) {
                    throw new AttachmentException(
                        'Attachment content ids must be unique.',
                        'attachment_invalid',
                        422,
                    );
                }
            }
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
            'to' => array_map(
                static fn (Address $address): array => $address->toArray(),
                $this->to,
            ),
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

        if ($this->cc !== []) {
            $payload['cc'] = array_map(
                static fn (Address $address): array => $address->toArray(),
                $this->cc,
            );
        }

        if ($this->bcc !== []) {
            $payload['bcc'] = array_map(
                static fn (Address $address): array => $address->toArray(),
                $this->bcc,
            );
        }

        if ($this->omitReplyTo) {
            $payload['reply_to'] = [];
        } elseif ($this->replyTo !== []) {
            $payload['reply_to'] = array_map(
                static fn (Address $address): array => $address->toArray(),
                $this->replyTo,
            );
        }

        if ($this->headers !== []) {
            $payload['headers'] = $this->headers;
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
     * @throws ValidationException When the address is invalid or the recipient limit is exceeded.
     */
    private function appendRecipient(string $field, Address $address): self
    {
        if (count($this->to) + count($this->cc) + count($this->bcc) >= self::MAX_RECIPIENTS) {
            throw new ValidationException(
                sprintf('A message can include at most %d recipients.', self::MAX_RECIPIENTS),
                'validation_error',
                422,
            );
        }

        $copy = clone $this;
        if ($field === 'cc') {
            $copy->cc[] = $address;
        } elseif ($field === 'bcc') {
            $copy->bcc[] = $address;
        } else {
            $copy->to[] = $address;
        }

        return $copy;
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

    /**
     * @throws ValidationException
     */
    private static function assertHeader(string $name, string $value): void
    {
        if (preg_match('/^[A-Za-z0-9-]+$/', $name) !== 1) {
            throw new ValidationException(
                'Header names must contain only letters, numbers, and hyphens.',
                'validation_error',
                422,
            );
        }

        if (preg_match('/[\r\n]/', $value) === 1) {
            throw new ValidationException(
                'Header values cannot contain line breaks.',
                'validation_error',
                422,
            );
        }

        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > self::MAX_HEADER_VALUE_LENGTH) {
            throw new ValidationException(
                sprintf('Header values must be at most %d characters.', self::MAX_HEADER_VALUE_LENGTH),
                'validation_error',
                422,
            );
        }
    }

    /**
     * @throws ValidationException
     */
    private static function assertTagPart(string $value, int $maxLength): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1 || strlen($value) > $maxLength) {
            throw new ValidationException(
                'Tag names and values may only contain letters, numbers, underscores, and hyphens.',
                'validation_error',
                422,
            );
        }
    }
}
