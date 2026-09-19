import { createSignal, onMount, Show, For } from 'solid-js';
import { api } from '../api/client';
import { toast } from '../components/Toast';
import SearchableSelect from '../components/SearchableSelect';

// Providers can report non-fatal issues alongside a successful send, e.g.
// Cloudflare stripping headers it does not allow.
const providerWarnings = (email) => {
  if (!email?.provider_response) return [];

  try {
    const parsed = typeof email.provider_response === 'string'
      ? JSON.parse(email.provider_response)
      : email.provider_response;
    return Array.isArray(parsed?.warnings) ? parsed.warnings : [];
  } catch {
    return [];
  }
};

export default function Emails() {
  const [emails, setEmails] = createSignal([]);
  const [loading, setLoading] = createSignal(true);
  const [pagination, setPagination] = createSignal({ page: 1, per_page: 20, total: 0 });
  const [filters, setFilters] = createSignal({ status: '', search: '' });
  const [searchTimeout, setSearchTimeout] = createSignal(null);
  const [selectedEmail, setSelectedEmail] = createSignal(null);
  const [showClearConfirm, setShowClearConfirm] = createSignal(false);
  const [clearing, setClearing] = createSignal(false);
  const [stats, setStats] = createSignal({ sent: 0, failed: 0, bounced: 0, paused: 0, total: 0 });
  const [showActionsMenu, setShowActionsMenu] = createSignal(false);
  const [actionsAnimating, setActionsAnimating] = createSignal(false);

  const openActionsMenu = () => {
    setShowActionsMenu(true);
    setTimeout(() => setActionsAnimating(true), 10);
  };

  const closeActionsMenu = () => {
    setActionsAnimating(false);
    setTimeout(() => setShowActionsMenu(false), 150);
  };
  const [activeTab, setActiveTab] = createSignal('body');

  const loadEmails = async () => {
    setLoading(true);
    try {
      const params = {
        page: pagination().page,
        per_page: pagination().per_page,
        ...filters(),
      };

      const response = await api.getEmails(params);
      setEmails(response.data.emails);
      setPagination({
        page: response.data.page,
        per_page: response.data.per_page,
        total: response.data.total,
        total_pages: response.data.total_pages,
      });
    } catch (error) {
      console.error('Failed to load emails:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadStats = async () => {
    try {
      const response = await api.getStats();
      const s = response.data.stats;
      setStats({
        sent: s.sent || 0,
        failed: s.failed || 0,
        bounced: s.bounced || 0,
        paused: s.paused || 0,
        total: s.total || 0,
      });
    } catch (error) {
      console.error('Failed to load stats:', error);
    }
  };

  const updateEmailInList = (id, newStatus) => {
    setEmails(emails().map(email =>
      email.id === id ? { ...email, status: newStatus } : email
    ));
    if (selectedEmail()?.id === id) {
      setSelectedEmail({ ...selectedEmail(), status: newStatus });
    }
  };

  onMount(() => {
    loadEmails();
    loadStats();
  });

  const handleResend = async (id) => {
    try {
      await api.resendEmail(id);
      toast.success('Email resent');
      updateEmailInList(id, 'sent');
      loadEmails();
      loadStats();
    } catch (error) {
      updateEmailInList(id, 'failed');
      toast.error('Failed to resend: ' + error.message);
      loadEmails();
      loadStats();
    }
  };

  const handleRefresh = () => {
    loadEmails();
    loadStats();
  };

  const handleClearLogs = async () => {
    setClearing(true);
    try {
      await api.clearLogs();
      toast.success('All logs cleared');
      setShowClearConfirm(false);
      loadEmails();
      loadStats();
    } catch (error) {
      toast.error('Failed to clear logs: ' + error.message);
    } finally {
      setClearing(false);
    }
  };

  const handleStatusFilter = (status) => {
    setFilters({ ...filters(), status });
    setPagination({ ...pagination(), page: 1 });
    loadEmails();
  };

  const getStatusOptions = () => [
    { value: '', label: `All Status (${stats().total})` },
    { value: 'sent', label: `Sent (${stats().sent || 0})` },
    { value: 'failed', label: `Failed (${stats().failed || 0})` },
    { value: 'bounced', label: `Bounced (${stats().bounced || 0})` },
    { value: 'paused', label: `Paused (${stats().paused || 0})` },
  ];

  const handleSearchChange = (value) => {
    setFilters({ ...filters(), search: value });
    if (searchTimeout()) clearTimeout(searchTimeout());
    setSearchTimeout(setTimeout(() => {
      setPagination({ ...pagination(), page: 1 });
      loadEmails();
    }, 300));
  };

  const handlePageChange = (page) => {
    setPagination({ ...pagination(), page });
    loadEmails();
  };

  const getPageNumbers = () => {
    const total = pagination().total_pages;
    const current = pagination().page;
    const pages = [];

    if (total <= 7) {
      for (let i = 1; i <= total; i++) pages.push(i);
    } else {
      pages.push(1);
      if (current > 3) pages.push('...');
      for (let i = Math.max(2, current - 1); i <= Math.min(total - 1, current + 1); i++) {
        pages.push(i);
      }
      if (current < total - 2) pages.push('...');
      pages.push(total);
    }

    return pages;
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'sent':
        return 'im:bg-green-50 im:text-green-700 im:border-green-200';
      case 'failed':
        return 'im:bg-red-50 im:text-red-700 im:border-red-200';
      case 'bounced':
      case 'complained':
        return 'im:bg-amber-50 im:text-amber-700 im:border-amber-200';
      case 'sending':
        return 'im:bg-blue-50 im:text-blue-700 im:border-blue-200';
      case 'paused':
        return 'im:bg-purple-50 im:text-purple-700 im:border-purple-200';
      default:
        return 'im:bg-gray-50 im:text-gray-700 im:border-gray-200';
    }
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
  };

  const formatFullDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  return (
    <div class="im:space-y-4">
      {/* Search & Filters */}
      <div class="im:flex im:items-center im:justify-between im:gap-4">
        <div class="im:flex im:items-center im:gap-3">
          <div class="im:relative">
            <svg class="im:absolute im:left-3 im:top-1/2 im:-translate-y-1/2 im:w-4 im:h-4 im:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              type="text"
              placeholder="Search by email or subject..."
              class="im:w-64 im:pl-9 im:pr-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:outline-none im:bg-white focus:im:ring-2 focus:im:ring-gray-900 focus:im:border-transparent"
              value={filters().search}
              onInput={(e) => handleSearchChange(e.target.value)}
            />
          </div>
          <div class="im:w-40">
            <SearchableSelect
              options={getStatusOptions()}
              value={filters().status}
              onChange={(value) => handleStatusFilter(value)}
              placeholder="Status"
            />
          </div>
        </div>
        <div class="im:relative">
          <button
            class="im:flex im:items-center im:gap-2 im:px-3 im:py-2 im:bg-gray-100 im:text-gray-700 im:rounded-md im:text-sm im:font-medium hover:im:bg-gray-200 im:transition-colors"
            onClick={() => showActionsMenu() ? closeActionsMenu() : openActionsMenu()}
          >
            Actions
            <svg class={`im:w-4 im:h-4 im:transition-transform im:duration-150 ${showActionsMenu() ? 'im:rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <Show when={showActionsMenu()}>
            <div
              class="im:fixed im:inset-0 im:z-10"
              onClick={closeActionsMenu}
            />
            <div class={`im-actions-dropdown im:absolute im:right-0 im:mt-1 im:w-44 im:bg-white im:rounded-md im:shadow-lg im:border im:border-gray-200 im:z-20 im:py-1 im:transform im:transition-all im:duration-150 im:ease-out im:origin-top-right ${actionsAnimating() ? 'im:opacity-100 im:scale-y-100' : 'im:opacity-0 im:scale-y-95'}`}>
              <button
                type="button"
                class="im:w-full im:flex im:items-center im:gap-2 im:px-4 im:py-2 im:text-sm im:text-gray-700 hover:im:bg-gray-50 im:transition-colors im:duration-75 im:text-left"
                onClick={() => {
                  handleRefresh();
                  closeActionsMenu();
                }}
              >
                <svg class="im:w-4 im:h-4 im:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
              </button>
              <button
                type="button"
                class="im:w-full im:flex im:items-center im:gap-2 im:px-4 im:py-2 im:text-sm im:text-gray-700 hover:im:bg-gray-50 im:transition-colors im:duration-75 im:text-left"
                onClick={() => {
                  api.exportCsv(filters().status);
                  closeActionsMenu();
                }}
              >
                <svg class="im:w-4 im:h-4 im:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Export CSV
              </button>
              <div class="im:border-t im:border-gray-100 im:my-1" />
              <button
                type="button"
                class="im-actions-clear im:w-full im:flex im:items-center im:gap-2 im:px-4 im:py-2 im:text-sm im:text-red-600 hover:im:bg-red-50 im:transition-colors im:duration-75 im:text-left"
                onClick={() => {
                  setShowClearConfirm(true);
                  closeActionsMenu();
                }}
              >
                <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                Clear Logs
              </button>
            </div>
          </Show>
        </div>
      </div>

      <div class="im:bg-white im:rounded-lg im:border im:border-gray-200">

        {/* Loading Skeleton */}
        <Show when={loading()}>
          <div class="im:overflow-x-auto">
            <table class="im:w-full">
              <thead>
                <tr class="im:border-b im:border-gray-200">
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">ID</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Recipient</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Subject</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Status</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Date</th>
                  <th class="im:px-5 im:py-3 im:text-right im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="im:divide-y im:divide-gray-100">
                <For each={[1, 2, 3, 4, 5]}>
                  {() => (
                    <tr>
                      <td class="im:px-5 im:py-4">
                        <div class="im:h-4 im:w-8 im:bg-gray-200 im:rounded im:animate-pulse" />
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:h-4 im:w-40 im:bg-gray-200 im:rounded im:animate-pulse" />
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:h-4 im:w-48 im:bg-gray-200 im:rounded im:animate-pulse" />
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:h-4 im:w-12 im:bg-gray-200 im:rounded im:animate-pulse" />
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:h-6 im:w-16 im:bg-gray-200 im:rounded-md im:animate-pulse" />
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:h-4 im:w-24 im:bg-gray-200 im:rounded im:animate-pulse" />
                      </td>
                      <td class="im:px-5 im:py-4 im:text-right">
                        <div class="im:h-6 im:w-14 im:bg-gray-200 im:rounded im:animate-pulse im:ml-auto" />
                      </td>
                    </tr>
                  )}
                </For>
              </tbody>
            </table>
          </div>
        </Show>

        {/* Table */}
        <Show when={!loading()}>
          <div class="im:overflow-x-auto">
            <table class="im:w-full">
              <thead>
                <tr class="im:border-b im:border-gray-200">
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">ID</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Recipient</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Subject</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Mode</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Status</th>
                  <th class="im:px-5 im:py-3 im:text-left im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Date</th>
                  <th class="im:px-5 im:py-3 im:text-right im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="im:divide-y im:divide-gray-100">
                <Show when={emails().length === 0}>
                  <tr>
                    <td colspan="6" class="im:px-5 im:py-12 im:text-center im:text-gray-500 im:text-sm">
                      No emails found
                    </td>
                  </tr>
                </Show>
                <For each={emails()}>
                  {(email) => (
                    <tr
                      class="im:cursor-pointer hover:im:bg-gray-50 im:transition-colors im:duration-75"
                      onClick={() => setSelectedEmail(email)}
                    >
                      <td class="im:px-5 im:py-4">
                        <div class="im:text-sm im:text-gray-500">{email.id}</div>
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:text-sm im:font-medium im:text-gray-900">{email.to_email}</div>
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:text-sm im:text-gray-900 im:max-w-xs im:truncate">{email.subject}</div>
                      </td>
                      <td class="im:px-5 im:py-4">
                        <span class={`im:inline-flex im:items-center im:px-2 im:py-1 im:rounded-md im:text-xs im:font-medium im:border ${getStatusBadge(email.status)}`}>
                          {email.status}
                        </span>
                      </td>
                      <td class="im:px-5 im:py-4">
                        <div class="im:text-sm im:text-gray-500">{formatDate(email.created_at)}</div>
                      </td>
                      <td class="im:px-5 im:py-4 im:text-right" onClick={(e) => e.stopPropagation()}>
                        <div class="im:flex im:items-center im:justify-end im:gap-1">
                          <button
                            class="im:inline-flex im:items-center im:gap-1 im:px-2 im:py-1 im:text-xs im:text-gray-500 hover:im:text-gray-700 hover:im:bg-gray-100 im:rounded im:transition-colors"
                            onClick={() => setSelectedEmail(email)}
                          >
                            <svg class="im:w-3.5 im:h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            View
                          </button>
                          <Show when={email.status === 'failed' || email.status === 'paused'}>
                            <button
                              class="im:inline-flex im:items-center im:gap-1 im:px-2 im:py-1 im:text-xs im:text-gray-500 hover:im:text-gray-700 hover:im:bg-gray-100 im:rounded im:transition-colors"
                              onClick={() => handleResend(email.id)}
                            >
                              <svg class="im:w-3.5 im:h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                              </svg>
                              Resend
                            </button>
                          </Show>
                        </div>
                      </td>
                    </tr>
                  )}
                </For>
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          <Show when={pagination().total_pages > 1}>
            <div class="im:flex im:items-center im:justify-between im:px-5 im:py-4 im:border-t im:border-gray-200">
              <div class="im:text-sm im:text-gray-500">
                Showing {((pagination().page - 1) * pagination().per_page) + 1}-{Math.min(pagination().page * pagination().per_page, pagination().total)} of {pagination().total}
              </div>
              <div class="im:flex im:items-center im:gap-1">
                <button
                  class="im:p-1.5 im:text-gray-500 im:rounded hover:im:bg-gray-100 disabled:im:opacity-30 disabled:im:cursor-not-allowed im:transition-colors"
                  disabled={pagination().page === 1}
                  onClick={() => handlePageChange(pagination().page - 1)}
                >
                  <svg class="im:w-5 im:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                  </svg>
                </button>
                <For each={getPageNumbers()}>
                  {(page) => (
                    <Show when={page === '...'} fallback={
                      <button
                        class={`im:min-w-[32px] im:h-8 im:px-2 im:text-sm im:rounded im:transition-colors ${
                          pagination().page === page
                            ? 'im:bg-gray-900 im:text-white'
                            : 'im:text-gray-600 hover:im:bg-gray-100'
                        }`}
                        onClick={() => handlePageChange(page)}
                      >
                        {page}
                      </button>
                    }>
                      <span class="im:px-1 im:text-gray-400">...</span>
                    </Show>
                  )}
                </For>
                <button
                  class="im:p-1.5 im:text-gray-500 im:rounded hover:im:bg-gray-100 disabled:im:opacity-30 disabled:im:cursor-not-allowed im:transition-colors"
                  disabled={pagination().page === pagination().total_pages}
                  onClick={() => handlePageChange(pagination().page + 1)}
                >
                  <svg class="im:w-5 im:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                  </svg>
                </button>
              </div>
            </div>
          </Show>
        </Show>
      </div>

      {/* Email Details Modal */}
      <Show when={selectedEmail()}>
        <div class="im:fixed im:inset-0 im:z-50 im:overflow-y-auto">
          <div class="im:flex im:min-h-full im:items-center im:justify-center im:p-4">
            <div
              class="im:fixed im:inset-0 im:bg-black/50 im:transition-opacity"
              onClick={() => setSelectedEmail(null)}
            />
            <div class="im:relative im:bg-white im:rounded-lg im:shadow-xl im:w-full im:max-w-2xl im:max-h-[90vh] im:overflow-hidden">
              {/* Modal Header */}
              <div class="im:flex im:items-center im:justify-between im:px-6 im:py-4 im:border-b im:border-gray-200">
                <div>
                  <h3 class="im:text-base im:font-semibold im:text-gray-900">Email Details</h3>
                  <p class="im:text-xs im:text-gray-500 im:mt-0.5">
                    ID: {selectedEmail().id} · Created: {formatDate(selectedEmail().created_at)}
                    <Show when={selectedEmail().sent_at}> · Sent: {formatDate(selectedEmail().sent_at)}</Show>
                  </p>
                </div>
                <button
                  class="im:p-2 im:text-gray-400 hover:im:text-gray-600 im:transition-colors"
                  onClick={() => setSelectedEmail(null)}
                >
                  <svg class="im:w-5 im:h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              {/* Status Bar */}
              <div class="im:flex im:items-center im:gap-2 im:flex-wrap im:px-6 im:py-3 im:bg-gray-50 im:border-b im:border-gray-200">
                <span class={`im:inline-flex im:items-center im:px-2.5 im:py-1 im:rounded-md im:text-xs im:font-medium im:border ${getStatusBadge(selectedEmail().status)}`}>
                  {selectedEmail().status}
                </span>
                <Show when={selectedEmail().provider}>
                  <span class="im:text-xs im:text-gray-500 im:px-2 im:py-1 im:bg-white im:border im:border-gray-200 im:rounded-md">
                    {selectedEmail().provider}
                  </span>
                </Show>
                <Show when={selectedEmail().status === 'failed' || selectedEmail().status === 'paused'}>
                  <button
                    class="im:ml-auto im:px-3 im:py-1 im:text-xs im:font-medium im:text-white im:bg-red-600 im:rounded hover:im:bg-red-700 im:transition-colors"
                    onClick={() => handleResend(selectedEmail().id)}
                  >
                    Resend
                  </button>
                </Show>
              </div>

              {/* Error Message */}
              <Show when={selectedEmail().error_message}>
                <div class="im:mx-6 im:mt-4 im:text-sm im:text-red-700 im:bg-red-50 im:border im:border-red-200 im:rounded-md im:p-3">
                  <span class="im:font-medium">Error:</span> {selectedEmail().error_message}
                </div>
              </Show>

              {/* Tabs */}
              <div class="im:border-b im:border-gray-200 im:px-6">
                <nav class="im:flex im:gap-6 im:-mb-px">
                  <button
                    class={`im:py-3 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${activeTab() === 'body' ? 'im:border-gray-900 im:text-gray-900' : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'}`}
                    onClick={() => setActiveTab('body')}
                  >
                    Body
                  </button>
                  <button
                    class={`im:py-3 im:text-sm im:font-medium im:border-b-2 im:transition-colors ${activeTab() === 'details' ? 'im:border-gray-900 im:text-gray-900' : 'im:border-transparent im:text-gray-500 hover:im:text-gray-700 hover:im:border-gray-300'}`}
                    onClick={() => setActiveTab('details')}
                  >
                    Details
                  </button>
                </nav>
              </div>

              {/* Tab Content */}
              <div class="im:px-6 im:py-5 im:overflow-y-auto im:max-h-[calc(90vh-220px)]">
                {/* Body Tab */}
                <Show when={activeTab() === 'body'}>
                  <div class="im:space-y-4">
                    <Show when={selectedEmail().body_html}>
                      <div
                        class="im:text-sm im:text-gray-700 im:bg-gray-50 im:border im:border-gray-200 im:rounded-md im:p-3 im:max-h-96 im:overflow-y-auto"
                        innerHTML={selectedEmail().body_html}
                      />
                    </Show>
                    <Show when={!selectedEmail().body_html}>
                      <div class="im:text-sm im:text-gray-500 im:text-center im:py-8">No HTML body available</div>
                    </Show>
                    <Show when={selectedEmail().attachments}>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Attachments</label>
                        <pre class="im:text-xs im:text-gray-700 im:bg-gray-50 im:border im:border-gray-200 im:rounded-md im:p-3 im:overflow-x-auto im:whitespace-pre-wrap">{typeof selectedEmail().attachments === 'string' ? selectedEmail().attachments : JSON.stringify(selectedEmail().attachments, null, 2)}</pre>
                      </div>
                    </Show>
                  </div>
                </Show>

                {/* Details Tab */}
                <Show when={activeTab() === 'details'}>
                  <div class="im:space-y-4">
                    <div>
                      <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Subject</label>
                      <div class="im:text-sm im:text-gray-900">{selectedEmail().subject || '-'}</div>
                    </div>
                    <div class="im:grid im:grid-cols-3 im:gap-4">
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">ID</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().id}</div>
                      </div>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Provider</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().provider || '-'}</div>
                      </div>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Status</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().status}</div>
                      </div>
                    </div>
                    <div class="im:grid im:grid-cols-2 im:gap-4">
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">To Email</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().to_email || '-'}</div>
                      </div>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">To Name</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().to_name || '-'}</div>
                      </div>
                    </div>
                    <div class="im:grid im:grid-cols-2 im:gap-4">
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">From Email</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().from_email || '-'}</div>
                      </div>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">From Name</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().from_name || '-'}</div>
                      </div>
                    </div>
                    <Show when={selectedEmail().reply_to}>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Reply To</label>
                        <div class="im:text-sm im:text-gray-900">{selectedEmail().reply_to}</div>
                      </div>
                    </Show>
                    <div class="im:grid im:grid-cols-2 im:gap-4">
                      <Show when={selectedEmail().provider}>
                        <div>
                          <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Provider</label>
                          <div class="im:text-sm im:text-gray-900">{selectedEmail().provider}</div>
                        </div>
                      </Show>
                      <Show when={selectedEmail().message_id}>
                        <div>
                          <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Message ID</label>
                          <div class="im:text-sm im:text-gray-900 im:font-mono im:break-all im:text-xs">{selectedEmail().message_id}</div>
                        </div>
                      </Show>
                    </div>
                    <div class="im:grid im:grid-cols-2 im:gap-4">
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Created At</label>
                        <div class="im:text-sm im:text-gray-900">{formatFullDate(selectedEmail().created_at)}</div>
                      </div>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Updated At</label>
                        <div class="im:text-sm im:text-gray-900">{formatFullDate(selectedEmail().updated_at)}</div>
                      </div>
                    </div>
                    <Show when={selectedEmail().sent_at || selectedEmail().scheduled_at}>
                      <div class="im:grid im:grid-cols-2 im:gap-4">
                        <Show when={selectedEmail().sent_at}>
                          <div>
                            <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Sent At</label>
                            <div class="im:text-sm im:text-gray-900">{formatFullDate(selectedEmail().sent_at)}</div>
                          </div>
                        </Show>
                        <Show when={selectedEmail().scheduled_at}>
                          <div>
                            <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Scheduled At</label>
                            <div class="im:text-sm im:text-gray-900">{formatFullDate(selectedEmail().scheduled_at)}</div>
                          </div>
                        </Show>
                      </div>
                    </Show>
                    <Show when={selectedEmail().headers}>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Headers</label>
                        <pre class="im:text-xs im:text-gray-700 im:bg-gray-50 im:border im:border-gray-200 im:rounded-md im:p-3 im:overflow-x-auto im:whitespace-pre-wrap im:max-h-32 im:overflow-y-auto">{typeof selectedEmail().headers === 'string' ? selectedEmail().headers : JSON.stringify(JSON.parse(selectedEmail().headers || '{}'), null, 2)}</pre>
                      </div>
                    </Show>
                    <Show when={providerWarnings(selectedEmail()).length > 0}>
                      <div class="im:p-3 im:bg-amber-50 im:border im:border-amber-200 im:rounded-md im:flex im:items-start im:gap-3">
                        <svg class="im:w-5 im:h-5 im:text-amber-500 im:shrink-0 im:mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <div>
                          <p class="im:text-sm im:font-medium im:text-amber-800">Delivered with warnings</p>
                          <For each={providerWarnings(selectedEmail())}>
                            {(warning) => <p class="im:text-sm im:text-amber-700">{warning}</p>}
                          </For>
                        </div>
                      </div>
                    </Show>
                    <Show when={selectedEmail().provider_response}>
                      <div>
                        <label class="im:block im:text-xs im:font-medium im:text-gray-500 im:uppercase im:tracking-wider im:mb-1">Provider Response</label>
                        <pre class="im:text-xs im:text-gray-700 im:bg-gray-50 im:border im:border-gray-200 im:rounded-md im:p-3 im:overflow-x-auto im:whitespace-pre-wrap im:max-h-24 im:overflow-y-auto">{typeof selectedEmail().provider_response === 'string' ? selectedEmail().provider_response : JSON.stringify(selectedEmail().provider_response, null, 2)}</pre>
                      </div>
                    </Show>
                  </div>
                </Show>
              </div>

              {/* Modal Footer */}
              <div class="im:flex im:items-center im:justify-end im:gap-3 im:px-6 im:py-4 im:border-t im:border-gray-200 im:bg-gray-50">
                <Show when={selectedEmail().status === 'failed' || selectedEmail().status === 'paused'}>
                  <button
                    class="im:px-4 im:py-2 im:text-sm im:font-medium im:text-gray-700 im:bg-white im:border im:border-gray-300 im:rounded-md hover:im:bg-gray-50 im:transition-colors"
                    onClick={() => {
                      handleResend(selectedEmail().id);
                      setSelectedEmail(null);
                    }}
                  >
                    Resend
                  </button>
                </Show>
                <button
                  class="im:px-4 im:py-2 im:text-sm im:font-medium im:text-white im:bg-gray-900 im:rounded-md hover:im:bg-gray-800 im:transition-colors"
                  onClick={() => setSelectedEmail(null)}
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      </Show>

      {/* Clear Logs Confirmation Modal */}
      <Show when={showClearConfirm()}>
        <div class="im:fixed im:inset-0 im:z-50 im:overflow-y-auto">
          <div class="im:flex im:min-h-full im:items-center im:justify-center im:p-4">
            <div
              class="im:fixed im:inset-0 im:bg-black/50 im:transition-opacity"
              onClick={() => setShowClearConfirm(false)}
            />
            <div class="im:relative im:bg-white im:rounded-lg im:shadow-xl im:w-full im:max-w-md im:p-6">
              <div class="im:flex im:items-start im:gap-4">
                <div class="im:flex im:items-center im:justify-center im:w-10 im:h-10 im:rounded-full im:bg-red-100 im:shrink-0">
                  <svg class="im:w-5 im:h-5 im:text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <div>
                  <h3 class="im:text-base im:font-semibold im:text-gray-900">Clear all logs?</h3>
                  <p class="im:text-sm im:text-gray-500 im:mt-2">
                    This will permanently delete all {pagination().total} email logs. This action cannot be undone.
                  </p>
                </div>
              </div>
              <div class="im:flex im:items-center im:justify-end im:gap-3 im:mt-6">
                <button
                  class="im:px-4 im:py-2 im:text-sm im:font-medium im:text-gray-700 im:bg-white im:border im:border-gray-300 im:rounded-md hover:im:bg-gray-50 im:transition-colors"
                  onClick={() => setShowClearConfirm(false)}
                  disabled={clearing()}
                >
                  Cancel
                </button>
                <button
                  class="im:px-4 im:py-2 im:text-sm im:font-medium im:text-white im:bg-red-600 im:rounded-md hover:im:bg-red-700 im:transition-colors disabled:im:opacity-50"
                  onClick={handleClearLogs}
                  disabled={clearing()}
                >
                  {clearing() ? 'Clearing...' : 'Clear All'}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Show>
    </div>
  );
}
