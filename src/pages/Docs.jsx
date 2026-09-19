import { createSignal, Show, For, onMount } from 'solid-js';
import { api } from '../api/client';
import { toast } from '../components/Toast';

const mainTabs = [
  { id: 'configuration', label: 'Configuration', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
  { id: 'troubleshooting', label: 'Troubleshooting', icon: 'M18.364 5.636a9 9 0 010 12.728m0 0l-2.829-2.829m2.829 2.829L21 21M15.536 8.464a5 5 0 010 7.072m0 0l-2.829-2.829m-4.243 2.829a4.978 4.978 0 01-1.414-2.83m-1.414 5.658a9 9 0 01-2.167-9.238m7.824 2.167a1 1 0 111.414 1.414m-1.414-1.414L3 3m8.293 8.293l1.414 1.414' },
  { id: 'debug', label: 'Debug', icon: 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const providerTabs = [
  { id: 'basic', label: 'Basic', icon: '⚙️' },
  { id: 'ses', label: 'Amazon SES', icon: '🔶' },
  { id: 'mailgun', label: 'Mailgun', icon: '🔫' },
  { id: 'sendgrid', label: 'SendGrid', icon: '📨' },
  { id: 'brevo', label: 'Brevo', icon: '💙' },
  { id: 'postmark', label: 'Postmark', icon: '📮' },
  { id: 'cloudflare', label: 'Cloudflare', icon: '🟠' },
  { id: 'smtp', label: 'Generic SMTP', icon: '📬' },
];

const providerDocs = {
  basic: {
    title: 'Basic Configuration',
    steps: [
      'Go to Provider tab and select your email provider',
      'Enter your API credentials (see provider-specific instructions)',
      'Set your From Email and From Name in Sender Details',
      'Optionally enable "Force From Address" to override all outgoing emails',
      'Click "Save Settings" and test your connection',
      'Configure Queue Runner in Advanced settings (external cron recommended)',
    ],
    tips: [
      'Use a verified domain email address as your From Email',
      'Default Queue Runner is WP-Cron, but external cron is recommended for reliability',
      'Test your configuration by sending a test email before going live',
    ],
  },
  ses: {
    title: 'Amazon SES Setup',
    steps: [
      'Log in to AWS Console and navigate to SES',
      'Verify your domain or email address in SES',
      'Go to IAM and create a new user with SES permissions',
      'Generate Access Key ID and Secret Access Key for the user',
      'Copy credentials to the plugin settings',
      'Select your SES region (must match where you verified your domain)',
    ],
    credentials: [
      { name: 'Access Key ID', desc: 'Found in IAM > Users > Security credentials' },
      { name: 'Secret Access Key', desc: 'Shown once when creating access key' },
      { name: 'Region', desc: 'AWS region where your SES is configured' },
    ],
    links: [
      { label: 'AWS SES Console', url: 'https://console.aws.amazon.com/ses/' },
      { label: 'IAM Console', url: 'https://console.aws.amazon.com/iam/' },
    ],
  },
  mailgun: {
    title: 'Mailgun Setup',
    steps: [
      'Sign up or log in at mailgun.com',
      'Add and verify your sending domain',
      'Go to API Security in your dashboard',
      'Create a new API key or use your existing one',
      'Copy the API key and domain to plugin settings',
      'Select US or EU region based on your account',
    ],
    credentials: [
      { name: 'API Key', desc: 'Found in Settings > API Security' },
      { name: 'Domain', desc: 'Your verified sending domain (e.g., mg.yourdomain.com)' },
      { name: 'Region', desc: 'US or EU based on your Mailgun account region' },
    ],
    links: [
      { label: 'Mailgun Dashboard', url: 'https://app.mailgun.com/' },
      { label: 'API Keys', url: 'https://app.mailgun.com/settings/api_security' },
    ],
  },
  sendgrid: {
    title: 'SendGrid Setup',
    steps: [
      'Sign up or log in at sendgrid.com',
      'Complete sender authentication (domain or single sender)',
      'Go to Settings > API Keys',
      'Create a new API key with "Mail Send" permissions',
      'Copy the API key to plugin settings (shown only once)',
    ],
    credentials: [
      { name: 'API Key', desc: 'Created in Settings > API Keys with Mail Send access' },
    ],
    links: [
      { label: 'SendGrid Dashboard', url: 'https://app.sendgrid.com/' },
      { label: 'API Keys', url: 'https://app.sendgrid.com/settings/api_keys' },
    ],
  },
  brevo: {
    title: 'Brevo (Sendinblue) Setup',
    steps: [
      'Sign up or log in at brevo.com',
      'Verify your sending domain or email',
      'Go to SMTP & API in your account settings',
      'Generate a new API key or use existing v3 key',
      'Copy the API key to plugin settings',
    ],
    credentials: [
      { name: 'API Key', desc: 'Found in SMTP & API > API Keys (use v3 key)' },
    ],
    links: [
      { label: 'Brevo Dashboard', url: 'https://app.brevo.com/' },
      { label: 'SMTP & API', url: 'https://app.brevo.com/settings/keys/api' },
    ],
  },
  postmark: {
    title: 'Postmark Setup',
    steps: [
      'Sign up or log in at postmarkapp.com',
      'Create a new server or use existing one',
      'Verify your sending domain',
      'Go to Server > API Tokens',
      'Copy the Server API Token to plugin settings',
    ],
    credentials: [
      { name: 'Server Token', desc: 'Found in Server > API Tokens' },
    ],
    links: [
      { label: 'Postmark Dashboard', url: 'https://account.postmarkapp.com/' },
    ],
  },
  cloudflare: {
    title: 'Cloudflare Email Service Setup',
    steps: [
      'Your sending domain must already use Cloudflare DNS',
      'In the Cloudflare dashboard go to Compute > Email Service > Email Sending',
      'Select Onboard Domain and pick your domain — Cloudflare adds the MX, SPF, DKIM and DMARC records on the cf-bounce subdomain',
      'Wait for DNS to propagate (usually 5-15 minutes)',
      'Go to My Profile > API Tokens and create a token with the "Email Sending: Edit" permission',
      'Copy the token and your Account ID (shown in the dashboard sidebar) to plugin settings',
    ],
    credentials: [
      { name: 'API Token', desc: 'Created under API Tokens, needs Email Sending: Edit' },
      { name: 'Account ID', desc: 'Shown in the Cloudflare dashboard sidebar' },
    ],
    tips: [
      'Before a sending domain is onboarded you can only send to verified destination addresses in your account.',
      'Cloudflare only accepts an allowlist of email headers. Headers it rejects are stripped automatically and listed on the log entry.',
      'Total message size, including attachments, must stay under 5 MiB.',
    ],
    links: [
      { label: 'Email Sending Dashboard', url: 'https://dash.cloudflare.com/?to=/:account/email-service/sending' },
      { label: 'Cloudflare API Tokens', url: 'https://dash.cloudflare.com/profile/api-tokens' },
      { label: 'Email Service Docs', url: 'https://developers.cloudflare.com/email-service/' },
    ],
  },
  smtp: {
    title: 'Generic SMTP Setup',
    steps: [
      'Obtain SMTP credentials from your email provider',
      'Enter the SMTP host (e.g., smtp.gmail.com)',
      'Enter the port (usually 587 for TLS, 465 for SSL)',
      'Enter your username (usually your email address)',
      'Enter your password or apim:specific password',
      'Select the appropriate encryption (TLS recommended)',
    ],
    credentials: [
      { name: 'Host', desc: 'SMTP server address (e.g., smtp.gmail.com)' },
      { name: 'Port', desc: '587 for TLS, 465 for SSL, 25 for none' },
      { name: 'Username', desc: 'Usually your email address' },
      { name: 'Password', desc: 'Account password or apim:specific password' },
      { name: 'Encryption', desc: 'TLS (recommended), SSL, or None' },
    ],
    tips: [
      'Gmail requires an App Password if 2FA is enabled',
      'Microsoft 365 may require OAuth or app passwords',
      'Some hosts block port 25, use 587 or 465 instead',
    ],
  },
};

const troubleshootingItems = [
  {
    title: 'Emails not sending',
    solutions: [
      'Verify your API credentials are correct',
      'Check if your sending domain/email is verified with the provider',
      'Ensure your provider account is active and not suspended',
      'Check the Logs tab for specific error messages',
    ],
  },
  {
    title: 'Connection test fails',
    solutions: [
      'Double-check API keys for typos',
      'Ensure you selected the correct region',
      'Verify your server can make outbound HTTPS requests',
      'Check if your hosting provider blocks external API calls',
    ],
  },
  {
    title: 'Emails going to spam',
    solutions: [
      'Set up SPF, DKIM, and DMARC records for your domain',
      'Ensure From email matches your verified domain',
      'Avoid spam trigger words in subject and content',
      'Warm up your sending domain gradually',
    ],
  },
  {
    title: 'Queue not processing',
    solutions: [
      'Default is WP-Cron which depends on site traffic - switch to external cron for reliability',
      'Verify queue processing is enabled in Advanced settings',
      'If using WP-Cron, check if DISABLE_WP_CRON is set in wp-config.php',
      'Check server error logs for PHP errors',
    ],
  },
  {
    title: 'Rate limiting errors',
    solutions: [
      'Reduce emails per batch in Advanced settings',
      'Increase the processing interval',
      'Check your provider\'s rate limits and quotas',
      'Consider upgrading your provider plan if needed',
    ],
  },
];

const debugInfo = [
  {
    title: 'Enable Debug Mode',
    content: 'Add the following to wp-config.php to enable detailed logging:',
    code: "define('INSANEMAILER_DEBUG', true);",
  },
  {
    title: 'Check Email Logs',
    content: 'Go to the Logs tab to view all email attempts, statuses, and error messages. Click on any email to see full details including provider responses.',
  },
  {
    title: 'Server Requirements',
    content: 'Ensure your server meets these requirements:',
    list: [
      'PHP 7.4 or higher',
      'cURL extension enabled',
      'OpenSSL extension for TLS',
      'Outbound HTTPS connections allowed',
    ],
  },
  {
    title: 'Common Error Codes',
    list: [
      '401/403: Invalid API credentials',
      '429: Rate limit exceeded',
      '500: Provider server error (retry later)',
      'Connection timeout: Check firewall/hosting restrictions',
    ],
  },
];

export default function Docs() {
  const [activeTab, setActiveTab] = createSignal('configuration');
  const [activeProvider, setActiveProvider] = createSignal('basic');
  const [cronToken, setCronToken] = createSignal(window.insaneMailerAdmin?.settings?.cron_token || '');
  const [regenerating, setRegenerating] = createSignal(false);

  const siteUrl = window.insaneMailerAdmin?.siteUrl || window.location.origin;
  const cronEndpoint = () => `${siteUrl}/wp-json/insane-mailer/v1/cron?token=${cronToken()}`;

  const handleRegenerateToken = async () => {
    setRegenerating(true);
    try {
      const response = await api.regenerateCronToken();
      setCronToken(response.data.token);
      toast.success('Cron token regenerated');
    } catch (error) {
      toast.error('Failed to regenerate token');
    } finally {
      setRegenerating(false);
    }
  };

  const handleCopyEndpoint = () => {
    navigator.clipboard.writeText(cronEndpoint());
    toast.success('Endpoint copied to clipboard');
  };

  return (
    <div>
      <h2 class="im:text-2xl im:font-semibold im:text-gray-900 im:mb-6">Documentation</h2>

      {/* Main Tabs */}
      <div class="im:border-b im:border-gray-200 im:mb-6">
        <div class="im:flex im:gap-1">
          <For each={mainTabs}>
            {(tab) => (
              <button
                class={`im:flex im:items-center im:gap-2 im:px-4 im:py-2.5 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${
                  activeTab() === tab.id
                    ? 'im:border-gray-900 im:text-gray-900'
                    : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'
                }`}
                onClick={() => setActiveTab(tab.id)}
              >
                <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d={tab.icon} />
                </svg>
                {tab.label}
              </button>
            )}
          </For>
        </div>
      </div>

      {/* Configuration Tab */}
      <Show when={activeTab() === 'configuration'}>
        {/* Provider Sub-tabs */}
        <div class="im:flex im:flex-wrap im:gap-2 im:mb-6">
          <For each={providerTabs}>
            {(tab) => (
              <button
                class={`im:flex im:items-center im:gap-1.5 im:px-3 im:py-1.5 im:text-sm im:rounded-md im:transition-colors ${
                  activeProvider() === tab.id
                    ? 'im:bg-gray-900 im:text-white'
                    : 'im:bg-gray-100 im:text-gray-600 hover:im:bg-gray-200'
                }`}
                onClick={() => setActiveProvider(tab.id)}
              >
                <span class="im:text-base">{tab.icon}</span>
                {tab.label}
              </button>
            )}
          </For>
        </div>

        {/* Provider Content */}
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-6">
          <h3 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-4">
            {providerDocs[activeProvider()]?.title}
          </h3>

          {/* Steps */}
          <div class="im:mb-6">
            <h4 class="im:text-sm im:font-medium im:text-gray-700 im:mb-3">Setup Steps</h4>
            <ol class="im:space-y-2">
              <For each={providerDocs[activeProvider()]?.steps || []}>
                {(step, index) => (
                  <li class="im:flex im:gap-3 im:text-sm im:text-gray-600">
                    <span class="im:flex im:items-center im:justify-center im:w-5 im:h-5 im:rounded-full im:bg-gray-100 im:text-xs im:font-medium im:text-gray-700 im:shrink-0">
                      {index() + 1}
                    </span>
                    {step}
                  </li>
                )}
              </For>
            </ol>
          </div>

          {/* Credentials */}
          <Show when={providerDocs[activeProvider()]?.credentials}>
            <div class="im:mb-6">
              <h4 class="im:text-sm im:font-medium im:text-gray-700 im:mb-3">Required Credentials</h4>
              <div class="im:space-y-2">
                <For each={providerDocs[activeProvider()]?.credentials || []}>
                  {(cred) => (
                    <div class="im:flex im:gap-2 im:text-sm">
                      <span class="im:font-medium im:text-gray-900 im:min-w-[140px]">{cred.name}</span>
                      <span class="im:text-gray-600">{cred.desc}</span>
                    </div>
                  )}
                </For>
              </div>
            </div>
          </Show>

          {/* Tips */}
          <Show when={providerDocs[activeProvider()]?.tips}>
            <div class="im:mb-6">
              <h4 class="im:text-sm im:font-medium im:text-gray-700 im:mb-3">Tips</h4>
              <ul class="im:space-y-1.5">
                <For each={providerDocs[activeProvider()]?.tips || []}>
                  {(tip) => (
                    <li class="im:flex im:gap-2 im:text-sm im:text-gray-600">
                      <span class="im:text-green-600">•</span>
                      {tip}
                    </li>
                  )}
                </For>
              </ul>
            </div>
          </Show>

          {/* Links */}
          <Show when={providerDocs[activeProvider()]?.links}>
            <div>
              <h4 class="im:text-sm im:font-medium im:text-gray-700 im:mb-3">Useful Links</h4>
              <div class="im:flex im:flex-wrap im:gap-2">
                <For each={providerDocs[activeProvider()]?.links || []}>
                  {(link) => (
                    <a
                      href={link.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      class="im:inline-flex im:items-center im:gap-1.5 im:px-3 im:py-1.5 im:text-sm im:text-blue-600 im:bg-blue-50 im:rounded-md hover:im:bg-blue-100 im:transition-colors"
                    >
                      {link.label}
                      <svg class="im:w-3.5 im:h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                      </svg>
                    </a>
                  )}
                </For>
              </div>
            </div>
          </Show>
        </div>
      </Show>

      {/* Troubleshooting Tab */}
      <Show when={activeTab() === 'troubleshooting'}>
        <div class="im:space-y-4">
          <For each={troubleshootingItems}>
            {(item) => (
              <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
                <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-3">{item.title}</h3>
                <ul class="im:space-y-2">
                  <For each={item.solutions}>
                    {(solution) => (
                      <li class="im:flex im:gap-2 im:text-sm im:text-gray-600">
                        <svg class="im:w-4 im:h-4 im:text-gray-400 im:shrink-0 im:mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        {solution}
                      </li>
                    )}
                  </For>
                </ul>
              </div>
            )}
          </For>
        </div>
      </Show>

      {/* Debug Tab */}
      <Show when={activeTab() === 'debug'}>
        <div class="im:space-y-4">
          {/* Queue Runner Setup - Interactive */}
          <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
            <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-2">External Cron Setup</h3>
            <p class="im:text-sm im:text-gray-600 im:mb-4">
              Default is WP-Cron (triggered by site visits). For reliable queue processing, switch to External Cron in Advanced settings and use this endpoint:
            </p>
            <div class="im:bg-gray-900 im:rounded-md im:p-3 im:mb-3">
              <code class="im:text-xs im:text-gray-100 im:font-mono im:break-all">{cronEndpoint()}</code>
            </div>
            <div class="im:flex im:gap-2">
              <button
                class="im:flex im:items-center im:gap-1.5 im:px-3 im:py-1.5 im:text-sm im:font-medium im:bg-gray-100 im:text-gray-700 im:rounded-md hover:im:bg-gray-200 im:transition-colors"
                onClick={handleCopyEndpoint}
              >
                <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                </svg>
                Copy Endpoint
              </button>
              <button
                class="im:flex im:items-center im:gap-1.5 im:px-3 im:py-1.5 im:text-sm im:font-medium im:bg-amber-100 im:text-amber-700 im:rounded-md hover:im:bg-amber-200 im:transition-colors disabled:im:opacity-50"
                onClick={handleRegenerateToken}
                disabled={regenerating()}
              >
                <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {regenerating() ? 'Regenerating...' : 'Regenerate Token'}
              </button>
            </div>
            <p class="im:text-xs im:text-gray-500 im:mt-3">
              Add to server crontab: <code class="im:bg-gray-100 im:px-1 im:rounded">*/5 * * * * wget -q -O - "{cronEndpoint()}" {'>'}/dev/null 2{'>'}&1</code>
            </p>
          </div>

          <For each={debugInfo}>
            {(item) => (
              <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
                <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-2">{item.title}</h3>
                <Show when={item.content}>
                  <p class="im:text-sm im:text-gray-600 im:mb-3">{item.content}</p>
                </Show>
                <Show when={item.code}>
                  <pre class="im:bg-gray-900 im:text-gray-100 im:text-xs im:p-3 im:rounded-md im:overflow-x-auto im:font-mono">
                    {item.code}
                  </pre>
                </Show>
                <Show when={item.list}>
                  <ul class="im:space-y-1.5 im:mt-2">
                    <For each={item.list}>
                      {(listItem) => (
                        <li class="im:flex im:gap-2 im:text-sm im:text-gray-600">
                          <span class="im:text-gray-400">•</span>
                          {listItem}
                        </li>
                      )}
                    </For>
                  </ul>
                </Show>
              </div>
            )}
          </For>
        </div>
      </Show>
    </div>
  );
}
