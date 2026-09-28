# Mail Gazelle PHP SDK

Official PHP client for the [Mail Gazelle](https://mailgazelle.com) transactional email API.

This package is framework-agnostic: construct a `Client` with your API token and call the email and domain resources. HTTP is performed through a small `TransportInterface`, so applications can plug in their own client or a test double.

The HTTP contract lives in [`doc/api.md`](doc/api.md). Keep that file and this SDK in the same change when endpoints, error codes, or attachment limits change.

## Requirements

- PHP 8.2 or newer
- `ext-curl`
- `ext-json`

There are no other runtime Composer dependencies.

## Install

```bash
composer require mailgazelle/php-sdk
```

## Create a client

```php
use MailGazelle\Client;

$client = new Client(apiToken: 'tes_…');
```

The default API root is `https://mailgazelle.com/api/v1`. Tokens are minted in the Mail Gazelle admin UI for a product that is ready to send. The raw token is shown once.

Optional constructor arguments:

| Argument | Default | Purpose |
|---|---|---|
| `baseUrl` | `https://mailgazelle.com/api/v1` | Override the API root (trailing slash is ignored). Useful in tests. |
| `transport` | `CurlTransport` | Inject a custom `TransportInterface`. |
| `timeout` | `30` seconds | Total request timeout. |
| `connectTimeout` | `10` seconds | Connection timeout. |
| `userAgentSuffix` | `null` | Appended to `mailgazelle-php-sdk/{version}`. |

```php
$client = new Client(
    apiToken: getenv('MAILGAZELLE_API_TOKEN'),
    userAgentSuffix: 'MyApp/2.4',
);
```

Reuse one client per token. Do not create a new instance for every send.

## Send email

Either HTML or plain text is required. Both may be sent together. `to` is one or more recipients. Together with `cc` and `bcc`, a message can include at most 50 addresses.

### Plain text

```php
use MailGazelle\Emails\Email;

$queued = $client->emails()->send(
    Email::to('user@example.com')
        ->subject('Welcome')
        ->text('Thanks for signing up.'),
);

$queued->id();     // Mail Gazelle message id
$queued->status(); // "queued"
```

`POST /emails` returns `202` immediately. The message is stored and queued; delivery happens asynchronously.

### HTML (and optional text)

```php
$client->emails()->send(
    Email::to('user@example.com', 'Ada')
        ->subject('Welcome')
        ->html('<p>Thanks for signing up.</p>')
        ->text('Thanks for signing up.'),
);
```

HTML must be 512 KB or smaller.

### Recipients

```php
Email::to('user@example.com', 'Ada')
    ->addTo('other@example.com')
    ->cc('billing@example.com')
    ->bcc('audit@example.com');
```

`bcc` is stored and delivered, and omitted from the visible MIME headers.

### From, Reply-To, headers, tags, and idempotency

`from` and `reply_to` default to the product values when omitted. A custom From address must use the product's primary or sending domain. `replyTo()` adds an address and the field is sent as an array. `withoutReplyTo()` sends an empty array so Reply-To is omitted.

Custom headers are an object of name to string. Names may contain letters, numbers, and hyphens. Values cannot contain line breaks and must be at most 8192 characters. A message may include at most 50 headers. The API ignores `From`, `To`, `Cc`, `Bcc`, `Reply-To`, `Sender`, `Subject`, `Content-Type`, and `Return-Path`. `Message-ID` is kept when it is present.

Tags are an object of names and values. Each must match `[A-Za-z0-9_-]`. Names are at most 64 characters, values at most 256, and a message may include at most 48 tags. Invalid tags are rejected. `team_id` and `product_id` are reserved.

An idempotency key is unique per team: the same key returns the original message and does not send again. A replay is not checked against quota.

```php
$client->emails()->send(
    Email::to('user@example.com')
        ->subject('Invoice')
        ->html('<p>Your invoice is attached.</p>')
        ->from('notif@example.com', 'App')
        ->replyTo('support@example.com')
        ->header('Message-ID', '<welcome-42@example.com>')
        ->tag('campaign', 'welcome')
        ->idempotencyKey('welcome-user-42'),
);
```

### Attachments

The team's plan sets how many attachments a message may include and their combined decoded size. The defaults are 10 attachments and 7 MB. The SDK enforces the 7 MB platform ceiling. A lower plan limit is enforced by the API: `attachment_too_large`, and the message states that limit. More attachments than the plan allows is `validation_error` from the API.

Filenames must be a basename (path segments are rejected). `content_type` is guessed from the filename when omitted. `content_id` marks an inline part; reference it from HTML as `cid:{content_id}`. A leading `cid:` and surrounding angle brackets are stripped. Empty or duplicate ids are rejected.

The assembled raw MIME (HTML, text, headers, and base64 attachments) must be 10 MB or smaller (`message_too_large`). A monthly decoded-byte allowance, when the plan has one, is a separate `quota_exceeded` error.

```php
use MailGazelle\ValueObjects\Attachment;

$client->emails()->send(
    Email::to('user@example.com')
        ->subject('Invoice')
        ->html('<p>Your invoice is attached.</p><img src="cid:logo">')
        ->attach(Attachment::fromPath('/tmp/invoice.pdf'))
        ->attach(Attachment::fromContents('logo.png', $pngBytes, 'image/png', contentId: 'logo'))
        ->attach(Attachment::fromBase64('terms.pdf', $alreadyEncoded)),
);
```

## Fetch a message

`GET /emails/{id}` returns status, timestamps, the provider message id, the last event type, and attachment metadata. It does not return HTML, text, or file bytes.

```php
$record = $client->emails()->get($queued->id());

$record->status();         // e.g. "queued" or "rejected"
$record->statusEnum();     // EmailStatus when the value is known to this SDK
$record->messageId();      // provider id after accept, or null
$record->lastEventType();
$record->createdAt();
$record->attachments();    // filename, content type, size
$record->toArray();        // full payload, including fields added later
```

## List domains

```php
foreach ($client->domains()->list() as $domain) {
    $domain->name();
    $domain->type();
    $domain->verificationStatuses();
    $domain->toArray();
}
```

## Errors

All failures extend `MailGazelle\Exceptions\MailGazelleException`. Catch that type when you only need to know that a Mail Gazelle call failed. Use a subclass when the recovery path depends on the cause.

```php
use MailGazelle\Exceptions\MailGazelleException;
use MailGazelle\Exceptions\RecipientSuppressedException;
use MailGazelle\Exceptions\RateLimitException;

try {
    $client->emails()->send($email);
} catch (RecipientSuppressedException $exception) {
    // Stored as rejected. Do not retry this address.
} catch (RateLimitException $exception) {
    // 60 requests per minute per token. Back off and retry later.
} catch (MailGazelleException $exception) {
    $exception->message();
    $exception->code();       // API error code
    $exception->httpStatus(); // 0 when no HTTP response was received
    $exception->details();
}
```

The SDK validates documented limits before it calls the API. Local and remote failures of the same kind throw the same exception class.

| API `code` | Exception | Typical HTTP |
|---|---|---|
| `unauthenticated` | `AuthenticationException` | 401 |
| `product_not_ready` | `ProductNotReadyException` | 403 |
| `validation_error` | `ValidationException` | 422 |
| `from_not_allowed` | `FromNotAllowedException` | 422 |
| `recipient_suppressed` | `RecipientSuppressedException` | 422 |
| `attachment_invalid` | `AttachmentException` | 422 |
| `attachment_too_large` | `AttachmentTooLargeException` | 422 |
| `quota_exceeded` | `QuotaExceededException` | 422 |
| `html_too_large` | `HtmlTooLargeException` | 422 |
| `message_too_large` | `MessageTooLargeException` | 422 |
| `rate_limited` | `RateLimitException` | 429 |

`unauthenticated` also covers a token for an archived product. `recipient_suppressed` applies to every `to`, `cc`, and `bcc` address and does not consume quota.

`quota_exceeded` means the send would pass the team's monthly email allowance, daily send limit, or monthly attachment allowance. The message states which limit was hit, and the message is not queued. Plans that allow additional sending, or additional attachment size, are not blocked by the monthly checks. Each distinct recipient counts as one send. Replaying a stored idempotency key does not re-check any limit.

Unknown codes become `ApiException`. Network, DNS, TLS, and timeout failures become `TransportException`. The SDK does not retry automatically; use `idempotency_key` when a caller may repeat a send.

## Plug the client into an application

The SDK does not depend on Laravel, Symfony, or any other framework. Bind `Client` in your container and inject it where you send mail.

### Custom HTTP transport

Implement `MailGazelle\Http\TransportInterface` to use Guzzle, Symfony HttpClient, or a test double:

```php
use MailGazelle\Http\Request;
use MailGazelle\Http\Response;
use MailGazelle\Http\TransportInterface;

final class GuzzleTransport implements TransportInterface
{
    public function __construct(private \GuzzleHttp\Client $guzzle)
    {
    }

    public function send(Request $request): Response
    {
        $response = $this->guzzle->request($request->method, $request->url, [
            'headers' => $request->headers,
            'body' => $request->body,
            'timeout' => $request->timeout,
            'connect_timeout' => $request->connectTimeout,
            'http_errors' => false,
        ]);

        return new Response(
            $response->getStatusCode(),
            (string) $response->getBody(),
        );
    }
}

$client = new Client(
    apiToken: 'tes_…',
    transport: new GuzzleTransport($guzzle),
);
```

### Laravel

```php
use MailGazelle\Client;

$this->app->singleton(Client::class, function () {
    return new Client(apiToken: (string) config('services.mailgazelle.token'));
});
```

### Symfony

```yaml
# config/services.yaml
MailGazelle\Client:
    arguments:
        $apiToken: '%env(MAILGAZELLE_API_TOKEN)%'
```

## Keeping this package current

Treat the API contract and this SDK as one change:

1. Update [`doc/api.md`](doc/api.md) first (endpoints, error codes, limits).
2. Add resources, DTO fields, and exception classes additively. Do not rename public types without a major version.
3. Read unknown JSON keys through `toArray()` so new response fields do not break older clients.
4. Record the change in [`CHANGELOG.md`](CHANGELOG.md).
5. Bump the Composer version and `MailGazelle\Client::VERSION` together. The User-Agent reads that constant.

This major version covers `POST /emails`, `GET /emails/{id}`, and `GET /domains`. Further endpoints should land as new resources on `Client` in minor releases.

## Development

```bash
composer install
composer test
composer analyse
```
