# Webhooks

{CONFIG:app.name} can send HTTP POST notifications to your server when events occur, such as a secret being retrieved. Webhooks are available on plans that include API access. See [Pricing]({ROUTE:plans.index}) for plan details.

## Setup

1. Go to [Notification Settings]({ROUTE:user.notification-settings.index}) and enter your HTTPS endpoint URL in the **Webhook URL** field.
2. A signing secret is automatically generated when you save your webhook URL. Use this secret to verify that incoming requests are genuinely from {CONFIG:app.name}.
3. Click **Send Test** to dispatch a `ping` event and confirm your endpoint is receiving requests.

## Events

| Event | Trigger | Description |
|-------|---------|-------------|
| `retrieved` | A secret is opened by a recipient | Sent automatically when any of your secrets is accessed via its share link |
| `ping` | Manual test from Notification Settings | Sent when you click **Send Test** — useful for verifying your endpoint |

## Payload Format

All webhook payloads are sent as JSON via HTTP POST:

```json
{
  "event": "retrieved",
  "hash_id": "abc123def456",
  "created_at": "2026-01-15T10:30:00+00:00",
  "retrieved_at": "2026-01-15T14:22:00+00:00"
}
```

For `ping` events, the `hash_id` will be prefixed with `test-` (e.g., `test-xK3mQ`) and both timestamps will be the current time.

### Headers

| Header | Description |
|--------|-------------|
| `Content-Type` | `application/json` |
| `X-Signature-256` | HMAC-SHA256 signature of the raw JSON body |
| `User-Agent` | `FlashView-Webhook/1.0` |

## Signature Verification

Every webhook request includes an `X-Signature-256` header containing an HMAC-SHA256 signature. You should verify this signature to ensure the request is authentic and has not been tampered with.

The signature format is: `sha256=<hex-digest>`

### PHP

```php
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, $webhookSecret);

if (! hash_equals($expected, $signature)) {
    http_response_code(401);
    exit('Invalid signature');
}
```

### Node.js

```javascript
const crypto = require('crypto');

function verifySignature(payload, signature, secret) {
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');

  return crypto.timingSafeEqual(
    Buffer.from(expected),
    Buffer.from(signature)
  );
}
```

### Python

```python
import hmac
import hashlib

def verify_signature(payload: bytes, signature: str, secret: str) -> bool:
    expected = 'sha256=' + hmac.new(
        secret.encode(), payload, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)
```

## Retry Policy

If your endpoint returns a non-2xx status code or the request times out (10 seconds), {CONFIG:app.name} will retry delivery using exponential backoff:

| Attempt | Delay |
|---------|-------|
| 1 | 30 seconds |
| 2 | 1 minute |
| 3 | 2 minutes |
| 4 | 5 minutes |
| 5 | 15 minutes |
| 6 | 30 minutes |
| 7 | 1 hour |
| 8 | 2 hours |
| 9 | 4 hours |
| 10 | 8 hours |

Retries stop after 24 hours. After that, the delivery is marked as permanently failed and no further attempts are made.

## Requirements

- Your endpoint must be HTTPS.
- Your endpoint must respond within 10 seconds.
- Your endpoint must return a 2xx status code to acknowledge receipt.
- Redirects are followed (up to 3 hops, HTTPS only).

## Testing

Use the **Send Test** button on the [Notification Settings]({ROUTE:user.notification-settings.index}) page to send a `ping` event to your configured URL. This uses your real webhook secret for signing, so you can verify your signature validation logic with a live request.
