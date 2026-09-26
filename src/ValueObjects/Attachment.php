<?php

declare(strict_types=1);

namespace MailGazelle\ValueObjects;

use MailGazelle\Exceptions\AttachmentException;

/**
 * A file attached to an email, encoded as the API expects.
 *
 * Prefer the named constructors so callers do not have to base64-encode bytes
 * themselves unless they already have encoded content.
 */
final readonly class Attachment
{
    /**
     * @param string $filename Basename only. Path segments are rejected.
     * @param string $content Base64-encoded file bytes.
     * @param string|null $contentType MIME type. Guessed from the filename when omitted.
     * @param string|null $contentId Optional CID for an inline part. A leading `cid:` and angle brackets are stripped.
     */
    private function __construct(
        public string $filename,
        public string $content,
        public ?string $contentType,
        public ?string $contentId,
        public int $decodedSize,
    ) {
    }

    /**
     * Attach a file from the local filesystem.
     *
     * @throws AttachmentException When the path cannot be read, the filename is invalid, or the content id is empty.
     */
    public static function fromPath(
        string $path,
        ?string $filename = null,
        ?string $contentType = null,
        ?string $contentId = null,
    ): self {
        $bytes = @file_get_contents($path);
        if ($bytes === false) {
            throw new AttachmentException(
                sprintf('Unable to read attachment from "%s".', $path),
                'attachment_invalid',
                422,
            );
        }

        return self::fromContents(
            $filename ?? basename($path),
            $bytes,
            $contentType,
            $contentId,
        );
    }

    /**
     * Attach raw (not base64-encoded) file bytes.
     *
     * @throws AttachmentException When the filename is invalid, the contents are empty, or the content id is empty.
     */
    public static function fromContents(
        string $filename,
        string $contents,
        ?string $contentType = null,
        ?string $contentId = null,
    ): self {
        return self::create(
            $filename,
            base64_encode($contents),
            strlen($contents),
            $contentType,
            $contentId,
        );
    }

    /**
     * Attach content that is already base64-encoded.
     *
     * @throws AttachmentException When the filename is invalid, the content is not valid base64, or the content id is empty.
     */
    public static function fromBase64(
        string $filename,
        string $base64,
        ?string $contentType = null,
        ?string $contentId = null,
    ): self {
        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '') {
            throw new AttachmentException(
                sprintf('Attachment "%s" is not valid base64.', $filename),
                'attachment_invalid',
                422,
            );
        }

        return self::create($filename, $base64, strlen($decoded), $contentType, $contentId);
    }

    /**
     * JSON representation used in API request bodies.
     *
     * @return array{filename: string, content: string, content_type?: string, content_id?: string}
     */
    public function toArray(): array
    {
        $payload = [
            'filename' => $this->filename,
            'content' => $this->content,
        ];

        if ($this->contentType !== null) {
            $payload['content_type'] = $this->contentType;
        }

        if ($this->contentId !== null) {
            $payload['content_id'] = $this->contentId;
        }

        return $payload;
    }

    /**
     * @throws AttachmentException
     */
    private static function create(
        string $filename,
        string $base64,
        int $decodedSize,
        ?string $contentType,
        ?string $contentId,
    ): self {
        $filename = trim($filename);
        self::assertBasename($filename);

        if ($decodedSize < 1) {
            throw new AttachmentException(
                sprintf('Attachment "%s" is empty.', $filename),
                'attachment_invalid',
                422,
            );
        }

        $contentType = $contentType === null || trim($contentType) === ''
            ? self::guessContentType($filename)
            : trim($contentType);

        return new self($filename, $base64, $contentType, self::normalizeContentId($contentId), $decodedSize);
    }

    /**
     * @throws AttachmentException When the id is empty or contains whitespace after normalization.
     */
    private static function normalizeContentId(?string $contentId): ?string
    {
        if ($contentId === null || trim($contentId) === '') {
            return null;
        }

        $value = trim($contentId);
        if (str_starts_with(strtolower($value), 'cid:')) {
            $value = substr($value, 4);
        }

        $value = trim($value, " \t<>");
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw new AttachmentException(
                'Attachment content ids must not be empty.',
                'attachment_invalid',
                422,
            );
        }

        return $value;
    }

    /**
     * @throws AttachmentException
     */
    private static function assertBasename(string $filename): void
    {
        if (
            $filename === ''
            || $filename === '.'
            || $filename === '..'
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || $filename !== basename($filename)
        ) {
            throw new AttachmentException(
                'Attachment filenames must be a basename without path segments.',
                'attachment_invalid',
                422,
            );
        }
    }

    private static function guessContentType(string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'html', 'htm' => 'text/html',
            'csv' => 'text/csv',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
            'ics' => 'text/calendar',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
