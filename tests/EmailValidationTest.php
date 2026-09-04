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

    public function testMoreThanTenAttachmentsIsRejected(): void
    {
        $email = Email::to('user@example.com')->subject('Files')->text('See attached');
        for ($i = 0; $i < Email::MAX_ATTACHMENTS; ++$i) {
            $email = $email->attach(Attachment::fromContents('file' . $i . '.txt', 'x'));
        }

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('A message may include at most 10 attachments.');
        $email->attach(Attachment::fromContents('overflow.txt', 'x'));
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
