import { createSignal, onMount, Show } from 'solid-js';
import { api } from '../api/client';
import { useSettings } from '../store/settings';
import LineChart from '../components/charts/LineChart';
import DonutChart from '../components/charts/DonutChart';
import BarChart from '../components/charts/BarChart';
import DateRangeSelect from '../components/DateRangeSelect';

export default function Overview(props) {
  const [stats, setStats] = createSignal(null);
  const [loading, setLoading] = createSignal(true);
  const [serverInfo, setServerInfo] = createSignal(null);
  const [analytics, setAnalytics] = createSignal(null);
  const [analyticsLoading, setAnalyticsLoading] = createSignal(true);
  const [analyticsPeriod, setAnalyticsPeriod] = createSignal(30);

  const settingsSignal = useSettings();
  const settings = () => settingsSignal() || {};

  const loadAnalytics = async (period) => {
    setAnalyticsLoading(true);
    try {
      const res = await api.getAnalytics({ period });
      setAnalytics(res.data);
    } catch (error) {
      console.error('Failed to load analytics:', error);
    } finally {
      setAnalyticsLoading(false);
    }
  };

  const handlePeriodChange = (period) => {
    setAnalyticsPeriod(period);
    loadAnalytics(period);
  };

  onMount(async () => {
    try {
      const [statsRes, serverRes] = await Promise.all([
        api.getStats(),
        api.getServerInfo(),
      ]);
      setStats(statsRes.data.stats);
      setServerInfo(serverRes.data);
    } catch (error) {
      console.error('Failed to load data:', error);
    } finally {
      setLoading(false);
    }
    loadAnalytics(analyticsPeriod());
  });

  const getProviderName = () => {
    const providers = {
      default: 'Default (PHP Mail)',
      smtp: 'SMTP',
      ses: 'Amazon SES',
      mailgun: 'Mailgun',
      sendgrid: 'SendGrid',
      brevo: 'Brevo',
      postmark: 'Postmark',
      cloudflare: 'Cloudflare Email Service',
    };
    return providers[settings().provider] || settings().provider || 'Not configured';
  };

  const getQueueRunnerName = () => {
    const runners = {
      wp_cron: 'WP Cron',
      external_cron: 'External Cron',
      action_scheduler: 'Action Scheduler',
    };
    return runners[settings().queue_runner] || 'WP Cron';
  };

  return (
    <div class="im:space-y-6">
      {/* Stats Cards */}
      <div class="im:grid im:grid-cols-4 im:gap-4">
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
          <div class="im:text-sm im:text-gray-500 im:mb-1">Total Emails</div>
          <div class="im:text-2xl im:font-semibold im:text-gray-900">
            {loading() ? '...' : stats()?.total || 0}
          </div>
        </div>
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
          <div class="im:text-sm im:text-gray-500 im:mb-1">Sent</div>
          <div class="im:text-2xl im:font-semibold im:text-green-600">
            {loading() ? '...' : stats()?.sent || 0}
          </div>
        </div>
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
          <div class="im:text-sm im:text-gray-500 im:mb-1">Pending</div>
          <div class="im:text-2xl im:font-semibold im:text-amber-600">
            {loading() ? '...' : stats()?.pending || 0}
          </div>
        </div>
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
          <div class="im:text-sm im:text-gray-500 im:mb-1">Failed</div>
          <div class="im:text-2xl im:font-semibold im:text-red-600">
            {loading() ? '...' : stats()?.failed || 0}
          </div>
        </div>
      </div>

      <div class="im:grid im:grid-cols-3 im:gap-6">
        {/* Provider Configuration */}
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5 im:relative">
          <button
            onClick={() => props.onSwitchTab('provider')}
            class="im:absolute im:top-3 im:right-3 im:p-1.5 im:text-gray-400 hover:im:text-gray-600 im:rounded-md hover:im:bg-gray-100 im:transition-colors"
            title="Edit provider settings"
          >
            <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
          </button>
          <h3 class="im:text-sm im:font-semibold im:text-gray-900 im:mb-4">Email Provider</h3>
          <div class="im:space-y-3">
            <div class="im:flex im:items-center im:justify-between">
              <span class="im:text-sm im:text-gray-500">Provider</span>
              <span class="im:text-sm im:font-medium im:text-gray-900">{getProviderName()}</span>
            </div>
            <div class="im:flex im:items-center im:justify-between">
              <span class="im:text-sm im:text-gray-500">From Name</span>
              <div class="im:flex im:items-center im:gap-2">
                <span class="im:text-sm im:font-medium im:text-gray-900">{settings().from_name || 'Not set'}</span>
                <Show when={settings().force_from}>
                  <span class="im:px-1.5 im:py-0.5 im:text-xs im:rounded im:bg-blue-50 im:text-blue-600 im:border im:border-blue-200">forced</span>
                </Show>
              </div>
            </div>
            <div class="im:flex im:items-center im:justify-between">
              <span class="im:text-sm im:text-gray-500">From Email</span>
              <div class="im:flex im:items-center im:gap-2">
                <span class="im:text-sm im:font-medium im:text-gray-900">{settings().from_email || 'Not set'}</span>
                <Show when={settings().force_from}>
                  <span class="im:px-1.5 im:py-0.5 im:text-xs im:rounded im:bg-blue-50 im:text-blue-600 im:border im:border-blue-200">forced</span>
                </Show>
              </div>
            </div>
          </div>
        </div>

        {/* Queue Settings */}
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5 im:relative">
          <button
            onClick={() => props.onSwitchTab('advanced')}
            class="im:absolute im:top-3 im:right-3 im:p-1.5 im:text-gray-400 hover:im:text-gray-600 im:rounded-md hover:im:bg-gray-100 im:transition-colors"
            title="Edit advanced settings"
          >
            <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
            </svg>
          </button>
          <h3 class="im:text-sm im:font-semibold im:text-gray-900 im:mb-4">Queue Settings</h3>
          <div class="im:space-y-3">
            <div class="im:flex im:items-center im:justify-between">
              <span class="im:text-sm im:text-gray-500">Send Mode</span>
              <span class="im:text-sm im:font-medium im:text-gray-900 im:capitalize">{settings().send_mode || 'queue'}</span>
            </div>
            <Show when={settings().send_mode !== 'direct'}>
              <div class="im:flex im:items-center im:justify-between">
                <span class="im:text-sm im:text-gray-500">Queue Runner</span>
                <span class="im:text-sm im:font-medium im:text-gray-900">{getQueueRunnerName()}</span>
              </div>
              <div class="im:flex im:items-center im:justify-between">
                <span class="im:text-sm im:text-gray-500">Batch Size</span>
                <span class="im:text-sm im:font-medium im:text-gray-900">{settings().batch_size || 10} emails</span>
              </div>
              <div class="im:flex im:items-center im:justify-between">
                <span class="im:text-sm im:text-gray-500">Rate Limit</span>
                <span class="im:text-sm im:font-medium im:text-gray-900">{settings().rate_limit || 100} / hour</span>
              </div>
            </Show>
            <Show when={settings().send_mode === 'direct'}>
              <p class="im:text-sm im:text-gray-500">Emails are sent immediately without queuing.</p>
            </Show>
            <Show when={settings().pause_sending}>
              <div class="im:flex im:items-center im:gap-2 im:px-2 im:py-1.5 im:bg-red-50 im:rounded-md im:border im:border-red-200">
                <svg class="im:w-4 im:h-4 im:text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="im:text-xs im:text-red-700 im:font-medium">Email Sending Paused</span>
              </div>
            </Show>
          </div>
        </div>

        {/* Server Capabilities */}
        <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5">
          <h3 class="im:text-sm im:font-semibold im:text-gray-900 im:mb-4">Server Capabilities</h3>
          <Show when={!loading()} fallback={<div class="im:text-sm im:text-gray-500">Loading...</div>}>
            <div class="im:space-y-3">
              <div class="im:flex im:items-center im:justify-between">
                <span class="im:text-sm im:text-gray-500">PHP mail()</span>
                <Show when={serverInfo()?.mail_enabled} fallback={
                  <span class="im:px-2 im:py-0.5 im:text-xs im:rounded-full im:bg-red-50 im:text-red-700 im:border im:border-red-200">Disabled</span>
                }>
                  <span class="im:px-2 im:py-0.5 im:text-xs im:rounded-full im:bg-green-50 im:text-green-700 im:border im:border-green-200">Enabled</span>
                </Show>
              </div>
              <div class="im:flex im:items-center im:justify-between">
                <span class="im:text-sm im:text-gray-500">Sendmail Path</span>
                <span class="im:text-sm im:font-medium im:text-gray-900 im:truncate im:max-w-32" title={serverInfo()?.sendmail_path || 'Not configured'}>
                  {serverInfo()?.sendmail_path || 'Not configured'}
                </span>
              </div>
              <div class="im:flex im:items-center im:justify-between">
                <span class="im:text-sm im:text-gray-500">PHP Version</span>
                <span class="im:text-sm im:font-medium im:text-gray-900">{serverInfo()?.php_version || '-'}</span>
              </div>
            </div>
          </Show>
        </div>
      </div>

      {/* Analytics Section */}
      <div class="im:bg-white im:rounded-lg im:border im:border-gray-200 im:p-5 im:overflow-hidden">
        <div class="im:flex im:justify-between im:mb-6 ">
          <h3 class="im:text-sm im:font-semibold im:text-gray-900 im:p-0">Email Analytics</h3>
          <DateRangeSelect value={analyticsPeriod()} onChange={handlePeriodChange} />
        </div>

        <Show when={!analyticsLoading()} fallback={
          <div class="im:flex im:items-center im:justify-center im:py-12">
            <div class="im:text-sm im:text-gray-500">Loading analytics...</div>
          </div>
        }>
          <Show when={analytics()}>
            {/* Summary Cards */}
            <div class="im:grid im:grid-cols-4 im:gap-4 im:mb-6">
              <div class="im:bg-gray-50 im:rounded-lg im:p-4">
                <div class="im:text-xs im:text-gray-500 im:mb-1">Delivery Rate</div>
                <div class="im:flex im:items-baseline im:gap-2">
                  <span class="im:text-xl im:font-semibold im:text-gray-900">
                    {analytics()?.summary?.delivery_rate || 0}%
                  </span>
                  <Show when={analytics()?.comparison?.delivery_rate_change !== 0}>
                    <span class={`im:text-xs im:font-medium ${analytics()?.comparison?.delivery_rate_change > 0 ? 'im:text-green-600' : 'im:text-red-600'}`}>
                      {analytics()?.comparison?.delivery_rate_change > 0 ? '+' : ''}{analytics()?.comparison?.delivery_rate_change}%
                    </span>
                  </Show>
                </div>
              </div>
              <div class="im:bg-gray-50 im:rounded-lg im:p-4">
                <div class="im:text-xs im:text-gray-500 im:mb-1">Bounce Rate</div>
                <div class="im:text-xl im:font-semibold im:text-gray-900">
                  {analytics()?.summary?.bounce_rate || 0}%
                </div>
              </div>
              <div class="im:bg-gray-50 im:rounded-lg im:p-4">
                <div class="im:text-xs im:text-gray-500 im:mb-1">Complaint Rate</div>
                <div class="im:text-xl im:font-semibold im:text-gray-900">
                  {analytics()?.summary?.complaint_rate || 0}%
                </div>
              </div>
              <div class="im:bg-gray-50 im:rounded-lg im:p-4">
                <div class="im:text-xs im:text-gray-500 im:mb-1">Volume Change</div>
                <div class="im:flex im:items-baseline im:gap-2">
                  <span class="im:text-xl im:font-semibold im:text-gray-900">
                    {analytics()?.comparison?.volume_change > 0 ? '+' : ''}{analytics()?.comparison?.volume_change || 0}%
                  </span>
                </div>
              </div>
            </div>

            {/* Charts */}
            <div class="im:grid im:grid-cols-3 im:gap-6">
              {/* Line Chart - Daily trend */}
              <div class="im:col-span-2">
                <h4 class="im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wide im:mb-4">Daily Email Volume</h4>
                <Show when={analytics()?.daily?.length > 0} fallback={
                  <div class="im:flex im:items-center im:justify-center im:h-48 im:text-sm im:text-gray-400">
                    No data for this period
                  </div>
                }>
                  <LineChart data={analytics()?.daily} lines={['sent', 'failed', 'bounced']} height={200} />
                </Show>
              </div>

              {/* Donut Chart - Status breakdown */}
              <div>
                <h4 class="im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wide im:mb-4">Status Breakdown</h4>
                <DonutChart
                  data={analytics()?.by_status}
                  size={160}
                  onSegmentClick={(status) => props.onSwitchTab('logs', { status })}
                />
              </div>
            </div>

            {/* Provider Performance */}
            <Show when={analytics()?.by_provider?.length > 0}>
              <div class="im:mt-6 im:pt-6 im:border-t im:border-gray-100">
                <h4 class="im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wide im:mb-4">Provider Performance</h4>
                <BarChart data={analytics()?.by_provider} />
              </div>
            </Show>
          </Show>
        </Show>
      </div>
    </div>
  );
}
