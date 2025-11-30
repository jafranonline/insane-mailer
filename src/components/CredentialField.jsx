import { Show, For, createSignal } from 'solid-js';
import { api } from '../api/client';

export const constantNames = {
  // SES
  ses_access_key: 'IM_SES_ACCESS_KEY',
  ses_secret_key: 'IM_SES_SECRET_KEY',
  // SendGrid
  sendgrid_api_key: 'IM_SENDGRID_API_KEY',
  // Mailgun
  mailgun_api_key: 'IM_MAILGUN_API_KEY',
  // Postmark
  postmark_server_token: 'IM_POSTMARK_TOKEN',
  // Brevo
  brevo_api_key: 'IM_BREVO_API_KEY',
  // SparkPost
  sparkpost_api_key: 'IM_SPARKPOST_API_KEY',
  // Mailjet
  mailjet_api_key: 'IM_MAILJET_API_KEY',
  mailjet_secret_key: 'IM_MAILJET_SECRET_KEY',
  // Elastic Email
  elasticemail_api_key: 'IM_ELASTICEMAIL_API_KEY',
  // SMTP.com
  smtpcom_api_key: 'IM_SMTPCOM_API_KEY',
  // Netcore/Pepipost
  pepipost_api_key: 'IM_PEPIPOST_API_KEY',
  // Resend
  resend_api_key: 'IM_RESEND_API_KEY',
  // MailerSend
  mailersend_api_key: 'IM_MAILERSEND_API_KEY',
  // Mailtrap
  mailtrap_api_key: 'IM_MAILTRAP_API_KEY',
  // Loops
  loops_api_key: 'IM_LOOPS_API_KEY',
  // Mandrill
  mandrill_api_key: 'IM_MANDRILL_API_KEY',
  // SMTP2GO
  smtp2go_api_key: 'IM_SMTP2GO_API_KEY',
  // SocketLabs
  socketlabs_server_id: 'IM_SOCKETLABS_SERVER_ID',
  socketlabs_api_key: 'IM_SOCKETLABS_API_KEY',
  // ZeptoMail
  zeptomail_api_key: 'IM_ZEPTOMAIL_TOKEN',
  // Gmail
  gmail_client_id: 'IM_GMAIL_CLIENT_ID',
  gmail_client_secret: 'IM_GMAIL_CLIENT_SECRET',
  // Outlook
  outlook_client_id: 'IM_OUTLOOK_CLIENT_ID',
  outlook_client_secret: 'IM_OUTLOOK_CLIENT_SECRET',
  // Custom SMTP
  smtp_username: 'IM_SMTP_USERNAME',
  smtp_password: 'IM_SMTP_PASSWORD',
};

export function CredentialStorageToggle(props) {
  const useConfig = () => props.credentials?.use_config || false;
  const [showTooltip, setShowTooltip] = createSignal(null);

  return (
    <div class="im:inline-flex im:rounded-lg im:border im:border-gray-200 im:p-1 im:bg-gray-50">
        <button
          type="button"
          onClick={() => props.onToggle(false)}
          onMouseEnter={() => setShowTooltip('db')}
          onMouseLeave={() => setShowTooltip(null)}
          class={`im:relative im:flex im:items-center im:gap-1.5 im:px-3 im:py-1.5 im:text-sm im:font-medium im:rounded-md im:transition-all ${
            !useConfig()
              ? 'im:bg-white im:text-gray-900 im:shadow-sm'
              : 'im:text-gray-500 hover:im:text-gray-700'
          }`}
        >
          <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
          </svg>
          DB
          <Show when={showTooltip() === 'db'}>
            <div class="im:absolute im:z-50 im:bottom-full im:left-1/2 im:-translate-x-1/2 im:mb-2 im:w-48 im:p-2 im:bg-gray-900 im:text-white im:text-xs im:rounded-lg im:shadow-lg im:text-left im:font-normal">
              Store credentials in WordPress database
              <div class="im:absolute im:top-full im:left-1/2 im:-translate-x-1/2 im:border-4 im:border-transparent im:border-t-gray-900" />
            </div>
          </Show>
        </button>
        <button
          type="button"
          onClick={() => props.onToggle(true)}
          onMouseEnter={() => setShowTooltip('config')}
          onMouseLeave={() => setShowTooltip(null)}
          class={`im:relative im:flex im:items-center im:gap-1.5 im:px-3 im:py-1.5 im:text-sm im:font-medium im:rounded-md im:transition-all ${
            useConfig()
              ? 'im:bg-white im:text-gray-900 im:shadow-sm'
              : 'im:text-gray-500 hover:im:text-gray-700'
          }`}
        >
          <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
          </svg>
          Config
          <Show when={showTooltip() === 'config'}>
            <div class="im:absolute im:z-50 im:bottom-full im:left-1/2 im:-translate-x-1/2 im:mb-2 im:w-48 im:p-2 im:bg-gray-900 im:text-white im:text-xs im:rounded-lg im:shadow-lg im:text-left im:font-normal">
              Load from wp-config.php constants
              <div class="im:absolute im:top-full im:left-1/2 im:-translate-x-1/2 im:border-4 im:border-transparent im:border-t-gray-900" />
            </div>
          </Show>
        </button>
    </div>
  );
}

export function ConfigConstants(props) {
  const [checking, setChecking] = createSignal(false);
  const [constants, setConstants] = createSignal(window.insaneMailerAdmin?.constants || {});
  const isDefinedInConfig = (field) => constants()[field] || false;
  const allDefined = () => props.fields.every((f) => isDefinedInConfig(f.key));

  const checkConstants = async () => {
    setChecking(true);
    try {
      const result = await api.checkConstants();
      if (result.constants) {
        setConstants(result.constants);
        window.insaneMailerAdmin.constants = result.constants;
        props.onConstantsUpdate?.(result.constants);
      }
    } catch (error) {
      console.error('Failed to check constants:', error);
    } finally {
      setChecking(false);
    }
  };

  return (
    <div class="im:space-y-4">
      <div class="im:flex im:items-center im:gap-3">
        <Show when={allDefined()}>
          <div class="im:flex im:items-center im:gap-2 im:px-3 im:py-2 im:bg-green-50 im:border im:border-green-200 im:rounded-lg im:text-green-700">
            <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="im:text-sm">All constants defined</span>
          </div>
        </Show>
        <Show when={!allDefined()}>
          <div class="im:flex im:items-center im:gap-2 im:px-3 im:py-2 im:bg-amber-50 im:border im:border-amber-200 im:rounded-lg im:text-amber-700">
            <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span class="im:text-sm">Missing constants</span>
          </div>
          <button
            type="button"
            onClick={checkConstants}
            disabled={checking()}
            class="im:flex im:items-center im:gap-1.5 im:px-3 im:py-2 im:text-sm im:font-medium im:text-gray-700 im:bg-white im:border im:border-gray-300 im:rounded-lg hover:im:bg-gray-50 im:transition-colors disabled:im:opacity-50"
          >
            <svg class={`im:w-4 im:h-4 ${checking() ? 'im:animate-spin' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            {checking() ? 'Checking...' : 'Check'}
          </button>
        </Show>
      </div>

      <div class="im:bg-gray-900 im:rounded-lg im:overflow-hidden">
        <div class="im:flex im:items-center im:justify-between im:px-4 im:py-2 im:bg-gray-800">
          <span class="im:text-xs im:text-gray-400 im:font-medium">wp-config.php</span>
          <button
            type="button"
            class="im:text-xs im:text-gray-400 hover:im:text-white im:transition-colors"
            onClick={() => {
              const code = props.fields.map((f) => `define( '${constantNames[f.key]}', 'your-${f.key.replace('_', '-')}' );`).join('\n');
              navigator.clipboard.writeText(code);
            }}
          >
            Copy
          </button>
        </div>
        <div class="im:p-4 im:space-y-1">
          <For each={props.fields}>
            {(field) => (
              <div class="im:flex im:items-center im:gap-3">
                <code class="im:flex-1 im:text-xs im:text-gray-300 im:font-mono">
                  define( '<span class="im:text-amber-400">{constantNames[field.key]}</span>', '<span class="im:text-green-400">your-{field.key.replace('_', '-')}</span>' );
                </code>
                <Show when={isDefinedInConfig(field.key)}>
                  <svg class="im:w-4 im:h-4 im:text-green-400 im:shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </Show>
                <Show when={!isDefinedInConfig(field.key)}>
                  <svg class="im:w-4 im:h-4 im:text-amber-400 im:shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01" />
                  </svg>
                </Show>
              </div>
            )}
          </For>
        </div>
      </div>
    </div>
  );
}
