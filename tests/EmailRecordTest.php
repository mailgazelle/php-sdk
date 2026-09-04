<?php

declare(strict_types=1);

namespace MailGazelle\Tests;

use MailGazelle\Client;
use MailGazelle\Emails\EmailStatus;
use PHPUnit\Framework\TestCase;

final class EmailRecordTest extends TestCase
{
    public function testGetEmailMapsDocumentedFieldsAndUnknownKeys(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(200, [
            'id' => '01JMSG',
            'status' => 'queued',
            'message_id' => 'provider-abc',
            'last_event_type' => 'queued',
            'created_at' => '2026-09-04T00:00:00+00:00',
            'updated_at' => '2026-09-04T00:01:00+00:00',
            'sent_at' => '2026-09-04T00:02:00+00:00',
            'future_field' => 'keep-me',
            'attachments' => [
                [
                    'filename' => 'invoice.pdf',
                    'content_type' => 'application/pdf',
                    'size' => 2048,
                    'checksum' => 'abc',
                ],
            ],
        ]);

        $record = (new Client('tes_secret', transport: $transport))->emails()->get('01JMSG');

        $this->assertSame('GET', $transport->lastRequest()->method);
        $this->assertSame('https://mailgazelle.com/api/v1/emails/01JMSG', $transport->lastRequest()->url);
        $this->assertSame('01JMSG', $record->id());
        $this->assertSame('queued', $record->status());
        $this->assertSame(EmailStatus::Queued, $record->statusEnum());
        $this->assertSame('provider-abc', $record->messageId());
        $this->assertSame('queued', $record->lastEventType());
        $this->assertSame('2026-09-04T00:00:00+00:00', $record->createdAt()?->format('c'));
        $this->assertSame('2026-09-04T00:01:00+00:00', $record->updatedAt()?->format('c'));
        $this->assertSame('2026-09-04T00:02:00+00:00', $record->sentAt()?->format('c'));
        $this->assertSame('keep-me', $record->toArray()['future_field']);
        $this->assertCount(1, $record->attachments());
        $this->assertSame('invoice.pdf', $record->attachments()[0]->filename());
        $this->assertSame('application/pdf', $record->attachments()[0]->contentType());
        $this->assertSame(2048, $record->attachments()[0]->size());
        $this->assertSame('abc', $record->attachments()[0]->toArray()['checksum']);
    }

    public function testMessageIdFallsBackToVendorSpecificKeyWithoutExposingIt(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(200, [
            'id' => '01JMSG',
            'status' => 'rejected',
            'ses_message_id' => 'hidden-provider-id',
            'last_event' => 'bounce',
        ]);

        $record = (new Client('tes_secret', transport: $transport))->emails()->get('01JMSG');

        $this->assertSame('hidden-provider-id', $record->messageId());
        $this->assertSame('bounce', $record->lastEventType());
        $this->assertSame(EmailStatus::Rejected, $record->statusEnum());
    }

    public function testWrappedDataObjectIsUnwrapped(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(200, [
            'data' => [
                'id' => '01JWRAP',
                'status' => 'queued',
            ],
        ]);

        $record = (new Client('tes_secret', transport: $transport))->emails()->get('01JWRAP');
        $this->assertSame('01JWRAP', $record->id());
    }

    public function testEmptyIdIsRejectedBeforeHttp(): void
    {
        $transport = new FakeTransport();
        $client = new Client('tes_secret', transport: $transport);

        try {
            $client->emails()->get('  ');
            $this->fail('Expected ValidationException');
        } catch (\MailGazelle\Exceptions\ValidationException) {
            $this->assertSame([], $transport->requests);
        }
    }
}
