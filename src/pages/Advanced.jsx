import { createSignal, Show } from 'solid-js';
import { api } from '../api/client';
import SearchableSelect from '../components/SearchableSelect';
import { toast } from '../components/Toast';
import { useSettings, updateSettings as updateGlobalSettings } from '../store/settings';

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
  const [exporting, setExporting] = createSignal(false);
  const [importing, setImporting] = createSignal(false);
  const [resetting, setResetting] = createSignal(false);
  const [showResetConfirm, setShowResetConfirm] = createSignal(false);
  let fileInputRef;

  const activeTab = () => props.subTab || 'general';
  const setActiveTab = (tab) => props.onSubTabChange?.(tab);

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
                  class={`im:py-3 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${activeTab() === 'tools' ? 'im:border-gray-900 im:text-gray-900' : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'}`}
                  onClick={() => setActiveTab('tools')}
                >
                  Tools
                </button>
              </nav>
            </div>

            {/* Tab Content */}
            <div class="im:p-6">
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
