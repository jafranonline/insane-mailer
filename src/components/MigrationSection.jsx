import { createSignal, For, Show, onMount } from 'solid-js';
import { api } from '../api/client';
import { toast } from './Toast';
import { updateSettings as updateGlobalSettings } from '../store/settings';

const pluginLogos = {
  'fluentsmtp': (
    <svg viewBox="0 0 24 24" class="im:w-8 im:h-8">
      <rect x="2" y="2" width="20" height="20" rx="4" fill="#7742E6"/>
      <path d="M7 8h10M7 12h7M7 16h4" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>
  ),
  'wp-mail-smtp': (
    <svg viewBox="0 0 24 24" class="im:w-8 im:h-8">
      <rect x="2" y="2" width="20" height="20" rx="4" fill="#E27730"/>
      <path d="M6 8l6 4 6-4M6 16l6-4 6 4" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
  ),
  'post-smtp': (
    <svg viewBox="0 0 24 24" class="im:w-8 im:h-8">
      <rect x="2" y="2" width="20" height="20" rx="4" fill="#00A0D2"/>
      <path d="M7 9l5 3 5-3v6l-5 3-5-3z" stroke="white" stroke-width="1.5" fill="none"/>
    </svg>
  ),
  'easy-wp-smtp': (
    <svg viewBox="0 0 24 24" class="im:w-8 im:h-8">
      <rect x="2" y="2" width="20" height="20" rx="4" fill="#1ABC9C"/>
      <path d="M6 12h12M12 6v12" stroke="white" stroke-width="2" stroke-linecap="round"/>
    </svg>
  ),
};

const providerLabels = {
  'smtp': 'Custom SMTP',
  'ses': 'Amazon SES',
  'sendgrid': 'SendGrid',
  'mailgun': 'Mailgun',
  'postmark': 'Postmark',
  'brevo': 'Brevo',
  'sparkpost': 'SparkPost',
  'gmail': 'Gmail',
  'outlook': 'Outlook',
  'mandrill': 'Mandrill',
  'elasticemail': 'Elastic Email',
  'smtpcom': 'SMTP.com',
  'pepipost': 'Netcore',
  'zeptomail': 'ZeptoMail',
  'smtp2go': 'SMTP2GO',
  'mailjet': 'Mailjet',
  'default': 'PHP Mail',
};

export default function MigrationSection({ onImportComplete }) {
  const [plugins, setPlugins] = createSignal([]);
  const [loading, setLoading] = createSignal(true);
  const [expanded, setExpanded] = createSignal(true);
  const [previewPlugin, setPreviewPlugin] = createSignal(null);
  const [previewData, setPreviewData] = createSignal(null);
  const [loadingPreview, setLoadingPreview] = createSignal(false);
  const [importing, setImporting] = createSignal(false);

  onMount(async () => {
    try {
      const response = await api.detectMigrations();
      setPlugins(response.data?.plugins || []);
    } catch (error) {
      console.error('Failed to detect migrations:', error);
    } finally {
      setLoading(false);
    }
  });

  const handlePreview = async (plugin) => {
    setPreviewPlugin(plugin);
    setLoadingPreview(true);

    try {
      const response = await api.previewMigration(plugin.slug);
      setPreviewData(response.data);
    } catch (error) {
      toast.error('Failed to load preview: ' + error.message);
      setPreviewPlugin(null);
    } finally {
      setLoadingPreview(false);
    }
  };

  const handleImport = async () => {
    if (!previewPlugin()) return;

    setImporting(true);

    try {
      const response = await api.importMigration(previewPlugin().slug);
      toast.success(`Settings imported from ${previewPlugin().name}`);

      // Update global settings
      if (response.data?.settings) {
        updateGlobalSettings(response.data.settings);
      }

      // Close modal and notify parent
      setPreviewPlugin(null);
      setPreviewData(null);

      // Remove imported plugin from list
      setPlugins(plugins().filter(p => p.slug !== previewPlugin().slug));

      // Notify parent to refresh
      if (onImportComplete) {
        onImportComplete(response.data?.settings);
      }
    } catch (error) {
      toast.error('Import failed: ' + error.message);
    } finally {
      setImporting(false);
    }
  };

  const closeModal = () => {
    setPreviewPlugin(null);
    setPreviewData(null);
  };

  // Don't render if no plugins detected
  if (!loading() && plugins().length === 0) {
    return null;
  }

  return (
    <>
      <Show when={!loading() && plugins().length > 0}>
        <div class="im:bg-gradient-to-r im:from-indigo-50 im:to-purple-50 im:rounded-lg im:border im:border-indigo-200 im:mb-8">
          <button
            type="button"
            class="im:w-full im:flex im:items-center im:justify-between im:p-4"
            onClick={() => setExpanded(!expanded())}
          >
            <div class="im:flex im:items-center im:gap-3">
              <div class="im:w-10 im:h-10 im:rounded-full im:bg-indigo-100 im:flex im:items-center im:justify-center">
                <svg class="im:w-5 im:h-5 im:text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
              </div>
              <div class="im:text-left">
                <h3 class="im:text-base im:font-semibold im:text-gray-900">Import Settings</h3>
                <p class="im:text-sm im:text-gray-600">
                  {plugins().length} SMTP plugin{plugins().length > 1 ? 's' : ''} detected with importable settings
                </p>
              </div>
            </div>
            <svg
              class={`im:w-5 im:h-5 im:text-gray-500 im:transition-transform ${expanded() ? 'im:rotate-180' : ''}`}
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <Show when={expanded()}>
            <div class="im:px-4 im:pb-4">
              <div class="im:grid im:gap-3">
                <For each={plugins()}>
                  {(plugin) => (
                    <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-4 im:flex im:items-center im:justify-between">
                      <div class="im:flex im:items-center im:gap-4">
                        <div class="im:shrink-0">
                          {pluginLogos[plugin.slug] || pluginLogos['easy-wp-smtp']}
                        </div>
                        <div>
                          <h4 class="im:font-medium im:text-gray-900">{plugin.name}</h4>
                          <div class="im:flex im:items-center im:gap-2 im:mt-1">
                            <span class="im:text-sm im:text-gray-600">
                              {providerLabels[plugin.provider] || plugin.provider}
                            </span>
                            <Show when={plugin.from_email}>
                              <span class="im:text-gray-300">|</span>
                              <span class="im:text-sm im:text-gray-500">{plugin.from_email}</span>
                            </Show>
                            <Show when={plugin.is_oauth}>
                              <span class="im:px-1.5 im:py-0.5 im:text-xs im:bg-amber-100 im:text-amber-700 im:rounded">
                                OAuth
                              </span>
                            </Show>
                          </div>
                        </div>
                      </div>
                      <button
                        type="button"
                        class="im:px-4 im:py-2 im:bg-indigo-600 im:text-white im:text-sm im:font-medium im:rounded-md hover:im:bg-indigo-700 im:transition-colors"
                        onClick={() => handlePreview(plugin)}
                      >
                        Preview Import
                      </button>
                    </div>
                  )}
                </For>
              </div>
            </div>
          </Show>
        </div>
      </Show>

      {/* Preview Modal */}
      <Show when={previewPlugin()}>
        <div class="im:fixed im:inset-0 im:z-50 im:flex im:items-center im:justify-center im:p-4">
          <div class="im:absolute im:inset-0 im:bg-black/50" onClick={closeModal} />
          <div class="im:relative im:bg-white im:rounded-xl im:shadow-xl im:max-w-lg im:w-full im:max-h-[80vh] im:overflow-auto">
            <div class="im:p-6">
              <div class="im:flex im:items-center im:justify-between im:mb-6">
                <div class="im:flex im:items-center im:gap-3">
                  {pluginLogos[previewPlugin().slug]}
                  <div>
                    <h3 class="im:text-lg im:font-semibold im:text-gray-900">
                      Import from {previewPlugin().name}
                    </h3>
                    <p class="im:text-sm im:text-gray-500">Review settings before importing</p>
                  </div>
                </div>
                <button
                  type="button"
                  class="im:p-2 im:text-gray-400 hover:im:text-gray-600 im:rounded-full hover:im:bg-gray-100"
                  onClick={closeModal}
                >
                  <svg class="im:w-5 im:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              <Show when={loadingPreview()}>
                <div class="im:py-12 im:text-center">
                  <div class="im:animate-spin im:w-8 im:h-8 im:border-2 im:border-indigo-600 im:border-t-transparent im:rounded-full im:mx-auto" />
                  <p class="im:mt-3 im:text-sm im:text-gray-500">Loading settings...</p>
                </div>
              </Show>

              <Show when={!loadingPreview() && previewData()}>
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
                <div class="im:space-y-4">
                  <div class="im:bg-gray-50 im:rounded-lg im:p-4 im:space-y-3">
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
                </div>

                {/* Actions */}
                <div class="im:flex im:gap-3 im:mt-6">
                  <button
                    type="button"
                    class="im:flex-1 im:px-4 im:py-2.5 im:bg-indigo-600 im:text-white im:font-medium im:rounded-lg hover:im:bg-indigo-700 im:transition-colors disabled:im:opacity-50 disabled:im:cursor-not-allowed"
                    onClick={handleImport}
                    disabled={importing()}
                  >
                    {importing() ? 'Importing...' : 'Confirm Import'}
                  </button>
                  <button
                    type="button"
                    class="im:px-4 im:py-2.5 im:text-gray-700 im:font-medium im:rounded-lg hover:im:bg-gray-100 im:transition-colors"
                    onClick={closeModal}
                    disabled={importing()}
                  >
                    Cancel
                  </button>
                </div>
              </Show>
            </div>
          </div>
        </div>
      </Show>
    </>
  );
}
