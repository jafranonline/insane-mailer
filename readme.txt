=== Insane Mailer - SMTP, Email Logs & Queue ===
Contributors: arraystory
Tags: smtp, email, mail, mailer, email log
Requires at least: 6.2
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The SMTP plugin you'll never replace. Blazing fast, queue-powered, zero bloat.

== Description ==

**Insane Mailer** is a lightweight WordPress SMTP and email delivery plugin built for performance. Send emails reliably through 20+ providers with queue-based sending, automatic retries, and complete email logs.

Insane Mailer is under 1MB, loads only when needed, and never slows down your site.

### Why Insane Mailer?

* **Lightweight** - Under 1MB, zero bloat, no unnecessary features
* **Queue System** - Emails sent in batches via WP-Cron for better deliverability
* **20+ Providers** - Amazon SES, SendGrid, Mailgun, Brevo, and more
* **Email Logs** - Track every email with delivery status and timestamps
* **Webhook Support** - Handle bounces and complaints automatically
* **Modern Dashboard** - Fast admin interface built with SolidJS
* **REST API** - Full control for developers

### Features

**Email Queue**
Store emails in the database and send them in controlled batches. Prevents server overload, improves deliverability, and handles failures gracefully with automatic retries.

**Direct Mode**
Bypass the queue for time-sensitive emails like password resets and order confirmations. Priority emails go out immediately.

**Email Logging**
Every email is logged with recipient, subject, status, and timestamp. Debug delivery issues and maintain records for compliance.

**Rate Limiting**
Respect provider limits automatically. Set custom send rates to match your email provider's requirements.

**Bounce Handling**
Receive webhooks from supported providers to track bounces and complaints. Keep your sender reputation clean.

### Supported Email Providers

**Transactional Email Services**
Amazon SES, SendGrid, Mailgun, Postmark, SparkPost, Brevo (Sendinblue), Mandrill, Elastic Email, SMTP2GO, MailerSend, Resend, Loops

**SMTP Providers**
Generic SMTP, Gmail, Outlook, SMTP.com, SocketLabs, Mailtrap, Mailjet, ZeptoMail, Netcore

**Default**
PHP Mail (no configuration required)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/insane-mailer` or install through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen.
3. Navigate to Settings > Insane Mailer.
4. Select your email provider and enter credentials.
5. Send a test email to verify configuration.

== Frequently Asked Questions ==

= Does this plugin work with WooCommerce? =

Yes. Insane Mailer integrates with wp_mail() so it works with WooCommerce, Contact Form 7, Gravity Forms, and any plugin that sends email through WordPress.

= Will emails be lost if my site goes down? =

No. Emails are stored in your database before sending. If your site restarts or a send fails, the queue processes them on the next cron run.

= How do I fix emails going to spam? =

Use a dedicated email provider like Amazon SES, SendGrid, or Mailgun. Configure SPF, DKIM, and DMARC records for your domain. Insane Mailer supports all major transactional email services.

= What is the difference between queue and direct mode? =

Queue mode batches emails and sends them via WP-Cron, improving deliverability and server performance. Direct mode sends immediately, ideal for urgent emails like password resets.

= Can I see which emails were sent? =

Yes. The Logs screen shows every email with recipient, subject, status, provider response, and timestamp.

= Does this plugin support multisite? =

Yes. Activate network-wide or per-site. Each site can have its own provider configuration.

= How do I migrate from another SMTP plugin? =

Go to Settings > Insane Mailer > Advanced and use the migration tool. We support migration from popular SMTP plugins.

= Is my API key secure? =

Yes. API keys are stored in your WordPress database and never exposed in the admin interface after saving.

== Screenshots ==

1. Dashboard overview with email statistics
2. Provider configuration screen
3. Email queue management
4. Email logs with search and filters
5. Test email interface

== Changelog ==

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

= 1.0.0 =
Initial release of Insane Mailer.

== Privacy Policy ==

Insane Mailer stores email logs in your WordPress database including recipient addresses, subjects, and delivery status. No data is sent to external servers except to your configured email provider for delivery.

You can disable logging or set automatic log cleanup in Settings > Insane Mailer > Advanced.

For GDPR compliance, email logs can be exported or deleted through the WordPress privacy tools.
