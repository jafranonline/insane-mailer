=== Insane Mailer - SMTP, Email Logs & Delivery ===
Contributors: arraystory
Tags: smtp, email log, mailer, transactional email, email delivery
Requires at least: 6.2
Tested up to: 7.0
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, reliable SMTP and email delivery for WordPress. Send via 20+ providers with complete logs and bounce handling.

== Description ==

**Insane Mailer** is a lightweight WordPress SMTP and email delivery plugin built for performance. Send emails reliably through 20+ providers, with every message logged and nothing to configure beyond your provider.

Insane Mailer is under 1MB and loads its admin assets only on its own settings screen.

### Why Insane Mailer?

* **Lightweight** - Under 1MB, with admin assets loaded only on the plugin's own screen
* **Sent Immediately** - No cron, no queue table to babysit; mail leaves when WordPress asks for it
* **20+ Providers** - Amazon SES, SendGrid, Mailgun, Brevo, and more
* **Email Logs** - Track every email with delivery status and timestamps
* **Webhook Support** - Handle bounces and complaints automatically
* **Modern Dashboard** - Fast admin interface built with SolidJS
* **REST API** - Full control for developers

### Features

**Immediate Delivery**
Every email goes out through your provider the moment WordPress sends it. Nothing waits on WP-Cron, so password resets and order confirmations arrive right away.

**Email Logging**
Every email is logged with recipient, subject, status, provider and timestamp. Debug delivery issues and maintain records for compliance.

**Resend From The Log**
A message that failed can be sent again from the Logs screen, without hunting down whatever triggered it.

**Bounce Handling**
Receive webhooks from supported providers to track bounces and complaints. Keep your sender reputation clean.

### Supported Email Providers

**Transactional Email Services**
Amazon SES, SendGrid, Mailgun, Postmark, SparkPost, Brevo (Sendinblue), Mandrill, Elastic Email, SMTP2GO, MailerSend, Resend, Loops, Cloudflare Email Service

**SMTP Providers**
Generic SMTP, Gmail, Outlook, SMTP.com, SocketLabs, Mailtrap, Mailjet, ZeptoMail, Netcore

**Default**
PHP Mail (no configuration required)

== External services ==

Insane Mailer does not send your data anywhere on its own. It only contacts the single email provider you choose and configure in the plugin settings. No data is sent to any service until you select that provider and enter its credentials.

When a provider is active, the plugin contacts that provider's API (or SMTP server) in these situations:

* When an email is sent. The data transmitted is the email itself: recipient and sender addresses and names, reply-to, subject, message body (HTML and/or plain text), custom headers, and any attachments.
* When you click "Send Test Email" or "Test Connection" in the settings, a sample request is sent to verify your credentials.

For Gmail and Outlook, the plugin also exchanges OAuth tokens with Google / Microsoft sign-in endpoints to authorize sending. Incoming webhooks (for bounce and complaint tracking) are received from the provider only if you configure them in your provider account.

The provider you choose, and its Terms and Privacy Policy, apply to that data:

* Amazon SES (api endpoint: email.[region].amazonaws.com) - Terms: https://aws.amazon.com/service-terms/ - Privacy: https://aws.amazon.com/privacy/
* SendGrid (api.sendgrid.com) - Terms: https://www.twilio.com/en-us/legal/tos - Privacy: https://www.twilio.com/en-us/legal/privacy
* Mailgun (api.mailgun.net) - Terms: https://www.mailgun.com/legal/terms/ - Privacy: https://www.mailgun.com/legal/privacy-policy/
* Postmark (api.postmarkapp.com) - Terms: https://postmarkapp.com/terms-of-service - Privacy: https://postmarkapp.com/privacy-policy
* SparkPost (api.sparkpost.com) - Terms: https://www.sparkpost.com/policies/tou/ - Privacy: https://www.sparkpost.com/policies/privacy/
* Brevo (api.brevo.com) - Terms: https://www.brevo.com/legal/termsofuse/ - Privacy: https://www.brevo.com/legal/privacypolicy/
* Mandrill (mandrillapp.com) - Terms: https://mailchimp.com/legal/terms/ - Privacy: https://mailchimp.com/legal/privacy/
* Elastic Email (api.elasticemail.com) - Terms: https://elasticemail.com/resources/usage-policies/terms-of-use - Privacy: https://elasticemail.com/resources/usage-policies/privacy-policy
* SMTP2GO (api.smtp2go.com) - Terms: https://www.smtp2go.com/terms-and-conditions/ - Privacy: https://www.smtp2go.com/privacy-policy/
* MailerSend (api.mailersend.com) - Terms: https://www.mailersend.com/legal/terms-of-service - Privacy: https://www.mailersend.com/legal/privacy-policy
* Resend (api.resend.com) - Terms: https://resend.com/legal/terms-of-service - Privacy: https://resend.com/legal/privacy-policy
* Loops (app.loops.so) - Terms: https://loops.so/terms - Privacy: https://loops.so/privacy
* Cloudflare Email Service (api.cloudflare.com) - Terms: https://www.cloudflare.com/terms/ - Privacy: https://www.cloudflare.com/privacypolicy/
* SMTP.com (api.smtp.com) - Terms: https://www.smtp.com/policies/terms-conditions/ - Privacy: https://www.smtp.com/policies/privacy-policy/
* SocketLabs (injection.socketlabs.com) - Terms: https://www.socketlabs.com/legal/terms-of-use/ - Privacy: https://www.socketlabs.com/legal/privacy-policy/
* Mailtrap (send.api.mailtrap.io) - Terms: https://mailtrap.io/terms-and-conditions/ - Privacy: https://mailtrap.io/privacy-policy/
* Mailjet (api.mailjet.com) - Terms: https://www.mailjet.com/legal/terms/ - Privacy: https://www.mailjet.com/legal/privacy-policy/
* ZeptoMail (api.zeptomail.com) - Terms: https://www.zoho.com/zeptomail/terms.html - Privacy: https://www.zoho.com/privacy.html
* Netcore (emailapi.netcoresmartech.com) - Terms: https://netcorecloud.com/terms-conditions/ - Privacy: https://netcorecloud.com/privacy-policy/
* Gmail / Google Workspace (gmail.googleapis.com, oauth2.googleapis.com) - Terms: https://policies.google.com/terms - Privacy: https://policies.google.com/privacy
* Outlook / Microsoft 365 (graph.microsoft.com, login.microsoftonline.com) - Terms: https://www.microsoft.com/servicesagreement/ - Privacy: https://privacy.microsoft.com/privacystatement

Generic SMTP and the default PHP Mail option send through the SMTP host you configure (or your own server) and do not contact any third-party service operated by us.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/insane-mailer` or install through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen.
3. Navigate to Settings > Insane Mailer.
4. Select your email provider and enter credentials.
5. Send a test email to verify configuration.

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =

Yes. Insane Mailer integrates with wp_mail() so it works with WooCommerce, Contact Form 7, Gravity Forms, and any plugin that sends email through WordPress.

= What happens if a send fails? =

The email is still logged, with the provider's error message and response. You can read the reason on the Logs screen and resend the message from there once the problem is fixed.

= How do I fix emails going to spam? =

Use a dedicated email provider like Amazon SES, SendGrid, or Mailgun. Configure SPF, DKIM, and DMARC records for your domain. Insane Mailer supports all major transactional email services.

= How do I set up Gmail SMTP in WordPress? =

Select Gmail as your provider in Settings > Insane Mailer, create OAuth credentials in your Google account, and paste the client ID and secret. Insane Mailer handles the token exchange so your WordPress emails send from your Gmail or Google Workspace address.

= How do I use Amazon SES with WordPress? =

Choose Amazon SES as your provider, enter your SES access key and secret, and select your region. Insane Mailer signs each request for you, so transactional emails go out through SES with high deliverability.

= How do I send reliable WooCommerce emails? =

Insane Mailer routes every WooCommerce email (orders, password resets, notifications) through your chosen SMTP or API provider instead of the default PHP mailer, and sends each one immediately so order confirmations are not waiting on a cron run.

= Can I see which emails were sent? =

Yes. The Logs screen shows every email with recipient, subject, status, provider response, and timestamp.

= Does this plugin support multisite? =

Yes. Activate network-wide or per-site. Each site can have its own provider configuration.

= How do I migrate from another SMTP plugin? =

Go to Settings > Insane Mailer > Advanced and use the migration tool. We support migration from popular SMTP plugins.

= Is my API key secure? =

Yes. API keys are stored in your WordPress database and never exposed in the admin interface after saving.

== Screenshots ==

1. See delivery rates, bounces, and email volume at a glance on the dashboard.
2. Connect any of 20+ providers in a few clicks, with credentials kept out of the UI.
3. Read the setup guide for your provider without leaving the dashboard.
4. Search and filter a complete log of every email, with status and provider response.
5. Send a test email and confirm your setup works before going live.

== Changelog ==

= 1.1.0 =
* Removed queue mode - every email is now sent immediately through your provider
* Removed the send mode, queue runner, external cron, batch, rate limit and retry settings
* Emails left in the queue at upgrade are given one delivery attempt, then logged as sent or failed
* Retry on the Logs screen is now Resend, which sends the message again straight away
* Fixed Custom SMTP credentials being ignored, so mail went out through the server's default transport
* Fixed Pause Sending being ignored outside queue mode
* Fixed the provider column never being recorded, so per-provider stats were always empty

= 1.0.0 =
* Initial release
* 20+ email provider integrations
* Queue-based email sending
* Email logging with status tracking
* Webhook support for bounces
* Modern admin dashboard
* REST API for all operations
* Migration tools for other SMTP plugins

== Upgrade Notice ==

= 1.1.0 =
Queue mode is removed; all email is sent immediately. Queue settings are dropped and anything still queued gets one final delivery attempt after the upgrade.

= 1.0.0 =
Initial release of Insane Mailer.

== Privacy Policy ==

Insane Mailer stores email logs in your WordPress database including recipient addresses, subjects, and delivery status. No data is sent to external servers except to your configured email provider for delivery. See the "External services" section above for the provider endpoints contacted and links to their terms and privacy policies.

You can set automatic log cleanup in Settings > Insane Mailer > Settings.

For GDPR compliance, email logs can be exported or deleted through the WordPress privacy tools.
