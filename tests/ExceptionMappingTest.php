<?php

declare(strict_types=1);

namespace MailGazelle\Tests;

use MailGazelle\Client;
use MailGazelle\Emails\Email;
use MailGazelle\Exceptions\ApiException;
use MailGazelle\Exceptions\AttachmentException;
use MailGazelle\Exceptions\AttachmentTooLargeException;
use MailGazelle\Exceptions\AuthenticationException;
use MailGazelle\Exceptions\FromNotAllowedException;
use MailGazelle\Exceptions\HtmlTooLargeException;
use MailGazelle\Exceptions\MailGazelleException;
use MailGazelle\Exceptions\MessageTooLargeException;
use MailGazelle\Exceptions\ProductNotReadyException;
use MailGazelle\Exceptions\QuotaExceededException;
use MailGazelle\Exceptions\RateLimitException;
use MailGazelle\Exceptions\RecipientSuppressedException;
use MailGazelle\Exceptions\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExceptionMappingTest extends TestCase
{
    /**
     * @param class-string<MailGazelleException> $exceptionClass
     */
    #[DataProvider('errorCodeProvider')]
    public function testErrorCodeMapsToException(
        string $code,
        int $status,
        string $exceptionClass,
    ): void {
        $transport = new FakeTransport();
        $transport->queueJson($status, [
            'message' => 'Boom for ' . $code,
            'code' => $code,
            'details' => ['field' => 'to'],
        ]);

        $client = new Client('tes_secret', transport: $transport);

        try {
            $client->emails()->send(
                Email::to('user@example.com')->subject('Hello')->text('Hi'),
            );
            $this->fail('Expected ' . $exceptionClass);
        } catch (MailGazelleException $exception) {
            $this->assertInstanceOf($exceptionClass, $exception);
            $this->assertSame('Boom for ' . $code, $exception->message());
            $this->assertSame($code, $exception->code());
            $this->assertSame($status, $exception->httpStatus());
            $this->assertSame(['field' => 'to'], $exception->details());
        }
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: class-string<MailGazelleException>}>
     */
    public static function errorCodeProvider(): array
    {
        return [
            'unauthenticated' => ['unauthenticated', 401, AuthenticationException::class],
            'product_not_ready' => ['product_not_ready', 403, ProductNotReadyException::class],
            'validation_error' => ['validation_error', 422, ValidationException::class],
            'from_not_allowed' => ['from_not_allowed', 422, FromNotAllowedException::class],
            'recipient_suppressed' => ['recipient_suppressed', 422, RecipientSuppressedException::class],
            'attachment_invalid' => ['attachment_invalid', 422, AttachmentException::class],
            'attachment_too_large' => ['attachment_too_large', 422, AttachmentTooLargeException::class],
            'quota_exceeded' => ['quota_exceeded', 422, QuotaExceededException::class],
            'html_too_large' => ['html_too_large', 422, HtmlTooLargeException::class],
            'message_too_large' => ['message_too_large', 422, MessageTooLargeException::class],
            'rate_limited' => ['rate_limited', 429, RateLimitException::class],
        ];
    }

    public function testUnknownCodeBecomesApiException(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(500, [
            'message' => 'Something broke.',
            'code' => 'unexpected_failure',
        ]);

        $client = new Client('tes_secret', transport: $transport);

        try {
            $client->emails()->send(
                Email::to('user@example.com')->subject('Hello')->text('Hi'),
            );
            $this->fail('Expected ApiException');
        } catch (ApiException $exception) {
            $this->assertSame('unexpected_failure', $exception->code());
            $this->assertSame(500, $exception->httpStatus());
        }
    }

    public function testStatusFallbackWhenCodeIsMissing(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(401, ['message' => 'No token.']);

        $client = new Client('tes_secret', transport: $transport);

        $this->expectException(AuthenticationException::class);
        $client->emails()->send(
            Email::to('user@example.com')->subject('Hello')->text('Hi'),
        );
    }

    public function testInvalidJsonSuccessPayload(): void
    {
        $transport = new FakeTransport();
        $transport->queue(new \MailGazelle\Http\Response(202, '"just-a-string"'));

        $client = new Client('tes_secret', transport: $transport);

        $this->expectException(ApiException::class);
        $client->emails()->send(
            Email::to('user@example.com')->subject('Hello')->text('Hi'),
        );
    }
}
