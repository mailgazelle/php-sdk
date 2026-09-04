# Mail Gazelle public API

Client integration contract for sending transactional email through Mail Gazelle. Update this file in the same change as any public API, auth, error-code, or attachment-limit change.

Operator environment names live in `.env.example`. Do not put AWS keys or raw tokens in this file.

## Base URL and auth

- Base URL: `https://mailgazelle.com/api/v1`
- Authenticate with `Authorization: Bearer tes_…`
- Tokens are minted on a **ready** product (sending DKIM verified and MAIL FROM success). The raw token is shown once in the admin UI.
- Rate limit: 60 requests per minute per token.
- A disabled team or a product that is not `ready` cannot send (`403`, `product_not_ready`).

## Onboarding

1. Create a product with a name and primary domain. Sending identity and MAIL FROM default to `notif.{primary}` and `bounce.notif.{primary}`; both can be edited on create.
2. Add the DNS records from the product checklist for that sending identity and MAIL FROM.
3. Click **Check now** (or wait for the minute poll). The product becomes `ready` only when sending DKIM and MAIL FROM are both success.
4. Create an API token.

The apex domain is not created as an SES identity on the default path. DMARC is recommended, not a ready-gate.

## Endpoints

### `POST /emails`

Persists the message, stores attachments on disk, and queues a send. Returns `202` immediately. SES is not called in the request.

```json
{
  "id": "01J…",
  "status": "queued"
}
```

JSON body:

| Field | Required | Notes |
|---|---|---|
| `to` | yes | Exactly one `{ "email", "name?" }` |
| `subject` | yes | |
| `html` or `text` | one required | HTML ≤ 512 KB |
| `from` | no | Defaults to the product From. Domain must be that product's `primary` or `sending` host |
| `reply_to` | no | Defaults to the product Reply-To |
| `tags` | no | Keys/values sanitized to `[A-Za-z0-9_-]` |
| `idempotency_key` | no | Unique per team. Same key returns the original message and does not send again |
| `attachments` | no | See below |

### `GET /emails/{id}`

Returns status, timestamps, SES message id, last event type, and attachment metadata (filename, content type, size). Does not return HTML, text, or file bytes.

### `GET /domains`

Lists the token's product domains and verification statuses.

## Attachments

Optional JSON array, same shape as Resend/Postmark:

```json
"attachments": [
  {
    "filename": "invoice.pdf",
    "content": "<base64>",
    "content_type": "application/pdf",
    "content_id": null
  }
]
```

- `filename` and `content` are required.
- `content_type` is optional (guessed from the filename).
- `content_id` is optional (inline CID).
- Filename must be a basename — path segments are rejected (`attachment_invalid`).
- Max 10 attachments. Decoded total ≤ 7 MB (`attachment_too_large`).
- Empty or invalid base64 is `attachment_invalid`.

SES Simple cannot carry attachments. Mail Gazelle always sends `Content.Raw`.

## Suppressions

The send path checks the **team** (account) and global suppression lists. A suppressed recipient is stored as a `rejected` message (no SES send) and returns `422`:

```json
{ "message": "This recipient is suppressed.", "code": "recipient_suppressed" }
```

Permanent bounces and complaints write the address to the team list and the SES account suppression list.

## Errors

All API errors use:

```json
{ "message": "…", "code": "…", "details": {} }
```

`details` is optional. Responses never include AWS stack traces, message bodies, or attachment bytes.

| Code | Typical status |
|---|---|
| `unauthenticated` | 401 |
| `product_not_ready` | 403 |
| `validation_error` | 422 |
| `from_not_allowed` | 422 |
| `recipient_suppressed` | 422 |
| `attachment_invalid` | 422 |
| `attachment_too_large` | 422 |
| `html_too_large` | 422 |
| `rate_limited` | 429 |

## Sandbox vs production

Until AWS takes the SES account out of sandbox, you can only send to verified identities. Simulator addresses work in sandbox:

- `success@simulator.amazonses.com`
- `bounce@simulator.amazonses.com`
- `complaint@simulator.amazonses.com`

## Examples

Text send:

```bash
curl -X POST "https://mailgazelle.com/api/v1/emails" \
  -H "Authorization: Bearer tes_…" \
  -H "Content-Type: application/json" \
  -d '{
    "to": [{"email": "user@example.com"}],
    "subject": "Welcome",
    "text": "Thanks for signing up."
  }'
```

Send with a PDF:

```bash
curl -X POST "https://mailgazelle.com/api/v1/emails" \
  -H "Authorization: Bearer tes_…" \
  -H "Content-Type: application/json" \
  -d '{
    "to": [{"email": "user@example.com"}],
    "subject": "Invoice",
    "html": "<p>Your invoice is attached.</p>",
    "attachments": [{
      "filename": "invoice.pdf",
      "content": "'"$PDF_BASE64"'",
      "content_type": "application/pdf"
    }]
  }'
```
