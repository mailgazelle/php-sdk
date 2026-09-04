<?php

declare(strict_types=1);

namespace MailGazelle\Tests;

use MailGazelle\Client;
use PHPUnit\Framework\TestCase;

final class DomainTest extends TestCase
{
    public function testListAcceptsRootArray(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(200, [
            [
                'name' => 'example.com',
                'type' => 'primary',
                'verification' => [
                    'dkim' => 'success',
                    'mail_from' => 'pending',
                ],
                'extra' => true,
            ],
        ]);

        $domains = (new Client('tes_secret', transport: $transport))->domains()->list();

        $this->assertSame('GET', $transport->lastRequest()->method);
        $this->assertSame('https://mailgazelle.com/api/v1/domains', $transport->lastRequest()->url);
        $this->assertCount(1, $domains);
        $this->assertSame('example.com', $domains[0]->name());
        $this->assertSame('primary', $domains[0]->type());
        $this->assertSame([
            'dkim' => 'success',
            'mail_from' => 'pending',
        ], $domains[0]->verificationStatuses());
        $this->assertTrue($domains[0]->toArray()['extra']);
    }

    public function testListAcceptsDataWrapperAndAlternateKeys(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(200, [
            'data' => [
                [
                    'domain' => 'notif.example.com',
                    'kind' => 'sending',
                    'dkim' => 'success',
                    'mail_from' => 'success',
                ],
            ],
        ]);

        $domains = (new Client('tes_secret', transport: $transport))->domains()->list();

        $this->assertCount(1, $domains);
        $this->assertSame('notif.example.com', $domains[0]->name());
        $this->assertSame('sending', $domains[0]->type());
        $this->assertSame([
            'dkim' => 'success',
            'mail_from' => 'success',
        ], $domains[0]->verificationStatuses());
    }

    public function testUnknownStatusRemainsOnRawRecord(): void
    {
        $transport = new FakeTransport();
        $transport->queueJson(200, [
            'domains' => [
                [
                    'host' => 'bounce.example.com',
                    'verification_statuses' => [
                        ['name' => 'mail_from', 'status' => 'success'],
                    ],
                ],
            ],
        ]);

        $domains = (new Client('tes_secret', transport: $transport))->domains()->list();
        $this->assertSame('bounce.example.com', $domains[0]->name());
        $this->assertSame(['mail_from' => 'success'], $domains[0]->verificationStatuses());
    }
}
