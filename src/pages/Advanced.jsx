import { createSignal, For, Show } from 'solid-js';
import { api } from '../api/client';
import SearchableSelect from '../components/SearchableSelect';
import { toast } from '../components/Toast';
import { useSettings, updateSettings as updateGlobalSettings } from '../store/settings';

const sendModes = [
  { id: 'queue', name: 'Queue', icon: '📬', desc: 'Recommended for reliability' },
  { id: 'direct', name: 'Immediately', icon: '⚡', desc: 'Send immediately' },
];

const queueRunnerOptions = [
  { value: 'wp_cron', label: 'WP Cron' },
  { value: 'external', label: 'External Cron (Recommended)' },
];

const cronIntervalOptions = [
  { value: 'every_minute', label: 'Every minute' },
  { value: 'every_5_minutes', label: 'Every 5 minutes' },
  { value: 'every_15_minutes', label: 'Every 15 minutes' },
  { value: 'every_30_minutes', label: 'Every 30 minutes' },
  { value: 'hourly', label: 'Hourly' },
];

const autoDeleteOptions = [
  { value: '7', label: '7 days' },
  { value: '14', label: '14 days' },
  { value: '30', label: '30 days' },
  { value: '180', label: '6 months' },
  { value: '365', label: '1 year' },
  { value: '0', label: 'Never' },
];

export default function Advanced(props) {
  const globalSettings = useSettings();
  const [settings, setSettings] = createSignal(globalSettings() || null);
  const [saving, setSaving] = createSignal(false);
  const [regenerating, setRegenerating] = createSignal(false);
  const [exporting, setExporting] = createSignal(false);
  const [importing, setImporting] = createSignal(false);
  const [resetting, setResetting] = createSignal(false);
  const [showResetConfirm, setShowResetConfirm] = createSignal(false);
  let fileInputRef;

  const activeTab = () => props.subTab || 'general';
  const setActiveTab = (tab) => props.onSubTabChange?.(tab);

  const siteUrl = window.insaneMailerAdmin?.siteUrl || window.location.origin;
  const cronEndpoint = () => `${siteUrl}/wp-json/insane-mailer/v1/cron?token=${settings()?.cron_token || ''}`;

  const handleSave = async (e) => {
    e.preventDefault();
    setSaving(true);

    try {
      await api.updateSettings(settings());
      // Update global settings store
      updateGlobalSettings(settings());
      toast.success('Settings saved');
    } catch (error) {
      toast.error('Failed to save: ' + error.message);
    } finally {
      setSaving(false);
    }
  };

  const updateSetting = (key, value) => {
    setSettings({ ...settings(), [key]: value });
  };

  const handleRegenerateToken = async () => {
    setRegenerating(true);
    try {
      const response = await api.regenerateCronToken();
      setSettings({ ...settings(), cron_token: response.data.token });
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

  const handleExport = async () => {
    setExporting(true);
    try {
      const response = await api.exportSettings();
      const blob = new Blob([JSON.stringify(response.data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `insane-mailer-settings-${new Date().toISOString().split('T')[0]}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
      toast.success('Settings exported');
    } catch (error) {
      toast.error('Failed to export: ' + error.message);
    } finally {
      setExporting(false);
    }
  };

  const handleImport = async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setImporting(true);
    try {
      const text = await file.text();
      const data = JSON.parse(text);
      const settingsData = data.settings || data;
      const response = await api.importSettings(settingsData);
      setSettings(response.data.settings);
      updateGlobalSettings(response.data.settings);
      toast.success('Settings imported');
    } catch (error) {
      toast.error('Failed to import: ' + error.message);
    } finally {
      setImporting(false);
      if (fileInputRef) fileInputRef.value = '';
    }
  };

  const handleReset = async () => {
    setResetting(true);
    try {
      const response = await api.resetSettings();
      setSettings(response.data.settings);
      updateGlobalSettings(response.data.settings);
      setShowResetConfirm(false);
      toast.success('Settings reset to defaults');
    } catch (error) {
      toast.error('Failed to reset: ' + error.message);
    } finally {
      setResetting(false);
    }
  };

  return (
    <div>
      {settings() && (
        <form onSubmit={handleSave}>
          <div class="im:bg-white im:rounded-lg im:border im:border-gray-200">
            {/* Tabs */}
            <div class="im:border-b im:border-gray-200 im:px-6">
              <nav class="im:flex im:gap-6 im:-mb-px">
                <button
                  type="button"
                  class={`im:py-3 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${activeTab() === 'general' ? 'im:border-gray-900 im:text-gray-900' : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'}`}
                  onClick={() => setActiveTab('general')}
                >
                  General
                </button>
                <button
                  type="button"
                  class={`im:py-3 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${activeTab() === 'send-mode' ? 'im:border-gray-900 im:text-gray-900' : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'}`}
                  onClick={() => setActiveTab('send-mode')}
                >
                  Send Mode
                </button>
                <Show when={settings().send_mode === 'queue'}>
                  <button
                    type="button"
                    class={`im:py-3 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${activeTab() === 'queue' ? 'im:border-gray-900 im:text-gray-900' : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'}`}
                    onClick={() => setActiveTab('queue')}
                  >
                    Queue Settings
                  </button>
                </Show>
                <button
                  type="button"
                  class={`im:py-3 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${activeTab() === 'tools' ? 'im:border-gray-900 im:text-gray-900' : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'}`}
                  onClick={() => setActiveTab('tools')}
                >
                  Tools
                </button>
              </nav>
            </div>

            {/* Tab Content */}
            <div class="im:p-6">
              {/* Send Mode Tab */}
              <Show when={activeTab() === 'send-mode'}>
                <div class="im:space-y-6">
                  <div>
                    <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-1">Send Mode</h3>
                    <p class="im:text-sm im:text-gray-500 im:mb-4">Choose how emails are processed</p>

                    <div class="im:flex im:flex-wrap im:gap-3">
                      <For each={sendModes}>
                        {(mode) => (
                          <button
                            type="button"
                            onClick={() => updateSetting('send_mode', mode.id)}
                            class={`im:relative im:flex im:flex-col im:items-center im:p-4 im:rounded-lg im:border-2 im:transition-all im:w-[150px] ${
                              settings().send_mode === mode.id
                                ? 'im:border-gray-900 im:bg-gray-50'
                                : 'im:border-gray-200 hover:im:border-gray-300 im:bg-white im:opacity-50 hover:im:opacity-100'
                            }`}
                          >
                            {settings().send_mode === mode.id && (
                              <span class="im:absolute im:top-2 im:right-2 im:w-5 im:h-5 im:bg-gray-900 im:rounded-full im:flex im:items-center im:justify-center">
                                <svg class="im:w-3 im:h-3 im:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                              </span>
                            )}
                            <span class="im:text-2xl im:mb-2">{mode.icon}</span>
                            <span class="im:text-sm im:font-medium im:text-gray-900">{mode.name}</span>
                          </button>
                        )}
                      </For>
                    </div>

                    <Show when={settings().send_mode === 'direct'}>
                      <div class="im:mt-4 im:flex im:items-center im:gap-3 im:p-3 im:bg-amber-50 im:border im:border-amber-200 im:rounded-lg">
                        <svg class="im:w-5 im:h-5 im:text-amber-500 im:flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="im:text-sm im:text-amber-800">
                          Sending emails immediately may slightly reduce page performance, especially when sending to multiple recipients.
                        </p>
                      </div>
                    </Show>
                  </div>

                  <Show when={settings().send_mode === 'queue'}>
                    <div class="im:border-t im:border-gray-200 im:pt-6">
                      <div class="im:flex im:items-start im:gap-4">
                        <button
                          type="button"
                          onClick={() => updateSetting('priority_express', !settings().priority_express)}
                          class={`im:relative im:inline-flex im:h-6 im:w-11 im:flex-shrink-0 im:cursor-pointer im:rounded-full im:border-2 im:border-transparent im:transition-colors im:duration-200 im:ease-in-out ${
                            settings().priority_express ? 'im:bg-gray-900' : 'im:bg-gray-200'
                          }`}
                        >
                          <span
                            class={`im:pointer-events-none im:inline-block im:h-5 im:w-5 im:transform im:rounded-full im:bg-white im:shadow im:ring-0 im:transition im:duration-200 im:ease-in-out ${
                              settings().priority_express ? 'im:translate-x-5' : 'im:translate-x-0'
                            }`}
                          />
                        </button>
                        <div class="im:flex-1" onClick={() => updateSetting('priority_express', !settings().priority_express)}>
                          <div class="im:flex im:items-center im:gap-2 im:cursor-pointer">
                            <span class="im:text-sm im:font-medium im:text-gray-900">Priority Express</span>
                            <div class="im:relative group" onClick={(e) => e.stopPropagation()}>
                              <svg class="im:w-4 im:h-4 im:text-gray-400 im:cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                              </svg>
                              <div class="im:absolute im:z-50 im:bottom-full im:left-1/2 im:-translate-x-1/2 im:mb-2 im:w-64 im:p-2 im:bg-gray-900 im:text-white im:text-xs im:rounded-lg im:shadow-lg im:opacity-0 im:invisible group-hover:im:opacity-100 group-hover:im:visible im:transition-all">
                                High-priority emails like password resets, order confirmations, and 2FA codes will bypass the queue and send immediately.
                                <div class="im:absolute im:top-full im:left-1/2 im:-translate-x-1/2 im:border-4 im:border-transparent im:border-t-gray-900" />
                              </div>
                            </div>
                          </div>
                          <p class="im:text-sm im:text-gray-500 im:mt-1 im:cursor-pointer">
                            Send critical emails immediately, queue the rest.
                          </p>
                        </div>
                      </div>
                    </div>
                  </Show>
                </div>
              </Show>

              {/* Queue Tab */}
              <Show when={activeTab() === 'queue'}>
                <div class="im:space-y-6">
                  <div>
                    <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-1">Queue Settings</h3>
                    <p class="im:text-sm im:text-gray-500 im:mb-4">Configure how emails are processed and sent</p>
                  </div>

                  <div class="im:grid im:grid-cols-2 im:gap-4 im:max-w-md">
                    <div>
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">
                        Queue Runner
                      </label>
                      <SearchableSelect
                        options={queueRunnerOptions}
                        value={settings().queue_runner}
                        onChange={(value) => updateSetting('queue_runner', value)}
                        placeholder="Select queue runner..."
                      />
                    </div>
                    <Show when={settings().queue_runner === 'wp_cron'}>
                      <div>
                        <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">
                          Interval
                        </label>
                        <SearchableSelect
                          options={cronIntervalOptions}
                          value={settings().cron_interval || 'every_minute'}
                          onChange={(value) => updateSetting('cron_interval', value)}
                          placeholder="Select interval..."
                        />
                      </div>
                    </Show>
                  </div>

                  <Show when={settings().queue_runner === 'external'}>
                    <div>
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">
                        External Cron Endpoint
                      </label>
                      <div class="im:bg-gray-900 im:rounded-md im:p-3 im:mb-3">
                        <code class="im:text-xs im:text-gray-100 im:font-mono im:break-all">{cronEndpoint()}</code>
                      </div>
                      <div class="im:flex im:gap-2">
                        <button
                          type="button"
                          class="im:flex im:items-center im:gap-1.5 im:px-3 im:py-1.5 im:text-sm im:font-medium im:bg-gray-100 im:text-gray-700 im:rounded-md hover:im:bg-gray-200 im:transition-colors"
                          onClick={handleCopyEndpoint}
                        >
                          <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                          </svg>
                          Copy Endpoint
                        </button>
                        <button
                          type="button"
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
                      <p class="im:text-xs im:text-gray-500 im:mt-3">Use this URL in your external cron service (e.g., cron-job.org) or add to server crontab</p>
                    </div>
                  </Show>

                  <div class="im:border-t im:border-gray-200 im:pt-6" />

                  <div class="im:grid im:grid-cols-3 im:gap-4 im:max-w-2xl">
                    <div>
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">
                        Bulk Limit
                      </label>
                      <input
                        type="number"
                        min="1"
                        max="50000"
                        class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none"
                        value={settings().bulk_limit}
                        onInput={(e) => updateSetting('bulk_limit', Math.min(50000, Math.max(1, parseInt(e.target.value) || 1)))}
                      />
                      <p class="im:text-xs im:text-gray-500 im:mt-1">Emails per queue run (1-50000)</p>
                    </div>

                    <div>
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">
                        Rate Limit
                      </label>
                      <input
                        type="number"
                        min="1"
                        max="50000"
                        class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none"
                        value={settings().rate_limit}
                        onInput={(e) => updateSetting('rate_limit', Math.min(50000, Math.max(1, parseInt(e.target.value) || 1)))}
                      />
                      <p class="im:text-xs im:text-gray-500 im:mt-1">Emails per second (1-50000)</p>
                    </div>

                    <div>
                      <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">
                        Max Retries
                      </label>
                      <input
                        type="number"
                        min="1"
                        max="100"
                        class="im:w-full im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none"
                        value={settings().max_retries}
                        onInput={(e) => updateSetting('max_retries', Math.min(100, Math.max(1, parseInt(e.target.value) || 1)))}
                      />
                      <p class="im:text-xs im:text-gray-500 im:mt-1">Retry attempts (1-100)</p>
                    </div>
                  </div>
                </div>
              </Show>

              {/* General Tab */}
              <Show when={activeTab() === 'general'}>
                <div class="im:space-y-6">
                  <div>
                    <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-1">General Settings</h3>
                    <p class="im:text-sm im:text-gray-500 im:mb-4">Configure general email settings</p>
                  </div>

                  <div class="im:max-w-xs">
                    <label class="im:block im:text-sm im:font-medium im:text-gray-700 im:mb-2">
                      Auto Delete Logs
                    </label>
                    <SearchableSelect
                      options={autoDeleteOptions}
                      value={String(settings().auto_delete_days)}
                      onChange={(value) => updateSetting('auto_delete_days', parseInt(value))}
                      placeholder="Select duration..."
                    />
                    <p class="im:text-xs im:text-gray-500 im:mt-1">Automatically delete old email logs</p>
                  </div>

                  <div class="im:border-t im:border-gray-200 im:pt-6">
                    <div class="im:flex im:items-start im:gap-4">
                      <button
                        type="button"
                        onClick={() => updateSetting('pause_sending', !settings().pause_sending)}
                        class={`im:relative im:inline-flex im:h-6 im:w-11 im:flex-shrink-0 im:cursor-pointer im:rounded-full im:border-2 im:border-transparent im:transition-colors im:duration-200 im:ease-in-out ${
                          settings().pause_sending ? 'im:bg-red-600' : 'im:bg-gray-200'
                        }`}
                      >
                        <span
                          class={`im:pointer-events-none im:inline-block im:h-5 im:w-5 im:transform im:rounded-full im:bg-white im:shadow im:ring-0 im:transition im:duration-200 im:ease-in-out ${
                            settings().pause_sending ? 'im:translate-x-5' : 'im:translate-x-0'
                          }`}
                        />
                      </button>
                      <div class="im:flex-1" onClick={() => updateSetting('pause_sending', !settings().pause_sending)}>
                        <div class="im:flex im:items-center im:gap-2 im:cursor-pointer">
                          <span class="im:text-sm im:font-medium im:text-red-600">Pause Sending</span>
                          <span class="im:px-1.5 im:py-0.5 im:text-[10px] im:font-semibold im:uppercase im:bg-red-100 im:text-red-600 im:rounded">Debug</span>
                        </div>
                        <p class="im:text-sm im:text-gray-500 im:mt-1 im:cursor-pointer">
                          Skip actual email delivery but still create logs.
                        </p>
                      </div>
                    </div>
                    <Show when={settings().pause_sending}>
                      <div class="im:mt-4 im:flex im:items-center im:gap-2 im:p-3 im:bg-red-50 im:border im:border-red-200 im:rounded-lg">
                        <svg class="im:w-5 im:h-5 im:text-red-500 im:flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="im:text-sm im:text-red-700">
                          <strong>Warning:</strong> Email sending is paused. Emails will be logged but not delivered.
                        </p>
                      </div>
                    </Show>
                  </div>
                </div>
              </Show>

              {/* Tools Tab */}
              <Show when={activeTab() === 'tools'}>
                <div class="im:space-y-6">
                  <div>
                    <h3 class="im:text-base im:font-semibold im:text-gray-900 im:mb-1">Settings Tools</h3>
                    <p class="im:text-sm im:text-gray-500 im:mb-4">Export, import, or reset your settings</p>
                  </div>

                  {/* Export */}
                  <div class="im:flex im:items-start im:gap-4 im:pt-4 im:px-4 im:pb-0 im:bg-gray-50 im:rounded-lg">
                    <div class="im:flex-1">
                      <h4 class="im:text-sm im:font-medium im:text-gray-900 im:mt-0 im:mb-1 im:pt-0 im:pb-0">Export Settings</h4>
                      <p class="im:text-sm im:text-gray-500 im:mt-1">Download your current settings as a JSON file for backup or migration.</p>
                    </div>
                    <button
                      type="button"
                      class="im:px-4 im:py-2 im:bg-gray-900 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-800 im:transition-colors disabled:im:opacity-50"
                      onClick={handleExport}
                      disabled={exporting()}
                    >
                      {exporting() ? 'Exporting...' : 'Export'}
                    </button>
                  </div>

                  {/* Import */}
                  <div class="im:flex im:items-start im:gap-4 im:pt-4 im:px-4 im:pb-0 im:bg-gray-50 im:rounded-lg">
                    <div class="im:flex-1">
                      <h4 class="im:text-sm im:font-medium im:text-gray-900 im:mt-0 im:mb-1 im:pt-0 im:pb-0">Import Settings</h4>
                      <p class="im:text-sm im:text-gray-500 im:mt-1">Restore settings from a previously exported JSON file.</p>
                    </div>
                    <input
                      type="file"
                      accept=".json"
                      class="im:hidden"
                      ref={fileInputRef}
                      onChange={handleImport}
                    />
                    <button
                      type="button"
                      class="im:px-4 im:py-2 im:bg-gray-900 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-800 im:transition-colors disabled:im:opacity-50"
                      onClick={() => fileInputRef?.click()}
                      disabled={importing()}
                    >
                      {importing() ? 'Importing...' : 'Import'}
                    </button>
                  </div>

                  {/* Reset */}
                  <div class="im:flex im:items-start im:gap-4 im:pt-4 im:px-4 im:pb-0 im:bg-red-50 im:rounded-lg im:border im:border-red-200">
                    <div class="im:flex-1">
                      <h4 class="im:text-sm im:font-medium im:text-red-900 im:mt-0 im:mb-1 im:pt-0 im:pb-0">Reset Settings</h4>
                      <p class="im:text-sm im:text-red-700 im:mt-1">Reset all settings to their default values. This cannot be undone.</p>
                    </div>
                    <Show when={!showResetConfirm()}>
                      <button
                        type="button"
                        class="im:px-4 im:py-2 im:bg-red-600 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-red-700 im:transition-colors"
                        onClick={() => setShowResetConfirm(true)}
                      >
                        Reset
                      </button>
                    </Show>
                    <Show when={showResetConfirm()}>
                      <div class="im:flex im:gap-2">
                        <button
                          type="button"
                          class="im:px-4 im:py-2 im:bg-gray-200 im:text-gray-700 im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-300 im:transition-colors"
                          onClick={() => setShowResetConfirm(false)}
                        >
                          Cancel
                        </button>
                        <button
                          type="button"
                          class="im:px-4 im:py-2 im:bg-red-600 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-red-700 im:transition-colors disabled:im:opacity-50"
                          onClick={handleReset}
                          disabled={resetting()}
                        >
                          {resetting() ? 'Resetting...' : 'Confirm Reset'}
                        </button>
                      </div>
                    </Show>
                  </div>
                </div>
              </Show>
            </div>
          </div>

          <Show when={activeTab() !== 'tools'}>
            <div class="im:flex im:items-center im:gap-3 im:mt-6">
              <button
                type="submit"
                class="im:px-4 im:py-2 im:bg-gray-900 im:text-white im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-800 im:outline-none disabled:im:opacity-50 disabled:im:cursor-not-allowed im:transition-colors"
                disabled={saving()}
              >
                {saving() ? 'Saving...' : 'Save Settings'}
              </button>
            </div>
          </Show>
        </form>
      )}
    </div>
  );
}
