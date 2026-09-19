const API_BASE = window.insaneMailerAdmin?.restUrl || '/wp-json/insane-mailer/v1';
const NONCE = window.insaneMailerAdmin?.nonce || '';

const request = async (endpoint, options = {}) => {
  const url = `${API_BASE}${endpoint}`;

  const headers = {
    'Content-Type': 'application/json',
    'X-WP-Nonce': NONCE,
    ...options.headers,
  };

  try {
    const response = await fetch(url, {
      ...options,
      headers,
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || data.message || 'Request failed');
    }

    return data;
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
};

export const api = {
  getSettings: () => request('/settings'),

  updateSettings: (settings) => request('/settings', {
    method: 'POST',
    body: JSON.stringify(settings),
  }),

  testConnection: (provider, credentials) => request('/settings/test-connection', {
    method: 'POST',
    body: JSON.stringify({ provider, credentials }),
  }),

  sendTestEmail: (to, subject, message) => request('/test-email', {
    method: 'POST',
    body: JSON.stringify({ to, subject, message }),
  }),

  getEmails: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return request(`/emails${query ? `?${query}` : ''}`);
  },

  getEmail: (id) => request(`/emails/${id}`),

  resendEmail: (id) => request(`/emails/${id}/resend`, {
    method: 'POST',
  }),

  deleteEmail: (id) => request(`/emails/${id}`, {
    method: 'DELETE',
  }),

  clearLogs: () => request('/emails', {
    method: 'DELETE',
  }),

  getStats: () => request('/stats'),

  getAnalytics: (params = {}) => {
    const query = new URLSearchParams(params).toString();
    return request(`/stats/analytics${query ? `?${query}` : ''}`);
  },

  exportCsv: (status = '') => {
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    params.append('_wpnonce', NONCE);
    window.location.href = `${API_BASE}/export?${params.toString()}`;
  },

  getServerInfo: () => request('/server-info'),

  checkConstants: () => request('/settings/check-constants'),

  getConnectionStatus: () => request('/settings/connection-status'),

  // Migration
  detectMigrations: () => request('/migration/detect'),

  previewMigration: (slug) => request(`/migration/preview/${slug}`),

  importMigration: (slug) => request('/migration/import', {
    method: 'POST',
    body: JSON.stringify({ slug }),
  }),

  deactivatePlugin: (slug) => request('/migration/deactivate', {
    method: 'POST',
    body: JSON.stringify({ slug }),
  }),

  // Settings tools
  exportSettings: () => request('/settings/export'),

  importSettings: (settings) => request('/settings/import', {
    method: 'POST',
    body: JSON.stringify({ settings }),
  }),

  getOAuthUrl: (provider) => request(`/oauth/${provider}/url`),

  disconnectOAuth: (provider) => request(`/oauth/${provider}/disconnect`, {
    method: 'POST',
  }),

  resetSettings: () => request('/settings/reset', {
    method: 'POST',
  }),
};
