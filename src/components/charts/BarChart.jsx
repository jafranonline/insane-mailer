import { For, Show, createSignal } from 'solid-js';

const providerLabels = {
  default: 'PHP Mail',
  ses: 'Amazon SES',
  sendgrid: 'SendGrid',
  mailgun: 'Mailgun',
  postmark: 'Postmark',
  brevo: 'Brevo',
  sparkpost: 'SparkPost',
  mailjet: 'Mailjet',
  elasticemail: 'Elastic Email',
  smtpcom: 'SMTP.com',
  pepipost: 'Netcore',
  resend: 'Resend',
  mailersend: 'MailerSend',
  mailtrap: 'Mailtrap',
  loops: 'Loops',
  cloudflare: 'Cloudflare',
  mandrill: 'Mandrill',
  smtp2go: 'SMTP2GO',
  socketlabs: 'SocketLabs',
  zeptomail: 'ZeptoMail',
  gmail: 'Gmail',
  outlook: 'Outlook',
  smtp: 'SMTP',
};

export default function BarChart({ data }) {
  const [hovered, setHovered] = createSignal(null);

  const getProviderLabel = (provider) => {
    return providerLabels[provider] || provider;
  };

  const getBarColor = (rate) => {
    if (rate >= 95) return '#10b981';
    if (rate >= 85) return '#f59e0b';
    return '#ef4444';
  };

  const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toLocaleString();
  };

  return (
    <Show when={data && data.length > 0} fallback={
      <div class="im:text-center im:py-8 im:text-gray-500 im:text-sm">
        No provider data available
      </div>
    }>
      <div class="im:space-y-3">
        <For each={data}>
          {(provider) => (
            <div
              class="im:group"
              onMouseEnter={() => setHovered(provider.provider)}
              onMouseLeave={() => setHovered(null)}
            >
              <div class="im:flex im:items-center im:justify-between im:mb-1">
                <span class="im:text-sm im:font-medium im:text-gray-700">
                  {getProviderLabel(provider.provider)}
                </span>
                <div class="im:flex im:items-center im:gap-2">
                  <span class="im:text-xs im:text-gray-500">
                    {formatNumber(provider.sent)} sent
                  </span>
                  <span
                    class="im:text-sm im:font-semibold"
                    style={{ color: getBarColor(provider.success_rate) }}
                  >
                    {provider.success_rate}%
                  </span>
                </div>
              </div>
              <div class="im:relative im:h-2.5 im:bg-gray-100 im:rounded-full im:overflow-hidden">
                <div
                  class="im:absolute im:inset-y-0 im:left-0 im:rounded-full im:transition-all"
                  style={{
                    width: `${provider.success_rate}%`,
                    background: getBarColor(provider.success_rate),
                    opacity: hovered() === provider.provider ? 1 : 0.85,
                  }}
                />
              </div>

              {/* Expanded details on hover */}
              <Show when={hovered() === provider.provider}>
                <div class="im:mt-2 im:flex im:gap-4 im:text-xs im:text-gray-500">
                  <span>
                    <span class="im:text-green-600 im:font-medium">{formatNumber(provider.sent)}</span> delivered
                  </span>
                  <span>
                    <span class="im:text-red-600 im:font-medium">{formatNumber(provider.failed)}</span> failed
                  </span>
                  <Show when={provider.bounced > 0}>
                    <span>
                      <span class="im:text-amber-600 im:font-medium">{formatNumber(provider.bounced)}</span> bounced
                    </span>
                  </Show>
                </div>
              </Show>
            </div>
          )}
        </For>
      </div>
    </Show>
  );
}
