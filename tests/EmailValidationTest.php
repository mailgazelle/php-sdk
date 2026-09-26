<?php

declare(strict_types=1);

namespace MailGazelle\Tests;

use MailGazelle\Emails\Email;
use MailGazelle\Exceptions\AttachmentException;
use MailGazelle\Exceptions\AttachmentTooLargeException;
use MailGazelle\Exceptions\HtmlTooLargeException;
use MailGazelle\Exceptions\ValidationException;
use MailGazelle\ValueObjects\Address;
use MailGazelle\ValueObjects\Attachment;
use PHPUnit\Framework\TestCase;

final class EmailValidationTest extends TestCase
{
    public function testInvalidRecipientIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Email::to('not-an-email');
    }

    public function testInvalidFromIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Email::to('user@example.com')->from('nope');
    }

    public function testSubjectIsRequired(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A subject is required.');
        Email::to('user@example.com')->text('Hello')->toPayload();
    }

    public function testHtmlOrTextIsRequired(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Either html or text is required.');
        Email::to('user@example.com')->subject('Hello')->toPayload();
    }

    public function testHtmlTooLarge(): void
    {
        $this->expectException(HtmlTooLargeException::class);
        Email::to('user@example.com')
            ->subject('Hello')
            ->html(str_repeat('a', Email::MAX_HTML_BYTES + 1))
            ->toPayload();
    }

    public function testEmptyTagKeyIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Email::to('user@example.com')->tag('!!!', 'value');
    }

    public function testInvalidTagCharactersAreRejected(): void
    {
        $this->expectException(ValidationException::class);
        Email::to('user@example.com')->tag('weird key!', 'value with spaces');
    }

    public function testReservedTagNameIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('reserved');
        Email::to('user@example.com')->tag('product_id', 'abc');
    }

    public function testTagLongerThanLimitIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        Email::to('user@example.com')->tag(str_repeat('a', Email::MAX_TAG_NAME_LENGTH + 1), 'ok');
    }

    public function testMoreThanFortyEightTagsIsRejected(): void
    {
        $email = Email::to('user@example.com');
        for ($i = 0; $i < Email::MAX_TAGS; ++$i) {
            $email = $email->tag('tag' . $i, 'value');
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at most 48 tags');
        $email->tag('overflow', 'value');
    }

    public function testAtMostFiftyRecipients(): void
    {
        $email = Email::to('user0@example.com');
        for ($i = 1; $i < Email::MAX_RECIPIENTS; ++$i) {
            $email = ($i % 2 === 0)
                ? $email->cc('user' . $i . '@example.com')
                : $email->addTo('user' . $i . '@example.com');
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at most 50 recipients');
        $email->bcc('overflow@example.com');
    }

    public function testInvalidHeaderNameIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Header names');
        Email::to('user@example.com')->header('Bad Name', 'x');
    }

    public function testHeaderValueLineBreakIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('line breaks');
        Email::to('user@example.com')->header('X-Custom', "a\nb");
    }

    public function testHeaderValueLongerThanLimitIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('8192');
        Email::to('user@example.com')->header('X-Custom', str_repeat('a', Email::MAX_HEADER_VALUE_LENGTH + 1));
    }

    public function testMoreThanFiftyHeadersIsRejected(): void
    {
        $email = Email::to('user@example.com');
        for ($i = 0; $i < Email::MAX_HEADERS; ++$i) {
            $email = $email->header('X-H' . $i, 'value');
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('at most 50 headers');
        $email->header('X-Overflow', 'value');
    }

    public function testAttachmentCountIsNotCappedLocally(): void
    {
        $email = Email::to('user@example.com')->subject('Files')->text('See attached');
        for ($i = 0; $i < 11; ++$i) {
            $email = $email->attach(Attachment::fromContents('file' . $i . '.txt', 'x'));
        }

        $payload = $email->toPayload();
        $this->assertCount(11, $payload['attachments']);
    }

    public function testContentIdIsNormalized(): void
    {
        $attachment = Attachment::fromContents('logo.png', 'PNG', contentId: 'cid:<logo>');
        $this->assertSame('logo', $attachment->contentId);
    }

    public function testEmptyContentIdIsRejected(): void
    {
        $this->expectException(AttachmentException::class);
        Attachment::fromContents('logo.png', 'PNG', contentId: 'cid:<>');
    }

    public function testContentIdWithWhitespaceIsRejected(): void
    {
        $this->expectException(AttachmentException::class);
        Attachment::fromContents('logo.png', 'PNG', contentId: 'lo go');
    }

    public function testDuplicateContentIdIsRejected(): void
    {
        $this->expectException(AttachmentException::class);
        $this->expectExceptionMessage('unique');
        Email::to('user@example.com')
            ->attach(Attachment::fromContents('a.png', 'a', contentId: 'logo'))
            ->attach(Attachment::fromContents('b.png', 'b', contentId: 'cid:<logo>'));
    }

    public function testWithoutReplyToClearsAddresses(): void
    {
        $payload = Email::to('user@example.com')
            ->subject('Hi')
            ->text('Hi')
            ->replyTo('support@example.com')
            ->replyTo('other@example.com')
            ->withoutReplyTo()
            ->toPayload();

        $this->assertSame([], $payload['reply_to']);
    }

    public function testReplyToAfterOmitIncludesTheAddress(): void
    {
        $payload = Email::to('user@example.com')
            ->subject('Hi')
            ->text('Hi')
            ->withoutReplyTo()
            ->replyTo('support@example.com', 'Support')
            ->toPayload();

        $this->assertSame(
            [['email' => 'support@example.com', 'name' => 'Support']],
            $payload['reply_to'],
        );
    }

    public function testDecodedAttachmentsOverSevenMegabytes(): void
    {
        $chunk = str_repeat('a', (int) (Email::MAX_ATTACHMENT_BYTES / 2) + 1);

        $this->expectException(AttachmentTooLargeException::class);
        Email::to('user@example.com')
            ->subject('Huge')
            ->text('See attached')
            ->attach(Attachment::fromContents('a.bin', $chunk))
            ->attach(Attachment::fromContents('b.bin', $chunk))
            ->toPayload();
    }

    public function testPathFilenameIsRejected(): void
    {
        $this->expectException(AttachmentException::class);
        Attachment::fromContents('../invoice.pdf', 'bytes');
    }

    public function testInvalidBase64IsRejected(): void
    {
        $this->expectException(AttachmentException::class);
        Attachment::fromBase64('invoice.pdf', '%%%not-base64%%%');
    }

    public function testEmptyAttachmentIsRejected(): void
    {
        $this->expectException(AttachmentException::class);
        Attachment::fromContents('empty.txt', '');
    }

    public function testMissingFileIsRejected(): void
    {
        $this->expectException(AttachmentException::class);
        Attachment::fromPath('/tmp/mailgazelle-missing-file-' . uniqid('', true) . '.pdf');
    }

    public function testAddressOmitsBlankName(): void
    {
        $address = new Address('user@example.com', '   ');
        $this->assertSame(['email' => 'user@example.com'], $address->toArray());
    }
}
