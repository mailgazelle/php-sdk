<?php

declare(strict_types=1);

namespace MailGazelle\Emails;

use MailGazelle\Exceptions\AttachmentTooLargeException;
use MailGazelle\Exceptions\HtmlTooLargeException;
use MailGazelle\Exceptions\MailGazelleException;
use MailGazelle\Exceptions\ValidationException;
use MailGazelle\Http\HttpClient;

/**
 * Send and inspect transactional emails.
 */
final class EmailsResource
{
    /**
     * @internal
     */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Persist a message, store attachments, and queue a send.
     *
     * Returns immediately with HTTP 202. Delivery is asynchronous. The same
     * idempotency key returns the original message and does not send again.
     * A replay is not checked against quota.
     *
     * @throws ValidationException
     * @throws HtmlTooLargeException
     * @throws AttachmentTooLargeException
     * @throws MailGazelleException
     */
    public function send(Email $email): QueuedEmail
    {
        $payload = $email->toPayload();
        $response = $this->http->request('POST', '/emails', $payload);

        return QueuedEmail::fromArray($response);
    }

    /**
     * Fetch status, timestamps, provider message id, last event, and attachment metadata.
     *
     * HTML, text, and file bytes are not returned.
     *
     * @throws MailGazelleException
     */
    public function get(string $id): EmailRecord
    {
        $id = trim($id);
        if ($id === '') {
            throw new ValidationException('An email id is required.', 'validation_error', 422);
        }

        $response = $this->http->request('GET', '/emails/' . rawurlencode($id));

        return EmailRecord::fromArray($response);
    }
}
