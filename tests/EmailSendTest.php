<?php

declare(strict_types=1);

namespace MailGazelle\Tests;

use MailGazelle\Client;
use MailGazelle\Emails\Email;
use MailGazelle\Emails\EmailStatus;
use MailGazelle\ValueObjects\Attachment;
use PHPUnit\Framework\TestCase;

final class EmailSendTest extends TestCase
{
    public function testTextOnlyPayload(): void
    {
        $transport = $this->transport();
        $this->client($transport)->emails()->send(
            Email::to('user@example.com')->subject('Welcome')->text('Thanks for signing up.'),
        );

        $this->assertSame([
            'to' => [['email' => 'user@example.com']],
            'subject' => 'Welcome',
            'text' => 'Thanks for signing up.',
        ], $transport->lastJsonBody());
    }

    public function testHtmlOnlyPayload(): void
    {
        $transport = $this->transport();
        $this->client($transport)->emails()->send(
            Email::to('user@example.com', 'Ada')->subject('Welcome')->html('<p>Hello</p>'),
        );

        $this->assertSame([
            'to' => [['email' => 'user@example.com', 'name' => 'Ada']],
            'subject' => 'Welcome',
            'html' => '<p>Hello</p>',
        ], $transport->lastJsonBody());
    }

    public function testHtmlAndTextWithOptionalFields(): void
    {
        $transport = $this->transport();
        $queued = $this->client($transport)->emails()->send(
            Email::to('user@example.com')
                ->subject('Invoice')
                ->html('<p>Your invoice</p>')
                ->text('Your invoice')
                ->from('notif@example.com', 'App')
                ->replyTo('support@example.com')
                ->replyTo('billing@example.com', 'Billing')
                ->tag('campaign', 'welcome')
                ->tag('source', 'api')
                ->idempotencyKey('welcome-user-42'),
        );

        $this->assertSame('01JTEST', $queued->id());
        $this->assertSame('queued', $queued->status());
        $this->assertSame([
            'to' => [['email' => 'user@example.com']],
            'subject' => 'Invoice',
            'html' => '<p>Your invoice</p>',
            'text' => 'Your invoice',
            'from' => ['email' => 'notif@example.com', 'name' => 'App'],
            'reply_to' => [
                ['email' => 'support@example.com'],
                ['email' => 'billing@example.com', 'name' => 'Billing'],
            ],
            'tags' => [
                'campaign' => 'welcome',
                'source' => 'api',
            ],
            'idempotency_key' => 'welcome-user-42',
        ], $transport->lastJsonBody());
    }

    public function testRecipientsHeadersAndOmittedReplyTo(): void
    {
        $transport = $this->transport();
        $this->client($transport)->emails()->send(
            Email::to('user@example.com', 'Ada')
                ->addTo('other@example.com')
                ->cc('billing@example.com', 'Billing')
                ->bcc('audit@example.com')
                ->subject('Hello')
                ->text('Hi')
                ->withoutReplyTo()
                ->header('X-Campaign', 'welcome')
                ->header('From', 'ignored@example.com')
                ->header('Message-ID', '<id@example.com>'),
        );

        $this->assertSame([
            'to' => [
                ['email' => 'user@example.com', 'name' => 'Ada'],
                ['email' => 'other@example.com'],
            ],
            'subject' => 'Hello',
            'text' => 'Hi',
            'cc' => [['email' => 'billing@example.com', 'name' => 'Billing']],
            'bcc' => [['email' => 'audit@example.com']],
            'reply_to' => [],
            'headers' => [
                'X-Campaign' => 'welcome',
                'From' => 'ignored@example.com',
                'Message-ID' => '<id@example.com>',
            ],
        ], $transport->lastJsonBody());
    }

    public function testHeadersHelperReplacesExistingHeaders(): void
    {
        $transport = $this->transport();
        $this->client($transport)->emails()->send(
            Email::to('user@example.com')
                ->subject('Hello')
                ->text('Hi')
                ->header('X-Old', 'one')
                ->headers(['X-Campaign' => 'onboarding']),
        );

        $body = $transport->lastJsonBody();
        $this->assertSame(['X-Campaign' => 'onboarding'], $body['headers']);
    }

    public function testTagsHelperReplacesExistingTags(): void
    {
        $transport = $this->transport();
        $this->client($transport)->emails()->send(
            Email::to('user@example.com')
                ->subject('Hello')
                ->text('Hi')
                ->tag('old', 'one')
                ->tags(['campaign' => 'onboarding', 'source' => 'api']),
        );

        $body = $transport->lastJsonBody();
        $this->assertSame([
            'campaign' => 'onboarding',
            'source' => 'api',
        ], $body['tags']);
    }

    public function testAttachmentsAreBase64Encoded(): void
    {
        $transport = $this->transport();
        $bytes = '%PDF-invoice';
        $this->client($transport)->emails()->send(
            Email::to('user@example.com')
                ->subject('Invoice')
                ->html('<p>Attached</p>')
                ->attach(Attachment::fromContents('invoice.pdf', $bytes, 'application/pdf'))
                ->attach(Attachment::fromBase64('logo.png', base64_encode('PNG'), contentId: 'cid:<logo>')),
        );

        $attachments = $transport->lastJsonBody()['attachments'];
        $this->assertSame('invoice.pdf', $attachments[0]['filename']);
        $this->assertSame(base64_encode($bytes), $attachments[0]['content']);
        $this->assertSame('application/pdf', $attachments[0]['content_type']);
        $this->assertSame('logo.png', $attachments[1]['filename']);
        $this->assertSame(base64_encode('PNG'), $attachments[1]['content']);
        $this->assertSame('image/png', $attachments[1]['content_type']);
        $this->assertSame('logo', $attachments[1]['content_id']);
    }

    public function testAttachmentFromPath(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mgz');
        $this->assertIsString($path);
        file_put_contents($path, 'plain-file');

        try {
            $transport = $this->transport();
            $this->client($transport)->emails()->send(
                Email::to('user@example.com')
                    ->subject('File')
                    ->text('See attachment')
                    ->attach(Attachment::fromPath($path, 'notes.txt')),
            );

            $attachment = $transport->lastJsonBody()['attachments'][0];
            $this->assertSame('notes.txt', $attachment['filename']);
            $this->assertSame(base64_encode('plain-file'), $attachment['content']);
            $this->assertSame('text/plain', $attachment['content_type']);
        } finally {
            unlink($path);
        }
    }

    public function testIdempotentReplayStillPostsTheKey(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(202, ['id' => '01JORIGINAL', 'status' => 'queued']);

        $queued = $this->client($transport)->emails()->send(
            Email::to('user@example.com')
                ->subject('Welcome')
                ->text('Hello')
                ->idempotencyKey('welcome-user-42'),
        );

        $this->assertSame('01JORIGINAL', $queued->id());
        $this->assertSame(EmailStatus::Queued->value, $queued->status());
        $this->assertSame('welcome-user-42', $transport->lastJsonBody()['idempotency_key']);
    }

    public function testValidationFailureDoesNotHitTheTransport(): void
    {
        $transport = new FakeTransport();
        $client = $this->client($transport);

        try {
            $client->emails()->send(Email::to('user@example.com')->subject('Missing body'));
            $this->fail('Expected validation to fail.');
        } catch (\MailGazelle\Exceptions\ValidationException) {
            $this->assertSame([], $transport->requests);
        }
    }

    private function transport(): FakeTransport
    {
        $transport = new FakeTransport();
        $transport->queueJson(202, ['id' => '01JTEST', 'status' => 'queued']);

        return $transport;
    }

    private function client(FakeTransport $transport): Client
    {
        return new Client('tes_secret', transport: $transport);
    }
}
