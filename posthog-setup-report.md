# PostHog post-wizard report

The wizard has completed a deep integration of PostHog analytics into FlashView. The integration covers both the PHP/Laravel backend (using `posthog/posthog-php`) and the Vue 3 frontend (using `posthog-js`). A dedicated `PostHogService` centralises all server-side capture and identify calls. PostHog is initialised once in `AppServiceProvider::boot()` and on the client-side via `resources/js/posthog.js`, which is called from `AppLayout.vue` to identify authenticated users on every page load and reset on logout.

## Events instrumented

| Event | Description | File |
|---|---|---|
| `secret_created` | A new text secret was created by a user or guest | `app/Services/SecretService.php` |
| `file_secret_created` | A new file (or combined file+message) secret was created | `app/Services/SecretService.php` |
| `secret_retrieved` | A secret message was retrieved (one-time access) | `app/Http/Controllers/Api/SecretController.php` |
| `file_secret_downloaded` | An encrypted file secret was downloaded | `app/Services/SecretService.php` |
| `secret_burned` | A secret was manually burned before retrieval | `app/Services/SecretService.php` |
| `user_registered` | A new user completed registration | `app/Http/Controllers/Auth/RegisterController.php` |
| `subscription_started` | A user started a new paid subscription | `app/Http/Controllers/PlanController.php` |
| `subscription_changed` | An existing subscriber swapped plan or billing period | `app/Http/Controllers/PlanController.php` |
| `subscription_cancelled` | A user cancelled their subscription | `app/Http/Controllers/PlanController.php` |
| `sender_identity_configured` | A user saved or updated their sender identity | `app/Http/Controllers/SenderIdentityController.php` |
| `sender_domain_verified` | A user's sender domain was verified via DNS | `app/Http/Controllers/SenderIdentityController.php` |
| `cli_token_issued` | A CLI auth code was exchanged for an API token | `app/Http/Controllers/CliAuthController.php` |
| `secret_link_generated` | Client-side: secret link displayed to the user | `resources/js/Pages/Secret/SecretCreateForm.vue` |
| `secret_decrypted` | Client-side: secret decrypted successfully in browser | `resources/js/Pages/Secret/SecretViewForm.vue` |

## New files created

- `config/posthog.php` — PostHog configuration (api_key, host, disabled)
- `app/Services/PostHogService.php` — Centralised capture/identify service
- `resources/js/posthog.js` — Client-side PostHog helpers (init, identify, reset, capture)

## Modified files

- `app/Providers/AppServiceProvider.php` — PostHog PHP SDK initialisation in `boot()`
- `app/Services/SecretService.php` — Events: `secret_created`, `file_secret_created`, `file_secret_downloaded`, `secret_burned`
- `app/Http/Controllers/Api/SecretController.php` — Event: `secret_retrieved`
- `app/Http/Controllers/Auth/RegisterController.php` — Identify user + event: `user_registered`
- `app/Http/Controllers/PlanController.php` — Events: `subscription_started`, `subscription_changed`, `subscription_cancelled`
- `app/Http/Controllers/SenderIdentityController.php` — Events: `sender_identity_configured`, `sender_domain_verified`
- `app/Http/Controllers/CliAuthController.php` — Event: `cli_token_issued`
- `resources/js/Layouts/AppLayout.vue` — PostHog init, user identify on mount, reset on logout
- `resources/js/Pages/Secret/SecretCreateForm.vue` — Event: `secret_link_generated`
- `resources/js/Pages/Secret/SecretViewForm.vue` — Event: `secret_decrypted`
- `.env.example` — Added PostHog env var placeholders

## Next steps

We've built some insights and a dashboard for you to keep an eye on user behaviour, based on the events we just instrumented:

- [Analytics basics dashboard](/dashboard/698826)
- [Secrets Created Over Time](/insights/u4GBlVlJ) — Text vs. file secrets created per day
- [Secret Retrieval and Burn Activity](/insights/AKYafBTG) — Retrieval, file download, and early burn trends
- [Registration to Subscription Funnel](/insights/eIKQAmig) — Conversion from sign-up to paid subscription
- [Secret Link Generated to Decryption Funnel](/insights/XLVsJoeC) — Share-to-decrypt conversion rate
- [Subscription Churn](/insights/lwYmB4tO) — Weekly subscription cancellations

### Agent skill

We've left an agent skill folder in your project. You can use this context for further agent development when using Claude Code. This will help ensure the model provides the most up-to-date approaches for integrating PostHog.
