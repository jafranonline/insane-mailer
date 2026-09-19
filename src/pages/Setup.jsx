import { createSignal, For, Show, Switch, Match, onMount } from 'solid-js';
import { api } from '../api/client';
import { toast } from '../components/Toast';
import { useSettings, updateSettings as updateGlobalSettings } from '../store/settings';
import SearchableSelect from '../components/SearchableSelect';
import PasswordInput from '../components/PasswordInput';
import { CredentialStorageToggle, ConfigConstants } from '../components/CredentialField';

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

// Plugin icon component
const PluginIcon = ({ slug }) => {
  const icons = {
    'fluentsmtp': { color: '#7742E6', icon: <path d="M7 8h10M7 12h7M7 16h4" stroke="white" stroke-width="2" stroke-linecap="round"/> },
    'wp-mail-smtp': { color: '#E27730', icon: <path d="M6 8l6 4 6-4M6 16l6-4 6 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> },
    'post-smtp': { color: '#00A0D2', icon: <path d="M7 9l5 3 5-3v6l-5 3-5-3z" stroke="white" stroke-width="1.5" fill="none"/> },
    'easy-wp-smtp': { color: '#1ABC9C', icon: <path d="M6 12h12M12 6v12" stroke="white" stroke-width="2" stroke-linecap="round"/> },
    'gmail-smtp': { color: '#EA4335', icon: <><path d="M6 8l6 5 6-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><rect x="6" y="8" width="12" height="9" rx="1" stroke="white" stroke-width="1.5" fill="none"/></> },
    'gosmtp': { color: '#4CAF50', icon: <path d="M8 12l3 3 5-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> },
    'smtp-mailer': { color: '#5C6BC0', icon: <path d="M6 9l6 4 6-4v7H6z" stroke="white" stroke-width="1.5" fill="none"/> },
    'wp-smtp': { color: '#2196F3', icon: <><circle cx="12" cy="12" r="4" stroke="white" stroke-width="2" fill="none"/><path d="M12 6v2M12 16v2M6 12h2M16 12h2" stroke="white" stroke-width="2" stroke-linecap="round"/></> },
    'yaysmtp': { color: '#FF9800', icon: <><path d="M7 10l5 5 5-5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 15v3" stroke="white" stroke-width="2" stroke-linecap="round"/></> },
  };

  const data = icons[slug] || icons['easy-wp-smtp'];

  return (
    <svg viewBox="0 0 24 24" class="im:w-8 im:h-8">
      <rect x="2" y="2" width="20" height="20" rx="4" fill={data.color}/>
      {data.icon}
    </svg>
  );
};

const ProviderIcon = ({ id }) => {
  const logo = providerLogos[id];
  const iconClass = "im:w-[90%] im:h-[70%] im:flex im:items-center im:justify-center im:[&>svg]:max-w-full im:[&>svg]:max-h-full";
  const largeIconClass = "im:w-full im:h-full im:flex im:items-center im:justify-center im:[&>svg]:max-w-full im:[&>svg]:max-h-full im:[&>svg]:w-full";
  const smallIconClass = "im:w-[70%] im:h-[55%] im:flex im:items-center im:justify-center im:[&>svg]:max-w-full im:[&>svg]:max-h-full";

  if (id === 'smtp') {
    return (
      <div class="im:flex im:flex-col im:items-center im:justify-center im:h-full im:gap-1">
        <svg viewBox="0 0 24 24" fill="none" class="im:w-8 im:h-8">
          <rect x="2" y="4" width="20" height="16" rx="2" stroke="#6366f1" stroke-width="1.5" fill="none"/>
          <path d="M2 7l10 5 10-5" stroke="#6366f1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="im:text-[9px] im:font-medium im:text-gray-600">Other SMTP</span>
      </div>
    );
  }

  if (logo) {
    const useLargeClass = id === 'postmark' || id === 'outlook';
    const useSmallClass = id === 'zeptomail' || id === 'gmail' || id === 'ses' || id === 'brevo';
    const classToUse = useLargeClass ? largeIconClass : (useSmallClass ? smallIconClass : iconClass);
    return <div class={classToUse} innerHTML={logo} />;
  }

  return null;
};

const providers = [
  { id: 'default', name: 'PHP Mailer', desc: 'WordPress default' },
  { id: 'ses', name: 'Amazon SES', desc: 'Amazon Simple Email Service' },
  { id: 'sendgrid', name: 'SendGrid', desc: 'Twilio SendGrid' },
  { id: 'mailgun', name: 'Mailgun', desc: 'Transactional email' },
  { id: 'postmark', name: 'Postmark', desc: 'Transactional email' },
  { id: 'brevo', name: 'Brevo', desc: 'Formerly Sendinblue' },
  { id: 'sparkpost', name: 'SparkPost', desc: 'Enterprise email' },
  { id: 'mailjet', name: 'Mailjet', desc: 'Email service' },
  { id: 'elasticemail', name: 'Elastic Email', desc: 'Email delivery' },
  { id: 'smtpcom', name: 'SMTP.com', desc: 'Enterprise SMTP' },
  { id: 'pepipost', name: 'Netcore', desc: 'Pepipost email' },
  { id: 'resend', name: 'Resend', desc: 'Modern email API' },
  { id: 'mailersend', name: 'MailerSend', desc: 'By MailerLite' },
  { id: 'mailtrap', name: 'Mailtrap', desc: 'Dev & production' },
  { id: 'loops', name: 'Loops', desc: 'SaaS email' },
  { id: 'cloudflare', name: 'Cloudflare', desc: 'Cloudflare Email Service' },
  { id: 'mandrill', name: 'Mandrill', desc: 'Mailchimp Transactional' },
  { id: 'smtp2go', name: 'SMTP2GO', desc: 'SMTP relay' },
  { id: 'socketlabs', name: 'SocketLabs', desc: 'Email delivery' },
  { id: 'zeptomail', name: 'ZeptoMail', desc: 'By Zoho' },
  { id: 'gmail', name: 'Gmail', desc: 'Google Workspace' },
  { id: 'outlook', name: 'Outlook', desc: 'Microsoft 365' },
  { id: 'smtp', name: 'Other SMTP', desc: 'Custom SMTP server' },
];

const awsRegions = [
  { value: 'us-east-1', label: 'US East (N. Virginia)' },
  { value: 'us-east-2', label: 'US East (Ohio)' },
  { value: 'us-west-1', label: 'US West (N. California)' },
  { value: 'us-west-2', label: 'US West (Oregon)' },
  { value: 'eu-west-1', label: 'Europe (Ireland)' },
  { value: 'eu-west-2', label: 'Europe (London)' },
  { value: 'eu-central-1', label: 'Europe (Frankfurt)' },
  { value: 'ap-southeast-1', label: 'Asia Pacific (Singapore)' },
  { value: 'ap-southeast-2', label: 'Asia Pacific (Sydney)' },
  { value: 'ap-northeast-1', label: 'Asia Pacific (Tokyo)' },
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

const providerLabels = {
  'smtp': 'Custom SMTP',
  'ses': 'Amazon SES',
  'sendgrid': 'SendGrid',
  'mailgun': 'Mailgun',
  'postmark': 'Postmark',
  'brevo': 'Brevo',
  'sparkpost': 'SparkPost',
  'cloudflare': 'Cloudflare Email Service',
  'default': 'PHP Mail',
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

// Steps:
// 'choose' - Initial choice (import from plugin OR manual)
// 'import-preview' - Preview import settings
// 'provider' - Choose provider (manual flow)
// 'credentials' - Enter credentials (manual flow)
// 'sender' - Sender details
// 'mode' - Send mode
// 'complete' - Done

export default function Setup({ onComplete }) {
  const globalSettings = useSettings();
  const isAlreadyConfigured = () => globalSettings()?.provider && globalSettings()?.provider !== 'default';
  const [step, setStep] = createSignal(isAlreadyConfigured() ? 'provider' : 'choose');
  const [settings, setSettings] = createSignal({
    provider: globalSettings()?.provider || 'default',
    credentials: globalSettings()?.credentials || {},
    from_name: globalSettings()?.from_name || '',
    from_email: globalSettings()?.from_email || '',
    force_from: globalSettings()?.force_from ?? true,
    send_mode: globalSettings()?.send_mode || 'direct',
  });

  const [migrations, setMigrations] = createSignal([]);
  const [loadingMigrations, setLoadingMigrations] = createSignal(true);
  const [selectedPlugin, setSelectedPlugin] = createSignal(null);
  const [previewData, setPreviewData] = createSignal(null);
  const [loadingPreview, setLoadingPreview] = createSignal(false);
  const [importing, setImporting] = createSignal(false);

  const [testing, setTesting] = createSignal(false);
  const [testError, setTestError] = createSignal(null);
  const [saving, setSaving] = createSignal(false);
  const [constants, setConstants] = createSignal(window.insaneMailerAdmin?.constants || {});

  onMount(async () => {
    try {
      const response = await api.detectMigrations();
      const plugins = response.data?.plugins || [];
      setMigrations(plugins);
    } catch (error) {
      console.error('Failed to detect migrations:', error);
    } finally {
      setLoadingMigrations(false);
    }
  });

  const updateSetting = (key, value) => {
    setSettings({ ...settings(), [key]: value });
  };

  const updateCredential = (key, value) => {
    const credentials = settings().credentials || {};
    setSettings({ ...settings(), credentials: { ...credentials, [key]: value } });
  };

  const getCredential = (key) => settings().credentials?.[key] || '';

  const useConfig = () => settings().credentials?.use_config || false;

  const toggleUseConfig = (value) => {
    updateCredential('use_config', value);
  };

  const updateConstants = (newConstants) => {
    setConstants(newConstants);
    window.insaneMailerAdmin.constants = newConstants;
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

  const handleSelectPlugin = async (plugin) => {
    setSelectedPlugin(plugin);
    setLoadingPreview(true);

    try {
      const response = await api.previewMigration(plugin.slug);
      setPreviewData(response.data);
      setStep('import-preview');
    } catch (error) {
      toast.error('Failed to load settings: ' + error.message);
      setSelectedPlugin(null);
    } finally {
      setLoadingPreview(false);
    }
  };

  const handleImport = async () => {
    if (!selectedPlugin()) return;
    setImporting(true);

    try {
      const response = await api.importMigration(selectedPlugin().slug);
      if (response.data?.settings) {
        const imported = response.data.settings;
        // Update local state with all imported settings
        setSettings((prev) => ({
          ...prev,
          ...imported,
          // Ensure send_mode has a default if not imported
          send_mode: imported.send_mode || prev.send_mode,
        }));
        updateGlobalSettings(response.data.settings);
      }
      toast.success(`Settings imported from ${selectedPlugin().name}`);
      setStep('deactivate');
    } catch (error) {
      toast.error('Import failed: ' + error.message);
    } finally {
      setImporting(false);
    }
  };

  const [deactivating, setDeactivating] = createSignal(false);

  const handleDeactivatePlugin = async () => {
    if (!selectedPlugin()) return;
    setDeactivating(true);

    try {
      await api.deactivatePlugin(selectedPlugin().slug);
      toast.success(`${selectedPlugin().name} has been deactivated`);
      setStep('sender');
    } catch (error) {
      toast.error('Failed to deactivate plugin: ' + error.message);
    } finally {
      setDeactivating(false);
    }
  };

  const handleSkipDeactivate = () => {
    setStep('sender');
  };

  const handleTestConnection = async () => {
    setTesting(true);
    setTestError(null);

    try {
      await api.testConnection(settings().provider, settings().credentials);
      setStep('sender');
    } catch (error) {
      setTestError(error.message || 'Connection test failed');
    } finally {
      setTesting(false);
    }
  };

  const handleSave = async () => {
    setSaving(true);

    try {
      const settingsToSave = {
        ...settings(),
        setup_completed: true,
      };
      await api.updateSettings(settingsToSave);
      updateGlobalSettings(settingsToSave);
      setStep('complete');
    } catch (error) {
      toast.error('Failed to save settings: ' + error.message);
    } finally {
      setSaving(false);
    }
  };

  const handleFinish = () => {
    if (onComplete) {
      onComplete();
    } else {
      window.location.hash = 'overview';
    }
  };

  const handleSkip = async () => {
    try {
      const settingsToSave = {
        ...settings(),
        setup_completed: true,
      };
      await api.updateSettings(settingsToSave);
      updateGlobalSettings(settingsToSave);
      handleFinish();
    } catch (error) {
      toast.error('Failed to skip setuim: ' + error.message);
    }
  };

  const needsCredentials = () => {
    const p = settings().provider;
    return p !== 'default';
  };

  const getStepNumber = () => {
    const s = step();
    const isImportFlow = selectedPlugin() !== null;

    if (isImportFlow) {
      if (s === 'choose') return 1;
      if (s === 'import-preview') return 1;
      if (s === 'deactivate') return 2;
      if (s === 'sender') return 3;
      if (s === 'mode') return 4;
      return 4;
    } else {
      if (s === 'choose') return 1;
      if (s === 'provider') return 1;
      if (s === 'credentials') return 2;
      if (s === 'sender') return settings().provider === 'default' ? 2 : 3;
      if (s === 'mode') return settings().provider === 'default' ? 3 : 4;
      return 4;
    }
  };

  const getTotalSteps = () => {
    if (selectedPlugin()) return 4;
    return settings().provider === 'default' ? 3 : 4;
  };

  return (
    <div class="im:min-h-[60vh] im:flex im:items-center im:justify-center im:py-8">
      <div class="im:w-full im:max-w-2xl">
        {/* Step Indicator */}
        <Show when={step() !== 'complete' && step() !== 'choose' && step() !== 'deactivate'}>
          <div class="im:flex im:items-center im:justify-center im:gap-2 im:mb-8">
            <For each={Array.from({ length: getTotalSteps() }, (_, i) => i + 1)}>
              {(num) => (
                <div class="im:flex im:items-center">
                  <div class={`im:w-8 im:h-8 im:rounded-full im:flex im:items-center im:justify-center im:text-sm im:font-medium im:transition-colors ${
                    getStepNumber() >= num ? 'im:bg-gray-900 im:text-white' : 'im:bg-gray-200 im:text-gray-500'
                  }`}>
                    {num}
                  </div>
                  <Show when={num < getTotalSteps()}>
                    <div class={`im:w-8 im:h-0.5 ${getStepNumber() >= num ? 'im:bg-gray-900' : 'im:bg-gray-200'}`} />
                  </Show>
                </div>
              )}
            </For>
          </div>
        </Show>

        {/* Card */}
        <div class="im:bg-white im:rounded-xl im:border im:border-gray-200 im:shadow-sm">
          {/* Steim: Choose (Import or Manual) */}
          <Show when={step() === 'choose'}>
            <div class="im:p-8">
              <h2 class="im:text-xl im:font-semibold im:text-gray-900 im:mb-2 im:text-center">Welcome to Insane Mailer</h2>
              <p class="im:text-gray-600 im:mb-6 im:text-center">How would you like to configure your email?</p>

              <Show when={loadingMigrations()}>
                <div class="im:py-8 im:text-center">
                  <div class="im:animate-spin im:w-8 im:h-8 im:border-2 im:border-gray-900 im:border-t-transparent im:rounded-full im:mx-auto" />
                  <p class="im:mt-3 im:text-sm im:text-gray-500">Checking for existing configurations...</p>
                </div>
              </Show>

              <Show when={!loadingMigrations()}>
                <div class="im:grid im:grid-cols-2 im:gap-4">
                  {/* Import options */}
                  <For each={migrations()}>
                    {(plugin) => (
                      <button
                        type="button"
                        class={`im:px-4 im:py-3 im:rounded-lg im:border-2 im:transition-colors im:text-left im:h-full im:flex im:flex-col ${
                          plugin.has_settings
                            ? 'im:border-gray-200 hover:im:border-gray-400'
                            : 'im:border-gray-100 im:bg-gray-50 im:opacity-60 im:cursor-not-allowed'
                        }`}
                        onClick={() => plugin.has_settings && handleSelectPlugin(plugin)}
                        disabled={loadingPreview() || !plugin.has_settings}
                      >
                        <div class="im:flex im:items-start im:gap-2 im:flex-1">
                          <div class="im:shrink-0 im:mt-0.5">
                            <PluginIcon slug={plugin.slug} />
                          </div>
                          <div class="im:flex-1 im:min-w-0">
                            <div class="im:flex im:items-center im:gap-2 im:flex-wrap">
                              <span class="im:font-semibold im:text-gray-900">{plugin.name}</span>
                              <Show when={!plugin.has_settings}>
                                <span class="im:text-xs im:px-1.5 im:py-0.5 im:bg-gray-200 im:text-gray-500 im:rounded">Not configured</span>
                              </Show>
                            </div>
                            <p class="im:text-sm im:text-gray-500 im:mt-0.5">
                              <Show when={plugin.has_settings}>
                                {providerLabels[plugin.provider] || plugin.provider}
                              </Show>
                              <Show when={!plugin.has_settings}>
                                No settings to import
                              </Show>
                            </p>
                          </div>
                        </div>
                      </button>
                    )}
                  </For>

                  {/* Manual configure option */}
                  <button
                    type="button"
                    class={`im:px-4 im:py-3 im:rounded-lg im:border-2 im:border-gray-200 hover:im:border-gray-400 im:transition-colors im:text-left im:h-full im:flex im:flex-col ${migrations().length === 0 ? 'im:col-span-2' : ''}`}
                    onClick={() => setStep('provider')}
                  >
                    <div class="im:flex im:items-start im:gap-2 im:flex-1">
                      <div class="im:w-8 im:h-8 im:rounded-lg im:bg-gray-100 im:flex im:items-center im:justify-center im:shrink-0 im:mt-0.5">
                        <svg class="im:w-5 im:h-5 im:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                      </div>
                      <div class="im:flex-1 im:min-w-0">
                        <span class="im:font-semibold im:text-gray-900">Configure Manually</span>
                        <p class="im:text-sm im:text-gray-500 im:mt-0.5">Manual setup</p>
                      </div>
                    </div>
                  </button>
                </div>

                <Show when={loadingPreview()}>
                  <div class="im:mt-4 im:text-center im:text-sm im:text-gray-500">
                    Loading settings...
                  </div>
                </Show>
              </Show>
            </div>
          </Show>

          {/* Steim: Import Preview */}
          <Show when={step() === 'import-preview'}>
            <div class="im:p-8">
              <h2 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-1">
                Import from {selectedPlugin()?.name}
              </h2>
              <p class="im:text-sm im:text-gray-600 im:mb-6">Review settings before importing</p>

              <Show when={previewData()}>
                {/* OAuth Warning */}
                <Show when={previewData().is_oauth}>
                  <div class="im:mb-4 im:p-3 im:bg-amber-50 im:border im:border-amber-200 im:rounded-lg im:flex im:gap-3">
                    <svg class="im:w-5 im:h-5 im:text-amber-500 im:shrink-0 im:mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                      <p class="im:text-sm im:font-medium im:text-amber-800">OAuth tokens cannot be migrated</p>
                      <p class="im:text-sm im:text-amber-700">You will need to re-authorize after import.</p>
                    </div>
                  </div>
                </Show>

                {/* Settings Preview */}
                <div class="im:bg-gray-50 im:rounded-lg im:p-4 im:space-y-3 im:mb-6">
                  <div class="im:flex im:justify-between">
                    <span class="im:text-sm im:text-gray-500">Provider</span>
                    <span class="im:text-sm im:font-medium im:text-gray-900">
                      {providerLabels[previewData().settings?.provider] || previewData().settings?.provider}
                    </span>
                  </div>
                  <Show when={previewData().settings?.from_email}>
                    <div class="im:flex im:justify-between">
                      <span class="im:text-sm im:text-gray-500">From Email</span>
                      <span class="im:text-sm im:font-medium im:text-gray-900">
                        {previewData().settings.from_email}
                      </span>
                    </div>
                  </Show>
                  <Show when={previewData().settings?.from_name}>
                    <div class="im:flex im:justify-between">
                      <span class="im:text-sm im:text-gray-500">From Name</span>
                      <span class="im:text-sm im:font-medium im:text-gray-900">
                        {previewData().settings.from_name}
                      </span>
                    </div>
                  </Show>
                  <div class="im:flex im:justify-between">
                    <span class="im:text-sm im:text-gray-500">Force From</span>
                    <span class="im:text-sm im:font-medium im:text-gray-900">
                      {previewData().settings?.force_from ? 'Yes' : 'No'}
                    </span>
                  </div>

                  {/* Credentials Preview (masked) */}
                  <Show when={previewData().settings?.credentials}>
                    <div class="im:pt-2 im:border-t im:border-gray-200">
                      <p class="im:text-xs im:text-gray-400 im:uppercase im:tracking-wide im:mb-2">Credentials</p>
                      <For each={Object.entries(previewData().settings.credentials)}>
                        {([key, value]) => (
                          <Show when={value}>
                            <div class="im:flex im:justify-between im:py-1">
                              <span class="im:text-sm im:text-gray-500 im:capitalize">
                                {key.replace(/_/g, ' ')}
                              </span>
                              <span class="im:text-sm im:font-mono im:text-gray-900">
                                {typeof value === 'boolean' ? (value ? 'Yes' : 'No') : value}
                              </span>
                            </div>
                          </Show>
                        )}
                      </For>
                    </div>
                  </Show>
                </div>
              </Show>

              <div class="im:flex im:justify-between">
                <button
                  type="button"
                  class="im:px-4 im:py-2.5 im:text-gray-700 im:font-medium im:rounded-lg hover:im:bg-gray-100 im:transition-colors"
                  onClick={() => {
                    setStep('choose');
                    setSelectedPlugin(null);
                    setPreviewData(null);
                  }}
                >
                  Back
                </button>
                <button
                  type="button"
                  class="im:px-6 im:py-2.5 im:bg-gray-900 im:text-white im:font-medium im:rounded-lg hover:im:bg-gray-800 im:transition-colors disabled:im:opacity-50"
                  onClick={handleImport}
                  disabled={importing()}
                >
                  {importing() ? 'Importing...' : 'Import & Continue'}
                </button>
              </div>
            </div>
          </Show>

          {/* Steim: Deactivate Plugin (Import flow) */}
          <Show when={step() === 'deactivate'}>
            <div class="im:p-8 im:text-center">
              <div class="im:w-16 im:h-16 im:rounded-full im:bg-green-100 im:flex im:items-center im:justify-center im:mx-auto im:mb-4">
                <svg class="im:w-8 im:h-8 im:text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <h2 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-2">Settings Imported Successfully</h2>
              <p class="im:text-gray-600 im:mb-6">
                Would you like to deactivate <span class="im:font-medium">{selectedPlugin()?.name}</span> to avoid conflicts?
              </p>

              <div class="im:bg-amber-50 im:border im:border-amber-200 im:rounded-lg im:p-4 im:mb-6 im:text-left">
                <div class="im:flex im:gap-3">
                  <svg class="im:w-5 im:h-5 im:text-amber-500 im:shrink-0 im:mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <div>
                    <p class="im:text-sm im:text-amber-800 im:font-medium">Recommended</p>
                    <p class="im:text-sm im:text-amber-700">
                      Having multiple SMTP plugins active can cause email delivery issues.
                    </p>
                  </div>
                </div>
              </div>

              <div class="im:flex im:flex-col im:gap-3">
                <button
                  type="button"
                  class="im:w-full im:px-6 im:py-3 im:bg-gray-900 im:text-white im:font-medium im:rounded-lg hover:im:bg-gray-800 im:transition-colors disabled:im:opacity-50"
                  onClick={handleDeactivatePlugin}
                  disabled={deactivating()}
                >
                  {deactivating() ? 'Deactivating...' : `Deactivate ${selectedPlugin()?.name}`}
                </button>
                <button
                  type="button"
                  class="im:w-full im:px-6 im:py-2.5 im:text-gray-600 im:font-medium im:rounded-lg hover:im:bg-gray-100 im:transition-colors"
                  onClick={handleSkipDeactivate}
                >
                  Keep it active, continue setup
                </button>
              </div>
            </div>
          </Show>

          {/* Steim: Provider Selection (Manual flow) */}
          <Show when={step() === 'provider'}>
            <div class="im:p-8">
              <h2 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-1">Choose Email Provider</h2>
              <p class="im:text-sm im:text-gray-600 im:mb-6">Select how you want to send emails</p>

              <div class="im:grid im:grid-cols-5 im:gap-3 im:mb-6">
                <For each={providers}>
                  {(provider) => (
                    <button
                      type="button"
                      onClick={() => updateSetting('provider', provider.id)}
                      class={`im:relative im:flex im:items-center im:justify-center im:w-full im:aspect-square im:rounded-lg im:border-2 im:transition-colors ${
                        settings().provider === provider.id
                          ? 'im:border-gray-900 im:bg-gray-50'
                          : 'im:border-gray-200 hover:im:border-gray-300'
                      }`}
                      title={provider.name}
                    >
                      <Show when={settings().provider === provider.id}>
                        <span class="im:absolute im:top-0.5 im:right-0.5 im:w-4 im:h-4 im:bg-gray-900 im:rounded-full im:flex im:items-center im:justify-center">
                          <svg class="im:w-2.5 im:h-2.5 im:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                          </svg>
                        </span>
                      </Show>
                      <ProviderIcon id={provider.id} />
                    </button>
                  )}
                </For>
              </div>

              <div class="im:flex im:justify-between">
                <Show when={!isAlreadyConfigured()}>
                  <button
                    type="button"
                    class="im:px-4 im:py-2.5 im:text-gray-700 im:font-medium im:rounded-lg hover:im:bg-gray-100 im:transition-colors"
                    onClick={() => setStep('choose')}
                  >
                    Back
                  </button>
                </Show>
                <Show when={isAlreadyConfigured()}>
                  <div />
                </Show>
                <button
                  type="button"
                  class="im:px-6 im:py-2.5 im:bg-gray-900 im:text-white im:font-medium im:rounded-lg hover:im:bg-gray-800 im:transition-colors"
                  onClick={() => setStep(needsCredentials() ? 'credentials' : 'sender')}
                >
                  Continue
                </button>
              </div>
            </div>
          </Show>

          {/* Steim: Credentials (Manual flow) */}
          <Show when={step() === 'credentials'}>
            <form class="im:p-8" onSubmit={(e) => { e.preventDefault(); if (canSave()) handleTestConnection(); }}>
              <div class="im:flex im:items-start im:justify-between im:mb-6">
                <div>
                  <h2 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-1">
                    Configure {providers.find(p => p.id === settings().provider)?.name}
                  </h2>
                  <p class="im:text-sm im:text-gray-600">Enter your API credentials</p>
                </div>
                <CredentialStorageToggle
                  credentials={settings().credentials}
                  onToggle={toggleUseConfig}
                />
              </div>

              <Show when={useConfig()}>
                <div class="im:mb-6">
                  <ConfigConstants
                    fields={currentProviderFields()}
                    onConstantsUpdate={updateConstants}
                  />
                </div>
              </Show>

              <Show when={!useConfig()}>
              <div class="im:space-y-4 im:mb-6">
                <Switch>
                  <Match when={settings().provider === 'ses'}>
                    <div class="im:space-y-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Access Key ID</label>
                        <input
                          type="text"
                          class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                          value={getCredential('access_key')}
                          onInput={(e) => updateCredential('access_key', e.target.value)}
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Secret Access Key</label>
                        <PasswordInput
                          value={getCredential('secret_key')}
                          onInput={(e) => updateCredential('secret_key', e.target.value)}
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Region</label>
                        <SearchableSelect
                          options={awsRegions}
                          value={getCredential('region') || 'us-east-1'}
                          onChange={(value) => updateCredential('region', value)}
                          placeholder="Select region..."
                        />
                      </div>
                    </div>
                  </Match>

                  <Match when={settings().provider === 'mailgun'}>
                    <div class="im:space-y-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Domain</label>
                        <input
                          type="text"
                          class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
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
                        />
                      </div>
                    </div>
                  </Match>

                  <Match when={settings().provider === 'smtp'}>
                    <div class="im:space-y-4">
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">SMTP Host</label>
                          <input
                            type="text"
                            class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                            value={getCredential('host')}
                            onInput={(e) => updateCredential('host', e.target.value)}
                            placeholder="smtp.example.com"
                          />
                        </div>
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Port</label>
                          <input
                            type="number"
                            class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                            value={getCredential('port') || 587}
                            onInput={(e) => updateCredential('port', parseInt(e.target.value))}
                          />
                        </div>
                      </div>
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Username</label>
                          <input
                            type="text"
                            class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                            value={getCredential('username')}
                            onInput={(e) => updateCredential('username', e.target.value)}
                          />
                        </div>
                        <div>
                          <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Password</label>
                          <PasswordInput
                            value={getCredential('password')}
                            onInput={(e) => updateCredential('password', e.target.value)}
                          />
                        </div>
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Encryption</label>
                        <SearchableSelect
                          options={encryptionOptions}
                          value={getCredential('encryption') || 'tls'}
                          onChange={(value) => updateCredential('encryption', value)}
                        />
                      </div>
                    </div>
                  </Match>

                  <Match when={settings().provider === 'socketlabs'}>
                    <div class="im:space-y-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Server ID</label>
                        <input
                          type="text"
                          class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                          value={getCredential('server_id')}
                          onInput={(e) => updateCredential('server_id', e.target.value)}
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                        />
                      </div>
                    </div>
                  </Match>

                  <Match when={settings().provider === 'mailjet'}>
                    <div class="im:space-y-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                        <input
                          type="text"
                          class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Secret Key</label>
                        <PasswordInput
                          value={getCredential('secret_key')}
                          onInput={(e) => updateCredential('secret_key', e.target.value)}
                        />
                      </div>
                    </div>
                  </Match>

                  <Match when={settings().provider === 'cloudflare'}>
                    <div class="im:space-y-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Token</label>
                        <PasswordInput
                          value={getCredential('api_key')}
                          onInput={(e) => updateCredential('api_key', e.target.value)}
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Account ID</label>
                        <input
                          type="text"
                          class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                          value={getCredential('account_id')}
                          onInput={(e) => updateCredential('account_id', e.target.value)}
                          placeholder="023e105f4ecef8ad9ca31a8372d0c353"
                        />
                      </div>
                    </div>
                  </Match>

                  <Match when={['gmail', 'outlook'].includes(settings().provider)}>
                    <div class="im:space-y-4">
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Client ID</label>
                        <input
                          type="text"
                          class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                          value={getCredential('client_id')}
                          onInput={(e) => updateCredential('client_id', e.target.value)}
                        />
                      </div>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">Client Secret</label>
                        <PasswordInput
                          value={getCredential('client_secret')}
                          onInput={(e) => updateCredential('client_secret', e.target.value)}
                        />
                      </div>
                    </div>
                  </Match>

                  {/* Default: Single API Key field */}
                  <Match when={!['ses', 'mailgun', 'smtp', 'socketlabs', 'mailjet', 'gmail', 'outlook', 'cloudflare'].includes(settings().provider)}>
                    <div>
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">API Key</label>
                      <PasswordInput
                        value={getCredential('api_key')}
                        onInput={(e) => updateCredential('api_key', e.target.value)}
                      />
                    </div>
                  </Match>
                </Switch>
              </div>
              </Show>

              <Show when={testError()}>
                <div class="im:mb-4 im:p-3 im:bg-red-50 im:border im:border-red-200 im:rounded-lg im:flex im:items-start im:gap-3">
                  <svg class="im:w-5 im:h-5 im:text-red-500 im:shrink-0 im:mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <div>
                    <p class="im:text-sm im:font-medium im:text-red-800">Connection Failed</p>
                    <p class="im:text-sm im:text-red-600">{testError()}</p>
                  </div>
                </div>
              </Show>

              <div class="im:flex im:items-center im:justify-between">
                <button
                  type="button"
                  class="im:px-4 im:py-2.5 im:text-gray-700 im:font-medium im:rounded-lg hover:im:bg-gray-100 im:transition-colors"
                  onClick={() => setStep('provider')}
                >
                  Back
                </button>
                <div class="im:flex im:items-center im:gap-3">
                  <Show when={useConfig() && !canSave()}>
                    <span class="im:text-sm im:text-amber-600">Define missing constants to continue</span>
                  </Show>
                  <button
                    type="submit"
                    class="im:px-6 im:py-2.5 im:bg-gray-900 im:text-white im:font-medium im:rounded-lg hover:im:bg-gray-800 im:transition-colors disabled:im:opacity-50"
                    disabled={testing() || !canSave()}
                  >
                    {testing() ? 'Testing...' : 'Test & Continue'}
                  </button>
                </div>
              </div>
            </form>
          </Show>

          {/* Steim: Sender Details */}
          <Show when={step() === 'sender'}>
            <form class="im:p-8" onSubmit={(e) => { e.preventDefault(); setStep('mode'); }}>
              <h2 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-1">Sender Details</h2>
              <p class="im:text-sm im:text-gray-600 im:mb-6">Configure the "From" address for your emails</p>

              <div class="im:space-y-4 im:mb-6">
                <div>
                  <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">From Name</label>
                  <input
                    type="text"
                    class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                    value={settings().from_name}
                    onInput={(e) => updateSetting('from_name', e.target.value)}
                    placeholder="My Website"
                  />
                </div>
                <div>
                  <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">From Email</label>
                  <input
                    type="email"
                    class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
                    value={settings().from_email}
                    onInput={(e) => updateSetting('from_email', e.target.value)}
                    placeholder="admin@example.com"
                  />
                </div>
                <label class="im:flex im:items-start im:gap-3 im:cursor-pointer">
                  <input
                    type="checkbox"
                    class="im:mt-1 im:w-4 im:h-4 im:rounded im:border-gray-300 im:text-gray-900 im:cursor-pointer"
                    checked={settings().force_from}
                    onChange={(e) => updateSetting('force_from', e.target.checked)}
                  />
                  <div>
                    <span class="im:text-sm im:font-medium im:text-gray-700">Force from address</span>
                    <p class="im:text-xs im:text-gray-500 im:mt-0.5">Override the from address set by plugins and themes</p>
                  </div>
                </label>
              </div>

              <div class="im:flex im:justify-between">
                <button
                  type="button"
                  class="im:px-4 im:py-2.5 im:text-gray-700 im:font-medium im:rounded-lg hover:im:bg-gray-100 im:transition-colors"
                  onClick={() => {
                    if (selectedPlugin()) {
                      setStep('import-preview');
                    } else if (needsCredentials()) {
                      setStep('credentials');
                    } else {
                      setStep('provider');
                    }
                  }}
                >
                  Back
                </button>
                <button
                  type="submit"
                  class="im:px-6 im:py-2.5 im:bg-gray-900 im:text-white im:font-medium im:rounded-lg hover:im:bg-gray-800 im:transition-colors"
                >
                  Continue
                </button>
              </div>
            </form>
          </Show>

          {/* Steim: Send Mode */}
          <Show when={step() === 'mode'}>
            <form class="im:p-8" onSubmit={(e) => { e.preventDefault(); handleSave(); }}>
              <h2 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-1">Send Mode</h2>
              <p class="im:text-sm im:text-gray-600 im:mb-6">Choose how emails should be sent</p>

              <div class="im:space-y-3 im:mb-6">
                <button
                  type="button"
                  class={`im:w-full im:p-4 im:rounded-lg im:border-2 im:text-left im:transition-colors ${
                    settings().send_mode === 'queue'
                      ? 'im:border-gray-900 im:bg-gray-50'
                      : 'im:border-gray-200 hover:im:border-gray-300'
                  }`}
                  onClick={() => updateSetting('send_mode', 'queue')}
                >
                  <div class="im:flex im:items-start im:gap-3">
                    <div class={`im:w-5 im:h-5 im:rounded-full im:border-2 im:flex im:items-center im:justify-center im:mt-0.5 ${
                      settings().send_mode === 'queue' ? 'im:border-gray-900' : 'im:border-gray-300'
                    }`}>
                      <Show when={settings().send_mode === 'queue'}>
                        <div class="im:w-2.5 im:h-2.5 im:rounded-full im:bg-gray-900" />
                      </Show>
                    </div>
                    <div>
                      <div class="im:font-medium im:text-gray-900">Queue (Recommended)</div>
                      <p class="im:text-sm im:text-gray-500 im:mt-1">
                        Emails are queued and sent in the background. More reliable and prevents timeouts.
                      </p>
                    </div>
                  </div>
                </button>

                <button
                  type="button"
                  class={`im:w-full im:p-4 im:rounded-lg im:border-2 im:text-left im:transition-colors ${
                    settings().send_mode === 'direct'
                      ? 'im:border-gray-900 im:bg-gray-50'
                      : 'im:border-gray-200 hover:im:border-gray-300'
                  }`}
                  onClick={() => updateSetting('send_mode', 'direct')}
                >
                  <div class="im:flex im:items-start im:gap-3">
                    <div class={`im:w-5 im:h-5 im:rounded-full im:border-2 im:flex im:items-center im:justify-center im:mt-0.5 ${
                      settings().send_mode === 'direct' ? 'im:border-gray-900' : 'im:border-gray-300'
                    }`}>
                      <Show when={settings().send_mode === 'direct'}>
                        <div class="im:w-2.5 im:h-2.5 im:rounded-full im:bg-gray-900" />
                      </Show>
                    </div>
                    <div>
                      <div class="im:font-medium im:text-gray-900">Direct</div>
                      <p class="im:text-sm im:text-gray-500 im:mt-1">
                        Emails are sent immediately. Faster but may timeout on large emails.
                      </p>
                    </div>
                  </div>
                </button>
              </div>

              <div class="im:flex im:justify-between">
                <button
                  type="button"
                  class="im:px-4 im:py-2.5 im:text-gray-700 im:font-medium im:rounded-lg hover:im:bg-gray-100 im:transition-colors"
                  onClick={() => setStep('sender')}
                >
                  Back
                </button>
                <button
                  type="submit"
                  class="im:px-6 im:py-2.5 im:bg-gray-900 im:text-white im:font-medium im:rounded-lg hover:im:bg-gray-800 im:transition-colors disabled:im:opacity-50"
                  disabled={saving()}
                >
                  {saving() ? 'Saving...' : 'Complete Setup'}
                </button>
              </div>
            </form>
          </Show>

          {/* Steim: Complete */}
          <Show when={step() === 'complete'}>
            <div class="im:p-8 im:text-center">
              <div class="im:w-16 im:h-16 im:rounded-full im:bg-green-100 im:flex im:items-center im:justify-center im:mx-auto im:mb-4">
                <svg class="im:w-8 im:h-8 im:text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
              </div>
              <h2 class="im:text-lg im:font-semibold im:text-gray-900 im:mb-2">Setup Complete!</h2>
              <p class="im:text-sm im:text-gray-600 im:mb-6">
                Your email configuration has been saved successfully.
              </p>

              <button
                type="button"
                class="im:px-6 im:py-2.5 im:bg-gray-900 im:text-white im:font-medium im:rounded-lg hover:im:bg-gray-800 im:transition-colors"
                onClick={handleFinish}
              >
                Go to Dashboard
              </button>
            </div>
          </Show>
        </div>

        {/* Help Footer */}
        <Show when={step() !== 'complete'}>
          <div class="im:mt-6 im:text-center im:space-y-3">
            <p class="im:text-sm im:text-gray-500">
              Need help? Check our{' '}
              <a
                href="#docs"
                onClick={(e) => {
                  e.preventDefault();
                  if (onComplete) onComplete();
                  window.location.hash = 'docs';
                }}
                class="im:text-gray-700 im:underline hover:im:text-gray-900"
              >
                documentation
              </a>
              {' '}or visit the{' '}
              <a
                href="#docs/providers"
                onClick={(e) => {
                  e.preventDefault();
                  if (onComplete) onComplete();
                  window.location.hash = 'docs/providers';
                }}
                class="im:text-gray-700 im:underline hover:im:text-gray-900"
              >
                provider setup guides
              </a>
            </p>
            <button
              type="button"
              onClick={handleSkip}
              class="im:text-sm im:text-gray-400 hover:im:text-gray-600 im:transition-colors"
            >
              Skip setup for now
            </button>
          </div>
        </Show>
      </div>
    </div>
  );
}
