# Insane Mailer

WordPress plugin: SMTP and API email delivery with logs and bounce handling. Every
message is sent immediately — there is no queue and no send-mode setting. PHP 7.4+,
WordPress 6.2+, SolidJS admin UI built with Vite.

## Working agreement

- **Stay on the current branch.** Do not create or switch branches.
- **Commit after every completed unit of work** — each bug fixed, each feature
  added, each task finished. Do not batch unrelated changes into one commit.
- **Update the docs in the same commit** as the change they describe (see
  [Documentation surfaces](#documentation-surfaces)). A feature is not done
  until its docs are current.

## Layout

```
insane-mailer.php        Bootstrap: constants, activation hooks, plugins_loaded init
includes/
  Mailer.php             wp_mail interception, provider dispatch, email logging
  Cleanup.php            Daily log retention and attachment GC
  QueueDrain.php         One-time drain of emails left over from queue mode
  Admin.php              Admin page, script localisation, REST controller loading
  Activator.php          Table schema, default settings, legacy im_ -> insanemailer_
  Providers/             One class per email provider
  Rest/                  REST controllers, namespace insane-mailer/v1
  Migration/             Importers for other SMTP plugins
  Helpers/               Attachment, Logger, RateLimiter
src/                     SolidJS admin UI (built into assets/, which is gitignored)
readme.txt               WordPress.org readme
```

## Conventions

- **No autoloader and no namespaces.** Classes are global with an
  `INSANEMAILER_` prefix and are `require_once`d explicitly.
- **Provider class names map to filenames.** `get_provider_class()` derives the
  path by stripping `INSANEMAILER_Provider_` from the class name, so
  `INSANEMAILER_Provider_Foo` must live in `includes/Providers/Foo.php`.
  Case-sensitive on Linux.
- **Tabs for indentation in PHP**, two spaces in JSX.
- **Tailwind classes are prefixed `im:`** to avoid colliding with wp-admin.
- `phpcs.xml` defines the standard. Run it before committing:
  ```sh
  phpcs --standard=phpcs.xml includes/
  ```

### Credential keys

Every credential has two spellings, and providers must accept both:

| Source | Spelling | Example |
| --- | --- | --- |
| Admin form (saved to the DB) | generic | `api_key`, `client_secret` |
| `wp-config.php` constants | prefixed | `resend_api_key`, `gmail_client_secret` |

Read them with `get_credential_any( [ 'api_key', 'foo_api_key' ] )` — generic
first, prefixed as fallback. Using `get_credential()` with only the prefixed key
breaks the admin form; that was a real bug across 11 providers.

## Adding a provider

There is no registry — the provider list is hand-duplicated across many files.
Missing one fails a different code path each time, so work through all of them.

**PHP**

1. `includes/Providers/<Name>.php` extending `INSANEMAILER_Abstract_Provider`.
   Implement `get_name()`, `send_raw( $mail_data )`, `test_connection()`.
2. Slug to class map in **both**: `Mailer.php` (sending) and
   `Rest/SettingsController.php` (connection test). Use the same slug in each.
3. Display name in `SettingsController::$provider_names`.
4. Constant map in **all three** copies: `Admin.php`,
   `SettingsController::get_credentials_with_constants()`, and
   `SettingsController::check_constants()`.
5. Any secret credential key added to the masking lists in
   `SettingsController` and `Admin.php`, or it is returned to the browser in
   plaintext.

**UI** (`src/`) — logo import and `providerLogos` in both `pages/Provider.jsx`
and `pages/Setup.jsx`; the `providers` array in both; `providerFields` in both;
a `<Match>` credential block in `Provider.jsx` (no catch-all exists there, so
omitting it renders an empty form); `constantNames` in
`components/CredentialField.jsx`; `providerLabels` in
`components/charts/BarChart.jsx`, `components/MigrationSection.jsx` and
`pages/Overview.jsx`; a `Docs.jsx` entry. `Setup.jsx` has a single-`api_key`
catch-all — a multi-field provider needs its own `<Match>` *and* its slug added
to that catch-all's exclusion array.

**Docs** — `readme.txt` provider list and the External services section.

### The send contract

`send_raw( $mail_data )` receives:

```php
[
  'to'          => [ 'email' => ..., 'name' => ... ],  // always a single recipient
  'from'        => [ 'email' => ..., 'name' => ... ],
  'reply_to'    => '<string>',
  'subject'     => '<string>',
  'body_html'   => '<string>',
  'body_plain'  => '<string>',
  'headers'     => [ 'Name' => 'value' ],
  'attachments' => [ '/abs/path', ... ],
]
```

Return `[ 'success' => true, 'message_id' => ..., 'response' => ... ]`, or
`[ 'success' => false, 'error' => '<message>' ]`. An optional `warnings` array
of strings is persisted to `provider_response` and surfaced on the log entry.
`$result['success']` is read without an isset guard, so always return an array
containing that key.

There is no cc/bcc support and no per-provider capability metadata.

## Send path

There is one path. `Mailer::handle_mail()` on `pre_wp_mail` logs the message with
`status = 'sending'`, then:

- `pause_sending` on → the row becomes `paused`, nothing is delivered, `true` is
  returned so callers still see a success.
- provider `default` → returns null and PHPMailer runs; the row is closed out by
  the `wp_mail_succeeded` / `wp_mail_failed` listeners.
- every other provider, **`smtp` included** → `send_raw()` over the provider class,
  which short-circuits `wp_mail()`. Letting it continue would attempt a second
  delivery and report a false failure for a message already accepted.

`Mailer::send_logged_email( $row )` re-sends a row that is already in the log; it
backs both the resend endpoint and `QueueDrain`. For `default` it calls `wp_mail()`
with our own `pre_wp_mail` filter detached, so the message is not logged twice.

Statuses: `sending`, `sent`, `failed`, `paused`, `bounced`, `complained`.

The plugin does not hook `phpmailer_init` at all. The configured sender is applied
via `wp_mail_from` / `wp_mail_from_name`, because WordPress calls `setFrom()` before
`phpmailer_init` fires, and its `wordpress@<host>` default is rejected outright on a
host without a dot, which fails the send before any provider is reached.

## Admin screens

Top-level tabs are Overview, Settings, Logs and Docs, routed off the URL hash by
`src/index.jsx`, which also listens for `hashchange` so browser back/forward
navigation between tabs works.
`Settings` is `pages/Settings.jsx`, whose sub-tabs are **Sender** (the default,
rendering `pages/Provider.jsx`), Preferences and Import / Export, addressed as
`#settings/<sub-tab>`. `#provider` and the old `#advanced[/<sub-tab>]` links
(with the old `provider`/`general`/`tools` sub-tab slugs) are redirected to their
`#settings/...` equivalents for links predating the rename.

`Settings.jsx` and `Provider.jsx` both write the whole `insanemailer_settings`
option, and `Settings.jsx` stays mounted while you switch sub-tabs, so its snapshot
goes stale as soon as Sender (Provider.jsx) saves. Its save therefore copies only
the keys in its `preferencesKeys` list onto the current settings. **Add a key to
that list when you add a field to the Preferences tab**, or the field will not
save. `Provider.jsx` is unmounted and remounted by the `<Show>` on each switch, so
it re-reads fresh.

A compact connection-status indicator sits in the header (`src/index.jsx`) on
every tab, fetched once via `api.getConnectionStatus()`. It shows a "Sender not
set" flag whenever `missingSender()` is true, taking priority over the raw
connection state.

A missing or invalid `from_email`, or a missing `from_name`, raises a warning banner
across the admin screens and a "Not set" flag on the Overview card and the Sender
Details panel. Without a valid sender WordPress falls back to `wordpress@<host>`,
which many providers reject.

## Webhooks

`POST /insane-mailer/v1/webhook/<provider>` is public by necessity but every
request is authenticated in `Rest/WebhookController.php` before anything is read
or written: SES by verifying the SNS message signature against the certificate
on `sns.<region>.amazonaws.com`; Mailgun (HMAC signing key), SendGrid (ECDSA
verification key), Postmark (Basic Auth password) and SparkPost (Basic Auth or
Bearer token) against `settings['webhook_secrets'][<provider>]`. A provider
with no secret configured is rejected with 401. Secrets are entered on the
Sender tab's "Bounce & Complaint Webhook" card (`webhookConfig` in
`Provider.jsx`) and are masked like credentials by `INSANEMAILER_Settings`.

## Settings option

`INSANEMAILER_Settings` (`includes/Settings.php`) is the only place that knows
which keys the `insanemailer_settings` option accepts, how they are sanitized
and which credential keys are secrets. Every writer (REST update, import,
migration) calls `sanitize()` + `restore_masked()`; every reader that returns
the option to the browser calls `mask()`. Add new keys there, not in the
controllers.

## Documentation surfaces

| File | Audience | Update when |
| --- | --- | --- |
| `readme.txt` | WordPress.org listing | Features, provider list, FAQ. **Every outbound endpoint must be disclosed** in External services with terms and privacy links — this is a review requirement. |
| `src/pages/Docs.jsx` | In-app setup guide | A provider needs setup steps, or a flow changes |
| `CLAUDE.md` | This file | Conventions or architecture change |

## Local development

```sh
npx vite build          # one-shot build; `npm run build` is vite --watch and never exits
```

`assets/` is gitignored but *is* shipped, so build it before packaging with
`pak.yml`. `pak.yml` excludes dev files (`src/`, `phpcs.xml`, `CLAUDE.md`) —
add anything new that should not reach users.

The site runs under Local, whose MySQL listens on a per-site socket, so plain
`wp` cannot connect. Point PHP at the socket:

```sh
cd /Users/jafran/Local\ Sites/arraystorytest/app/public
php -d mysqli.default_socket="$HOME/Library/Application Support/Local/run/rh21gVPnX/mysql/mysqld.sock" \
  "$(which wp)" eval 'echo get_option("siteurl");' --skip-themes
```

The run-directory ID changes if the site is recreated; find it with
`find "$HOME/Library/Application Support/Local/run" -name mysqld.sock`.

When testing sends, use a real inbox you control. Never send to `example.com` or
`example.org` — they have no mail servers, so the messages hard-bounce and count
against the sending domain's reputation.

Settings live in the `insanemailer_settings` option; logs in
`{prefix}insanemailer_emails`. Upgrades run through `INSANEMAILER_Activator::activate()`,
which `insanemailer_maybe_upgrade()` fires whenever `INSANEMAILER_VERSION` is ahead of
the stored `insanemailer_db_version` — that is the hook for any schema or settings
migration. `INSANEMAILER_Mailer` caches settings in its
constructor, so changing the option mid-request does not affect the current
send — use a fresh process when testing different providers.

## Known issues

- Netcore is registered as `netcore` in `Mailer.php` but `pepipost` in
  `SettingsController.php`, and the UI ships `pepipost`. It passes the connection
  test, then fails at send time.
- `Provider.jsx` and `Setup.jsx` key Mailtrap's logo and constants as
  `mailtraim`, so it renders no logo and no wp-config constants.
- `includes/Helpers/Attachment.php` is never required by anything.
