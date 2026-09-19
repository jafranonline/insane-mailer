import { createSignal, For, Show, Switch, Match, onMount } from 'solid-js';
import { api } from '../api/client';
import { CredentialStorageToggle, ConfigConstants } from '../components/CredentialField';
import SearchableSelect from '../components/SearchableSelect';
import PasswordInput from '../components/PasswordInput';
import { toast } from '../components/Toast';
import { useSettings, updateSettings as updateGlobalSettings } from '../store/settings';

import awsLogo from '../logos/aws.svg?raw';
import sendgridLogo from '../logos/sendgrid.svg?raw';
import mailgunLogo from '../logos/mailgun.svg?raw';
import brevoLogo from '../logos/brevo.svg?raw';
import sparkpostLogo from '../logos/sparkpost.svg?raw';
import mailjetLogo from '../logos/mailjet.svg?raw';
import elasticEmailLogo from '../logos/elastic-email.svg?raw';
import netcoreLogo from '../logos/netcore.svg?raw';
import resendLogo from '../logos/resend.svg?raw';
import mailtrapLogo from '../logos/mailtrap.svg?raw';
import loopsLogo from '../logos/loops.svg?raw';
import smtp2goLogo from '../logos/smtp2go.svg?raw';
import socketLabsLogo from '../logos/socket-labs.svg?raw';
import zohoLogo from '../logos/zohomail.svg?raw';
import gmailLogo from '../logos/gmail.svg?raw';
import outlookLogo from '../logos/outlook.svg?raw';
import mandrillLogo from '../logos/mandril.svg?raw';
import postmarkLogo from '../logos/postmark.svg?raw';
import mailersendLogo from '../logos/mailsend.svg?raw';
import smtpcomLogo from '../logos/smtpcom.svg?raw';
import cloudflareLogo from '../logos/cloudflare.svg?raw';
import phpLogo from '../logos/php-svgrepo-com.svg?raw';

const providerLogos = {
  default: phpLogo,
  ses: awsLogo,
  sendgrid: sendgridLogo,
  mailgun: mailgunLogo,
  postmark: postmarkLogo,
  brevo: brevoLogo,
  sparkpost: sparkpostLogo,
  mailjet: mailjetLogo,
  elasticemail: elasticEmailLogo,
  pepipost: netcoreLogo,
  resend: resendLogo,
  mailtraim: mailtrapLogo,
  loops: loopsLogo,
  mandrill: mandrillLogo,
  smtp2go: smtp2goLogo,
  mailersend: mailersendLogo,
  smtpcom: smtpcomLogo,
  socketlabs: socketLabsLogo,
  zeptomail: zohoLogo,
  gmail: gmailLogo,
  outlook: outlookLogo,
  cloudflare: cloudflareLogo,
};

const ProviderIcon = ({ id }) => {
  const logo = providerLogos[id];
  const iconClass = "im:w-[90%] im:h-[70%] im:flex im:items-center im:justify-center im:[&>svg]:max-w-full im:[&>svg]:max-h-full";
  const largeIconClass = "im:w-full im:h-full im:flex im:items-center im:justify-center im:[&>svg]:max-w-full im:[&>svg]:max-h-full im:[&>svg]:w-full";
  const smallIconClass = "im:w-[70%] im:h-[55%] im:flex im:items-center im:justify-center im:[&>svg]:max-w-full im:[&>svg]:max-h-full";


  if (id === 'smtp') {
    return (
      <div class="im:flex im:flex-col im:items-center im:justify-center im:h-full im:gap-1">
        <svg viewBox="0 0 24 24" fill="none" class="im:w-10 im:h-10">
          <rect x="2" y="4" width="20" height="16" rx="2" stroke="#6366f1" stroke-width="1.5" fill="none"/>
          <path d="M2 7l10 5 10-5" stroke="#6366f1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="im:text-[10px] im:font-medium im:text-gray-600">Other SMTP</span>
      </div>
    );
  }

  if (logo) {
    const useLargeClass = id === 'postmark' || id === 'outlook';
    const useSmallClass = id === 'zeptomail' || id === 'gmail' || id === 'ses' || id === 'brevo';
    const classToUse = useLargeClass ? largeIconClass : (useSmallClass ? smallIconClass : iconClass);
    return <div class={classToUse} innerHTML={logo} />;
  }

  const fallbackIcons = {
    postmark: (
      <svg viewBox="0 0 24 24" class={iconClass}>
        <rect x="2" y="4" width="20" height="16" rx="2" fill="#FFDE00"/>
        <path d="M6 9l6 4 6-4" stroke="#1E1E1E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    ),
    smtpcom: (
      <svg viewBox="0 0 24 24" class={iconClass}>
        <rect x="2" y="4" width="20" height="16" rx="2" fill="#0066CC"/>
        <path d="M2 7l10 5 10-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <circle cx="12" cy="15" r="2" fill="white"/>
      </svg>
    ),
    mailersend: (
      <svg viewBox="0 0 24 24" class={iconClass}>
        <rect x="2" y="4" width="20" height="16" rx="2" fill="#0ea5e9"/>
        <path d="M6 9l6 4 6-4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    ),
    pepipost: (
      <svg viewBox="0 0 24 24" class={iconClass}>
        <rect x="2" y="2" width="20" height="20" rx="4" fill="#FC5E02"/>
        <path d="M8 7v10M8 7h5a3 3 0 010 6H8" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    ),
    smtp2go: (
      <svg viewBox="0 0 24 24" class={iconClass}>
        <rect x="2" y="4" width="20" height="16" rx="2" fill="#ABD3FF"/>
        <path d="M7 9h4l-3 6h4M15 9v6M15 9h2a2 2 0 110 4h-2" stroke="#231F20" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    ),
    smtp: (
      <svg viewBox="0 0 24 24" fill="none" class={iconClass}>
        <rect x="2" y="4" width="20" height="16" rx="2" stroke="#6366f1" stroke-width="2"/>
        <path d="M2 7l10 5 10-5" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M6 14h12" stroke="#6366f1" stroke-width="2" stroke-linecap="round"/>
      </svg>
    ),
    mandrill: (
      <svg viewBox="0 0 24 24" class={iconClass}>
        <circle cx="12" cy="12" r="10" fill="#2C3E50"/>
        <path d="M8 10v4M12 8v8M16 10v4" stroke="#FFE01B" stroke-width="2" stroke-linecap="round"/>
      </svg>
    ),
    other: (
      <svg viewBox="0 0 24 24" fill="none" class={iconClass}>
        <path d="M12 15h.01M12 12v-2a3 3 0 10-3 3h3zm0 0v3" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="12" cy="12" r="10" stroke="#6B7280" stroke-width="2"/>
      </svg>
    ),
  };

  return fallbackIcons[id] || fallbackIcons.other;
};

// Providers sorted by popularity (all have REST API)
const providers = [
  // Default option
  { id: 'default', name: 'PHP Mailer', desc: 'WordPress default' },

  // Tier 1: Most popular (implemented)
  { id: 'ses', name: 'Amazon SES', desc: 'Amazon Simple Email Service' },
  { id: 'sendgrid', name: 'SendGrid', desc: 'Twilio SendGrid' },
  { id: 'mailgun', name: 'Mailgun', desc: 'Transactional email' },
  { id: 'postmark', name: 'Postmark', desc: 'Transactional email' },
  { id: 'brevo', name: 'Brevo', desc: 'Formerly Sendinblue' },

  // Tier 2: Popular (implemented)
  { id: 'sparkpost', name: 'SparkPost', desc: 'Enterprise email' },
  { id: 'mailjet', name: 'Mailjet', desc: 'Email service' },
  { id: 'elasticemail', name: 'Elastic Email', desc: 'Email delivery' },
  { id: 'smtpcom', name: 'SMTP.com', desc: 'Enterprise SMTP' },
  { id: 'pepipost', name: 'Netcore', desc: 'Pepipost email' },

  // Tier 3: Growing
  { id: 'resend', name: 'Resend', desc: 'Modern email API' },
  { id: 'mailersend', name: 'MailerSend', desc: 'By MailerLite' },
  { id: 'mailtrap', name: 'Mailtrap', desc: 'Dev & production' },
  { id: 'loops', name: 'Loops', desc: 'SaaS email' },
  { id: 'cloudflare', name: 'Cloudflare', desc: 'Cloudflare Email Service' },

  // Tier 4: Others
  { id: 'mandrill', name: 'Mandrill', desc: 'Mailchimp Transactional' },
  { id: 'smtp2go', name: 'SMTP2GO', desc: 'SMTP relay' },
  { id: 'socketlabs', name: 'SocketLabs', desc: 'Email delivery' },
  { id: 'zeptomail', name: 'ZeptoMail', desc: 'By Zoho' },
  { id: 'gmail', name: 'Gmail', desc: 'Google Workspace' },
  { id: 'outlook', name: 'Outlook', desc: 'Microsoft 365' },

  // Custom SMTP
  { id: 'smtp', name: 'Other SMTP', desc: 'Custom SMTP server' },
];

const awsRegions = [
  { value: 'us-east-1', label: 'US East (N. Virginia)' },
  { value: 'us-east-2', label: 'US East (Ohio)' },
  { value: 'us-west-1', label: 'US West (N. California)' },
  { value: 'us-west-2', label: 'US West (Oregon)' },
  { value: 'af-south-1', label: 'Africa (Cape Town)' },
  { value: 'aim:east-1', label: 'Asia Pacific (Hong Kong)' },
  { value: 'aim:south-1', label: 'Asia Pacific (Mumbai)' },
  { value: 'aim:south-2', label: 'Asia Pacific (Hyderabad)' },
  { value: 'aim:southeast-1', label: 'Asia Pacific (Singapore)' },
  { value: 'aim:southeast-2', label: 'Asia Pacific (Sydney)' },
  { value: 'aim:southeast-3', label: 'Asia Pacific (Jakarta)' },
  { value: 'aim:northeast-1', label: 'Asia Pacific (Tokyo)' },
  { value: 'aim:northeast-2', label: 'Asia Pacific (Seoul)' },
  { value: 'aim:northeast-3', label: 'Asia Pacific (Osaka)' },
  { value: 'ca-central-1', label: 'Canada (Central)' },
  { value: 'eu-central-1', label: 'Europe (Frankfurt)' },
  { value: 'eu-central-2', label: 'Europe (Zurich)' },
  { value: 'eu-west-1', label: 'Europe (Ireland)' },
  { value: 'eu-west-2', label: 'Europe (London)' },
  { value: 'eu-west-3', label: 'Europe (Paris)' },
  { value: 'eu-south-1', label: 'Europe (Milan)' },
  { value: 'eu-south-2', label: 'Europe (Spain)' },
  { value: 'eu-north-1', label: 'Europe (Stockholm)' },
  { value: 'il-central-1', label: 'Israel (Tel Aviv)' },
  { value: 'me-south-1', label: 'Middle East (Bahrain)' },
  { value: 'me-central-1', label: 'Middle East (UAE)' },
  { value: 'sa-east-1', label: 'South America (São Paulo)' },
];

const mailgunRegions = [
  { value: 'us', label: 'US' },
  { value: 'eu', label: 'EU' },
];

const encryptionOptions = [
  { value: 'none', label: 'None' },
  { value: 'ssl', label: 'SSL' },
  { value: 'tls', label: 'TLS' },
];

export default function Settings() {
  const globalSettings = useSettings();
  const [settings, setSettings] = createSignal(globalSettings() || null);
  const [saving, setSaving] = createSignal(false);
  const [connectionStatus, setConnectionStatus] = createSignal(null);
  const [connectionLoading, setConnectionLoading] = createSignal(true);
  const [testEmailTo, setTestEmailTo] = createSignal('');
  const [sendingTest, setSendingTest] = createSignal(false);
  const [testSuccess, setTestSuccess] = createSignal(false);
  const [editingSender, setEditingSender] = createSignal(false);
  const [savingSender, setSavingSender] = createSignal(false);
  const [saveError, setSaveError] = createSignal(null);
  const [constants, setConstants] = createSignal(window.insaneMailerAdmin?.constants || {});

  onMount(async () => {
    try {
      const result = await api.getConnectionStatus();
      if (result.data?.status) {
        setConnectionStatus(result.data.status);
      }
    } catch (error) {
      console.error('Failed to fetch connection status:', error);
    } finally {
      setConnectionLoading(false);
    }
  });

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);
    setConnectionStatus(null);
    setSaveError(null);

    try {
      const response = await api.updateSettings(settings());
      if (response.data?.connection_valid) {
        setConnectionStatus('success');
      }
      // Update global settings store
      updateGlobalSettings(settings());
      toast.success('Settings saved');
    } catch (error) {
      setConnectionStatus('error');
      setSaveError(error.message || 'Failed to save settings');
    } finally {
      setSaving(false);
    }
  };

  const handleSendTestEmail = async (e) => {
    e.preventDefault();
    if (!testEmailTo()) return;

    setSendingTest(true);
    setTestSuccess(false);

    try {
      await api.sendTestEmail(testEmailTo());
      setTestSuccess(true);
      setTestEmailTo('');
      toast.success('Test email sent');
    } catch (error) {
      toast.error('Failed to send: ' + error.message);
    } finally {
      setSendingTest(false);
    }
  };

  const updateSetting = (key, value) => {
    setSettings({ ...settings(), [key]: value });
    if (key === 'provider' && value !== 'default') {
      setTimeout(() => {
        document.getElementById('provider-config')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
    }
  };

  const handleSaveSender = async () => {
    setSavingSender(true);
    try {
      await api.updateSettings(settings());
      toast.success('Sender details saved');
      setEditingSender(false);
    } catch (error) {
      toast.error('Failed to save: ' + error.message);
    } finally {
      setSavingSender(false);
    }
  };

  const updateCredential = (key, value) => {
    const credentials = settings().credentials || {};
    setSettings({ ...settings(), credentials: { ...credentials, [key]: value } });
  };

  const getCredential = (key) => {
    return settings().credentials?.[key] || '';
  };

  const hasError = () => saveError() !== null;
  const inputErrorClass = () => hasError() ? 'im:border-red-300 im:ring-1 im:ring-red-300' : 'im:border-gray-300';

  const useConfig = () => settings().credentials?.use_config || false;

  const toggleUseConfig = (value) => {
    updateCredential('use_config', value);
  };

  const providerFields = {
    ses: [{ key: 'ses_access_key', label: 'Access Key ID' }, { key: 'ses_secret_key', label: 'Secret Access Key' }],
    sendgrid: [{ key: 'sendgrid_api_key', label: 'API Key' }],
    mailgun: [{ key: 'mailgun_api_key', label: 'API Key' }],
    postmark: [{ key: 'postmark_server_token', label: 'Server Token' }],
    brevo: [{ key: 'brevo_api_key', label: 'API Key' }],
    sparkpost: [{ key: 'sparkpost_api_key', label: 'API Key' }],
    mailjet: [{ key: 'mailjet_api_key', label: 'API Key' }, { key: 'mailjet_secret_key', label: 'Secret Key' }],
    elasticemail: [{ key: 'elasticemail_api_key', label: 'API Key' }],
    smtpcom: [{ key: 'smtpcom_api_key', label: 'API Key' }],
    pepipost: [{ key: 'pepipost_api_key', label: 'API Key' }],
    resend: [{ key: 'resend_api_key', label: 'API Key' }],
    mailersend: [{ key: 'mailersend_api_key', label: 'API Key' }],
    mailtraim: [{ key: 'mailtrap_api_key', label: 'API Key' }],
    loops: [{ key: 'loops_api_key', label: 'API Key' }],
    cloudflare: [{ key: 'cloudflare_api_token', label: 'API Token' }, { key: 'cloudflare_account_id', label: 'Account ID' }],
    mandrill: [{ key: 'mandrill_api_key', label: 'API Key' }],
    smtp2go: [{ key: 'smtp2go_api_key', label: 'API Key' }],
    socketlabs: [{ key: 'socketlabs_server_id', label: 'Server ID' }, { key: 'socketlabs_api_key', label: 'API Key' }],
    zeptomail: [{ key: 'zeptomail_api_key', label: 'Send Mail Token' }],
    gmail: [{ key: 'gmail_client_id', label: 'Client ID' }, { key: 'gmail_client_secret', label: 'Client Secret' }],
    outlook: [{ key: 'outlook_client_id', label: 'Client ID' }, { key: 'outlook_client_secret', label: 'Client Secret' }],
    smtp: [{ key: 'smtp_username', label: 'Username' }, { key: 'smtp_password', label: 'Password' }],
  };

  const currentProviderFields = () => providerFields[settings()?.provider] || [];

  const missingConstants = () => {
    if (!useConfig()) return [];
    return currentProviderFields().filter((f) => !constants()[f.key]);
  };

  const canSave = () => {
    if (settings()?.provider === 'default') return true;
    if (!useConfig()) return true;
    return missingConstants().length === 0;
  };

  const updateConstants = (newConstants) => {
    setConstants(newConstants);
    window.insaneMailerAdmin.constants = newConstants;
  };

  const selectedProvider = () => providers.find(p => p.id === settings()?.provider);
  const [providerSearch, setProviderSearch] = createSignal('');

  const filteredProviders = () => {
    const search = providerSearch().toLowerCase();
    if (!search) return providers;
    return providers.filter(p =>
      p.name.toLowerCase().includes(search) ||
      p.desc.toLowerCase().includes(search)
    );
  };

  return (
    <div>
      {settings() && (
        <>
        <div class="im:flex im:gap-8">
          <form onSubmit={handleSave} class="im:flex-1 im:space-y-8">
          <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-6">
            <div class="im:flex im:items-start im:justify-between im:mb-6">
              <div>
                <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-0 im:pb-0">Email Provider</h3>
                <p class="im:text-sm im:text-gray-500">Select your email delivery service</p>
              </div>
              <div class="im:relative">
                <svg class="im:absolute im:left-2.5 im:top-1/2 im:-translate-y-1/2 im:w-4 im:h-4 im:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                  type="text"
                  placeholder="Search..."
                  value={providerSearch()}
                  onInput={(e) => setProviderSearch(e.target.value)}
                  class="im:w-40 im:pl-8 im:pr-3 im:py-1.5 im:text-sm im:border im:border-gray-200 im:rounded-md focus:im:outline-none focus:im:ring-1 focus:im:ring-gray-300 focus:im:border-gray-300"
                />
              </div>
            </div>

            <div class="im:flex im:flex-wrap im:gap-4">
              <For each={filteredProviders()}>
                {(provider) => (
                  <div class="im-provider-wrap im:relative">
                    <button
                      type="button"
                      onClick={() => !provider.upcoming && updateSetting('provider', provider.id)}
                      disabled={provider.upcoming}
                      class={`im-provider-card im:relative im:flex im:items-center im:justify-center im:w-[120px] im:h-[100px] im:rounded-lg im:border-2 im:transition-colors im:duration-75 ${
                        provider.upcoming
                          ? 'im:border-gray-200 im:bg-gray-50 im:opacity-50 im:cursor-not-allowed'
                          : settings().provider === provider.id
                            ? 'im:border-gray-900 im:bg-gray-50'
                            : 'im:border-gray-200 im:bg-white hover:im:border-gray-300 hover:im:bg-gray-50'
                      }`}
                    >
                      {provider.upcoming && (
                        <span class="im:absolute im:top-0.5 im:right-0.5 im:px-1 im:py-0.5 im:text-[8px] im:font-medium im:bg-amber-100 im:text-amber-700 im:rounded">
                          soon
                        </span>
                      )}
                      {settings().provider === provider.id && !provider.upcoming && (
                        <span class="im:absolute im:top-0.5 im:right-0.5 im:w-4 im:h-4 im:bg-gray-900 im:rounded-full im:flex im:items-center im:justify-center">
                          <svg class="im:w-2.5 im:h-2.5 im:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                          </svg>
                        </span>
                      )}
                      <ProviderIcon id={provider.id} />
                    </button>
                    <div class="im-provider-tooltip im:absolute im:z-50 im:bottom-full im:left-1/2 im:-translate-x-1/2 im:mb-2 im:px-3 im:py-1.5 im:bg-gray-900 im:text-white im:text-sm im:font-medium im:rounded im:whitespace-nowrap im:pointer-events-none">
                      {provider.name}
                      <div class="im:absolute im:top-full im:left-1/2 im:-translate-x-1/2 im:border-4 im:border-transparent im:border-t-gray-900" />
                    </div>
                  </div>
                )}
              </For>
            </div>
          </div>

          {/* Provider Configuration */}
          <Show when={settings().provider !== 'default'}>
            <div id="provider-config" class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-6">
              <div class="im:flex im:items-start im:justify-between im:mb-6">
                <div>
                  <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-1">
                    {selectedProvider()?.name} Configuration
                  </h3>
                  <p class="im:text-sm im:text-gray-500">{selectedProvider()?.desc}</p>
                </div>
                <CredentialStorageToggle
                  credentials={settings().credentials}
                  onToggle={toggleUseConfig}
                />
              </div>

              <div class="im:space-y-5">

                <Show when={useConfig()}>
                  <ConfigConstants fields={providerFields[settings().provider] || []} onConstantsUpdate={updateConstants} />
                </Show>

                <Show when={!useConfig()}>
                  <Switch>
                    <Match when={settings().provider === 'ses'}>
                      <div class="im:space-y-4">
                        <div class="im:flex im:items-center im:gap-4">
                          <label class="im:text-sm im:font-medium im:text-gray-700">Connection Type:</label>
                          <div class="im:inline-flex im:rounded-lg im:border im:border-gray-200 im:p-1 im:bg-gray-50">
                            <button
                              type="button"
                              onClick={() => updateCredential('ses_auth_type', 'api')}
                              class={`im:px-3 im:py-1.5 im:text-sm im:font-medium im:rounded-md im:transition-all ${
                                getCredential('ses_auth_type') !== 'smtp'
                                  ? 'im:bg-white im:text-gray-900 im:shadow-sm'
                                  : 'im:text-gray-500 hover:im:text-gray-700'
                              }`}
                            >
                              IAM API
                            </button>
                            <button
                              type="button"
                              onClick={() => updateCredential('ses_auth_type', 'smtp')}
                              class={`im:px-3 im:py-1.5 im:text-sm im:font-medium im:rounded-md im:transition-all ${
                                getCredential('ses_auth_type') === 'smtp'
                                  ? 'im:bg-white im:text-gray-900 im:shadow-sm'
                                  : 'im:text-gray-500 hover:im:text-gray-700'
                              }`}
                            >
                              SMTP
                            </button>
                          </div>
                        </div>
                        <Show when={getCredential('ses_auth_type') !== 'smtp'}>
                          <div class="im:grid im:grid-cols-2 im:gap-4">
                            <div>
                              <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Access Key ID</label>
                              <input
                                type="text"
                                class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                                value={getCredential('access_key')}
                                onInput={(e) => updateCredential('access_key', e.target.value)}
                              />
                            </div>
                            <div>
                              <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Secret Access Key</label>
                              <PasswordInput
                                value={getCredential('secret_key')}
                                onInput={(e) => updateCredential('secret_key', e.target.value)}
                                hasError={hasError()}
                              />
                            </div>
                          </div>
                        </Show>
                        <Show when={getCredential('ses_auth_type') === 'smtp'}>
                          <div class="im:grid im:grid-cols-2 im:gap-4">
                            <div>
                              <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">SMTP Username</label>
                              <input
                                type="text"
                                class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                                value={getCredential('ses_smtp_username')}
                                onInput={(e) => updateCredential('ses_smtp_username', e.target.value)}
                              />
                            </div>
                            <div>
                              <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">SMTP Password</label>
                              <PasswordInput
                                value={getCredential('ses_smtp_password')}
                                onInput={(e) => updateCredential('ses_smtp_password', e.target.value)}
                                hasError={hasError()}
                              />
                            </div>
                          </div>
                        </Show>
                      </div>
                    </Match>

                    <Match when={settings().provider === 'mailgun'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'sendgrid'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'brevo'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'postmark'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Server Token</label>
                        <PasswordInput
                          value={getCredential('server_token')}
                          onInput={(e) => updateCredential('server_token', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'sparkpost'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'elasticemail'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'smtpcom'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'pepipost'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'mailjet'}>
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                          <input
                            type="text"
                            class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                            value={getCredential('api_key')}
                            onInput={(e) => updateCredential('api_key', e.target.value)}
                          />
                        </div>
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Secret Key</label>
                          <PasswordInput
                            value={getCredential('secret_key')}
                            onInput={(e) => updateCredential('secret_key', e.target.value)}
                            hasError={hasError()}
                          />
                        </div>
                      </div>
                    </Match>

                    <Match when={settings().provider === 'resend'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'mailersend'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'mailtrap'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'loops'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'cloudflare'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Token</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                        <p class="im:mt-2 im:text-xs im:text-gray-500">Needs the "Email Sending: Edit" permission.</p>
                      </div>
                    </Match>

                    <Match when={settings().provider === 'mandrill'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'smtp2go'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'socketlabs'}>
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Server ID</label>
                          <input
                            type="text"
                            class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                            value={getCredential('server_id')}
                            onInput={(e) => updateCredential('server_id', e.target.value)}
                          />
                        </div>
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                          <PasswordInput
                            value={getCredential('api_key')}
                            onInput={(e) => updateCredential('api_key', e.target.value)}
                            hasError={hasError()}
                          />
                        </div>
                      </div>
                    </Match>

                    <Match when={settings().provider === 'zeptomail'}>
                      <div class="im:max-w-md">
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Send Mail Token</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                          hasError={hasError()}
                        />
                      </div>
                    </Match>

                    <Match when={settings().provider === 'gmail'}>
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Client ID</label>
                          <input
                            type="text"
                            class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                            value={getCredential('client_id')}
                            onInput={(e) => updateCredential('client_id', e.target.value)}
                          />
                        </div>
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Client Secret</label>
                          <PasswordInput
                            value={getCredential('client_secret')}
                            onInput={(e) => updateCredential('client_secret', e.target.value)}
                            hasError={hasError()}
                          />
                        </div>
                      </div>
                    </Match>

                    <Match when={settings().provider === 'outlook'}>
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Client ID</label>
                          <input
                            type="text"
                            class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                            value={getCredential('client_id')}
                            onInput={(e) => updateCredential('client_id', e.target.value)}
                          />
                        </div>
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Client Secret</label>
                          <PasswordInput
                            value={getCredential('client_secret')}
                            onInput={(e) => updateCredential('client_secret', e.target.value)}
                            hasError={hasError()}
                          />
                        </div>
                      </div>
                    </Match>

                    <Match when={settings().provider === 'smtp'}>
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Username</label>
                          <input
                            type="text"
                            class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                            value={getCredential('username')}
                            onInput={(e) => updateCredential('username', e.target.value)}
                          />
                        </div>
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Password</label>
                          <PasswordInput
                            value={getCredential('password')}
                            onInput={(e) => updateCredential('password', e.target.value)}
                            hasError={hasError()}
                          />
                        </div>
                      </div>
                    </Match>
                  </Switch>
                </Show>

                {/* Non-sensitive fields shown regardless of storage mode */}
                <Switch>
                  <Match when={settings().provider === 'cloudflare'}>
                    <div class="im:max-w-md">
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Account ID</label>
                      <input
                        type="text"
                        class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                        value={getCredential('account_id')}
                        onInput={(e) => updateCredential('account_id', e.target.value)}
                        placeholder="023e105f4ecef8ad9ca31a8372d0c353"
                      />
                      <p class="im:mt-2 im:text-xs im:text-gray-500">Found in the Cloudflare dashboard sidebar.</p>
                    </div>
                  </Match>

                  <Match when={settings().provider === 'ses'}>
                    <div class="im:max-w-xs">
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Region</label>
                      <SearchableSelect
                        options={awsRegions}
                        value={getCredential('region') || 'us-east-1'}
                        onChange={(value) => updateCredential('region', value)}
                        placeholder="Select region..."
                      />
                    </div>
                  </Match>

                  <Match when={settings().provider === 'mailgun'}>
                    <div class="im:grid im:grid-cols-2 im:gap-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Domain</label>
                        <input
                          type="text"
                          class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                          value={getCredential('domain')}
                          onInput={(e) => updateCredential('domain', e.target.value)}
                          placeholder="mg.yourdomain.com"
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Region</label>
                        <SearchableSelect
                          options={mailgunRegions}
                          value={getCredential('region') || 'us'}
                          onChange={(value) => updateCredential('region', value)}
                          placeholder="Select region..."
                        />
                      </div>
                    </div>
                  </Match>

                  <Match when={settings().provider === 'smtpcom'}>
                    <div class="im:max-w-md">
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Channel</label>
                      <input
                        type="text"
                        class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                        value={getCredential('channel')}
                        onInput={(e) => updateCredential('channel', e.target.value)}
                        placeholder="your-channel-name"
                      />
                    </div>
                  </Match>

                  <Match when={settings().provider === 'smtp'}>
                    <div class="im:grid im:grid-cols-2 im:gap-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">SMTP Host</label>
                        <input
                          type="text"
                          class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                          value={getCredential('host')}
                          onInput={(e) => updateCredential('host', e.target.value)}
                          placeholder="smtp.example.com"
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Port</label>
                        <input
                          type="number"
                          class={`im:w-full im:px-3 im:py-2 im:border im:rounded-md im:text-sm im:outline-none ${inputErrorClass()}`}
                          value={getCredential('port') || 587}
                          onInput={(e) => updateCredential('port', parseInt(e.target.value))}
                        />
                      </div>
                    </div>
                    <div class="im:max-w-xs">
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Encryption</label>
                      <SearchableSelect
                        options={encryptionOptions}
                        value={getCredential('encryption') || 'tls'}
                        onChange={(value) => updateCredential('encryption', value)}
                        placeholder="Select encryption..."
                      />
                    </div>
                  </Match>
                </Switch>
              </div>
            </div>
          </Show>

          <Show when={saveError()}>
            <div class="im:flex im:items-start im:gap-3 im:p-4 im:bg-red-50 im:border im:border-red-200 im:rounded-lg">
              <svg class="im:w-5 im:h-5 im:text-red-500 im:shrink-0 im:mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <p class="im:text-sm im:font-medium im:text-red-800">Connection Failed</p>
                <p class="im:text-sm im:text-red-600 im:mt-1">{saveError()}</p>
              </div>
            </div>
          </Show>

          <div class="im:flex im:items-center im:gap-3">
            <button
              type="submit"
              class="im:px-4 im:py-2 im:bg-gray-900 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-800 im:outline-none disabled:im:opacity-50 disabled:im:cursor-not-allowed im:transition-colors"
              disabled={saving() || !canSave()}
            >
              {saving() ? 'Saving...' : 'Save Settings'}
            </button>
            <Show when={!canSave()}>
              <span class="im:text-sm im:text-amber-600">Define missing constants in wp-config.php to save</span>
            </Show>
          </div>
        </form>

          {/* Right Sidebar */}
          <div class="im:w-80 im:space-y-6 im:shrink-0">
            {/* Connection Status */}
            <Show when={settings().provider !== 'default'}>
              <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-6">
                <h4 class="im:text-sm im:font-semibold im:text-gray-900 im:mb-4 im:mt-0">Connection Status</h4>
                <Show when={connectionLoading()}>
                  <div class="im:flex im:items-center im:gap-3 im:text-gray-500">
                    <div class="im:w-2.5 im:h-2.5 im:rounded-full im:bg-gray-300 im:animate-pulse" />
                    <span class="im:text-sm">Loading...</span>
                  </div>
                </Show>
                <Show when={!connectionLoading() && connectionStatus() === null}>
                  <div class="im:flex im:items-center im:gap-3 im:text-gray-500">
                    <div class="im:w-2.5 im:h-2.5 im:rounded-full im:bg-gray-300" />
                    <span class="im:text-sm">Not tested</span>
                  </div>
                </Show>
                <Show when={!connectionLoading() && connectionStatus() === 'success'}>
                  <div class="im:flex im:items-center im:gap-3 im:text-green-600">
                    <div class="im:w-2.5 im:h-2.5 im:rounded-full im:bg-green-500" />
                    <span class="im:text-sm im:font-medium">Connected</span>
                  </div>
                </Show>
                <Show when={!connectionLoading() && connectionStatus() === 'error'}>
                  <div class="im:flex im:items-center im:gap-3 im:text-red-600">
                    <div class="im:w-2.5 im:h-2.5 im:rounded-full im:bg-red-500" />
                    <span class="im:text-sm im:font-medium">Connection failed</span>
                  </div>
                </Show>
              </div>
            </Show>

            {/* Sender Details */}
            <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-6 im:relative">
              <Show when={!editingSender()}>
                <button
                  type="button"
                  class="im:absolute im:top-5 im:right-5 im:p-1.5 im:text-gray-400 hover:im:text-gray-600 hover:im:bg-gray-100 im:rounded im:transition-colors"
                  onClick={() => setEditingSender(true)}
                  title="Edit"
                >
                  <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                  </svg>
                </button>
              </Show>
              <h4 class="im:text-sm im:font-semibold im:text-gray-900 im:mb-4 im:mt-0">Sender Details</h4>
              <div class="im:space-y-3">
                <Show when={!editingSender()}>
                  <div>
                    <div class="im:text-xs im:text-gray-500 im:mb-0.5">From Name</div>
                    <div class="im:text-sm im:text-gray-900">{settings().from_name || 'Not configured'}</div>
                  </div>
                  <div>
                    <div class="im:text-xs im:text-gray-500 im:mb-0.5">From Email</div>
                    <div class="im:text-sm im:text-gray-900">{settings().from_email || 'Not configured'}</div>
                  </div>
                  <Show when={settings().reply_to}>
                    <div>
                      <div class="im:text-xs im:text-gray-500 im:mb-0.5">Reply-To</div>
                      <div class="im:text-sm im:text-gray-900">{settings().reply_to}</div>
                    </div>
                  </Show>
                  <Show when={settings().force_from}>
                    <span class="im:inline-flex im:items-center im:gap-1 im:px-2 im:py-0.5 im:bg-amber-50 im:text-amber-700 im:text-xs im:rounded-full im:border im:border-amber-200">
                      <svg class="im:w-3 im:h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                      Force from enabled
                    </span>
                  </Show>
                </Show>
                <Show when={editingSender()}>
                  <div>
                    <label class="im:block im:text-xs im:text-gray-500 im:mb-1">From Name</label>
                    <input
                      type="text"
                      class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                      value={settings().from_name}
                      onInput={(e) => updateSetting('from_name', e.target.value)}
                    />
                  </div>
                  <div>
                    <label class="im:block im:text-xs im:text-gray-500 im:mb-1">From Email</label>
                    <input
                      type="email"
                      class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                      value={settings().from_email}
                      onInput={(e) => updateSetting('from_email', e.target.value)}
                    />
                  </div>
                  <div>
                    <label class="im:block im:text-xs im:text-gray-500 im:mb-1">Reply-To (optional)</label>
                    <input
                      type="email"
                      class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                      value={settings().reply_to || ''}
                      onInput={(e) => updateSetting('reply_to', e.target.value)}
                      placeholder="replies@example.com"
                    />
                  </div>
                  <label class="im:inline-flex im:items-center im:gap-2 im:cursor-pointer">
                    <input
                      type="checkbox"
                      class="im:w-4 im:h-4 im:shrink-0 im:rounded im:border-gray-300 im:text-gray-900 im:cursor-pointer"
                      checked={settings().force_from}
                      onChange={(e) => updateSetting('force_from', e.target.checked)}
                    />
                    <span class="im:text-sm im:text-gray-600 im:leading-none">Force from address</span>
                  </label>
                  <div class="im:flex im:items-center im:gap-2 im:pt-2">
                    <button
                      type="button"
                      class="im:flex-1 im:px-3 im:py-2 im:bg-gray-900 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-800 im:transition-colors disabled:im:opacity-50"
                      onClick={handleSaveSender}
                      disabled={savingSender()}
                    >
                      {savingSender() ? 'Saving...' : 'Save'}
                    </button>
                    <button
                      type="button"
                      class="im:px-3 im:py-2 im:text-gray-600 im:text-sm hover:im:text-gray-800 im:transition-colors"
                      onClick={() => setEditingSender(false)}
                    >
                      Cancel
                    </button>
                  </div>
                </Show>
              </div>
            </div>

            {/* Send Test Email */}
            <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-6">
              <h4 class="im:text-sm im:font-semibold im:text-gray-900 im:mb-4 im:mt-0">Send Test Email</h4>
              <Show when={testSuccess()}>
                <div class="im:mb-3 im:bg-green-50 im:border im:border-green-200 im:rounded-md im:px-3 im:py-2 im:flex im:items-center im:gap-2">
                  <svg class="im:w-4 im:h-4 im:text-green-600 im:shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <span class="im:text-sm im:font-medium im:text-green-800">Email sent! Check your inbox.</span>
                  <button
                    type="button"
                    class="im:ml-auto im:text-green-600 hover:im:text-green-800"
                    onClick={() => setTestSuccess(false)}
                  >
                    <svg class="im:w-3.5 im:h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                  </button>
                </div>
              </Show>
              <form onSubmit={handleSendTestEmail}>
                <div class="im:mb-2">
                  <label class="im:block im:text-xs im:text-gray-500 im:mb-1">Recipient</label>
                  <input
                    type="email"
                    class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm focus:im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                    placeholder="you@example.com"
                    value={testEmailTo()}
                    onInput={(e) => setTestEmailTo(e.target.value)}
                    required
                  />
                </div>
                <button
                  type="submit"
                  class="im:w-full im:px-3 im:py-2 im:bg-gray-900 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-800 im:outline-none disabled:im:opacity-30 disabled:im:cursor-not-allowed im:transition-colors"
                  disabled={sendingTest() || !testEmailTo() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(testEmailTo()) || (settings().provider !== 'default' && connectionStatus() !== 'success')}
                >
                  {sendingTest() ? 'Sending...' : 'Send Test'}
                </button>
              </form>
            </div>
          </div>
        </div>
        </>
      )}
    </div>
  );
}
