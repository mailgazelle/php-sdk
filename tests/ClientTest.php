<?php

declare(strict_types=1);

namespace MailGazelle\Tests;

use MailGazelle\Client;
use MailGazelle\Emails\Email;
use MailGazelle\Exceptions\TransportException;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testConstructorRejectsEmptyToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A Mail Gazelle API token is required.');

        new Client('   ');
    }

    public function testConstructorRejectsNonPositiveTimeouts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client('tes_token', timeout: 0);
    }

    public function testDefaultBaseUrlAndHeadersAreSent(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(202, ['id' => '01JTEST', 'status' => 'queued']);

        $client = new Client('tes_secret', transport: $transport);
        $client->emails()->send(
            Email::to('user@example.com')->subject('Welcome')->text('Hello'),
        );

        $request = $transport->lastRequest();
        $this->assertSame('POST', $request->method);
        $this->assertSame('https://mailgazelle.com/api/v1/emails', $request->url);
        $this->assertSame('Bearer tes_secret', $request->headers['Authorization']);
        $this->assertSame('application/json', $request->headers['Accept']);
        $this->assertSame('mailgazelle-php-sdk/' . Client::VERSION, $request->headers['User-Agent']);
        $this->assertSame('application/json', $request->headers['Content-Type']);
        $this->assertSame(30.0, $request->timeout);
        $this->assertSame(10.0, $request->connectTimeout);
    }

    public function testCustomBaseUrlStripsTrailingSlash(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(202, ['id' => '01JTEST', 'status' => 'queued']);

        $client = new Client(
            apiToken: 'tes_secret',
            baseUrl: 'https://staging.mailgazelle.com/api/v1/',
            transport: $transport,
            timeout: 12.5,
            connectTimeout: 3.5,
            userAgentSuffix: 'MyApp/1.2',
        );
        $client->emails()->send(
            Email::to('user@example.com')->subject('Welcome')->text('Hello'),
        );

        $request = $transport->lastRequest();
        $this->assertSame('https://staging.mailgazelle.com/api/v1/emails', $request->url);
        $this->assertSame('mailgazelle-php-sdk/' . Client::VERSION . ' MyApp/1.2', $request->headers['User-Agent']);
        $this->assertSame(12.5, $request->timeout);
        $this->assertSame(3.5, $request->connectTimeout);
    }

    public function testTransportExceptionsPropagate(): void
    {
        $transport = new FakeTransport();
        $transport->queueException(new TransportException('Connection timed out.', 'transport_error'));

        $client = new Client('tes_secret', transport: $transport);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Connection timed out.');

        $client->emails()->send(
            Email::to('user@example.com')->subject('Welcome')->text('Hello'),
        );
    }
}
