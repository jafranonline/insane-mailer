# Easy SMTP Queue

Lightweight WordPress SMTP plugin with queue-based email sending, supporting multiple providers via REST API.

## Features

- Queue-based or direct email sending
- Multiple email providers (SES, Mailgun, SendGrid, Brevo, SparkPost, Postmark, etc.)
- Rate limiting and retry mechanism
- Priority emails (bypass queue)
- Webhook support for bounces/complaints
- Admin dashboard built with SolidJS
- REST API for all operations

## Development

### Building Admin Assets

```bash
npm install
npm run build
```

For development with auto-rebuild:

```bash
npm run build
```

This will compile the SolidJS admin app to `assets/dist/`.

## File Structure

```
easy-smtp-queue/
├── src/                    # SolidJS admin app
│   ├── pages/
│   ├── components/
│   ├── api/
│   └── index.jsx
├── includes/               # PHP classes
│   ├── Providers/
│   ├── Rest/
│   └── Helpers/
├── assets/dist/           # Compiled JS/CSS
└── easy-smtp-queue.php    # Main plugin file
```

## REST API Endpoints

- `GET/POST /esq/v1/settings` - Manage settings
- `POST /esq/v1/settings/test-connection` - Test provider connection
- `POST /esq/v1/test-email` - Send test email
- `GET /esq/v1/emails` - List emails
- `POST /esq/v1/emails/{id}/retry` - Retry failed email
- `DELETE /esq/v1/emails/{id}` - Delete email
- `GET /esq/v1/stats` - Get dashboard stats
- `POST /esq/v1/queue/process` - Process queue manually
- `POST /esq/v1/webhook/{provider}` - Provider webhooks

## Supported Providers

- Generic SMTP
- Amazon SES
- Mailgun
- SendGrid
- Brevo (Sendinblue)
- SparkPost
- Postmark
- Elastic Email
- SMTP.com
- Netcore
