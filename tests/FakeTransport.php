<?php

declare(strict_types=1);

namespace MailGazelle\Tests;

use MailGazelle\Exceptions\TransportException;
use MailGazelle\Http\Request;
use MailGazelle\Http\Response;
use MailGazelle\Http\TransportInterface;

/**
 * Scripted transport for unit tests.
 */
final class FakeTransport implements TransportInterface
{
    /**
     * @var list<Request>
     */
    public array $requests = [];

    /**
     * @var list<Response|\Throwable>
     */
    private array $queue = [];

    public function queue(Response $response): void
    {
        $this->queue[] = $response;
    }

    /**
     * @param array<mixed> $payload
     * @param array<string, string> $headers
     */
    public function queueJson(int $status, array $payload, array $headers = []): void
    {
        $this->queue[] = new Response(
            $status,
            json_encode($payload, JSON_THROW_ON_ERROR),
            $headers,
        );
    }

    public function queueException(\Throwable $exception): void
    {
        $this->queue[] = $exception;
    }

    public function lastRequest(): Request
    {
        if ($this->requests === []) {
            throw new \RuntimeException('No HTTP request was sent.');
        }

        return $this->requests[array_key_last($this->requests)];
    }

    /**
     * @return array<mixed>
     */
    public function lastJsonBody(): array
    {
        $body = $this->lastRequest()->body;
        if ($body === null || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);
        if ($next instanceof \Throwable) {
            throw $next;
        }

        if ($next instanceof Response) {
            return $next;
        }

        throw new TransportException('FakeTransport has no queued response.', 'transport_error');
    }
}
