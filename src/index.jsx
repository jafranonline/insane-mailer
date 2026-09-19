import { render } from 'solid-js/web';
import { createSignal, Show, onMount } from 'solid-js';
import './index.css';
import Overview from './pages/Overview';
import Advanced from './pages/Advanced';
import Logs from './pages/Logs';
import Docs from './pages/Docs';
import Setup from './pages/Setup';
import ToastContainer from './components/Toast';
import { useSettings } from './store/settings';

function App() {
  const parseHash = () => {
    const hash = window.location.hash.slice(1);
    const [tab, subTab] = hash.split('/');

    // Provider used to be a top-level tab; keep old links and bookmarks working.
    if (tab === 'provider') {
      return { tab: 'advanced', subTab: 'provider' };
    }

    return { tab: tab || 'overview', subTab: subTab || null };
  };

  const getInitialTab = () => parseHash().tab;
  const getInitialSubTab = () => parseHash().subTab;

  const [currentTab, setCurrentTab] = createSignal(getInitialTab());
  const [currentSubTab, setCurrentSubTab] = createSignal(getInitialSubTab());
  const settings = useSettings();
  const isPaused = () => settings()?.pause_sending || false;
  const needsSetup = () => !settings()?.setup_completed && settings()?.provider === 'default';
  const isDefaultMailer = () => settings()?.provider === 'default';

  // Without a valid sender, WordPress falls back to wordpress@<host>, which many
  // providers reject outright.
  const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value || '');
  const missingSender = () => !isValidEmail(settings()?.from_email) || !settings()?.from_name;

  onMount(() => {
    if (needsSetup() && currentTab() !== 'setup') {
      switchTab('setup');
    }
  });

  const switchTab = (tab, subTab = null) => {
    setCurrentTab(tab);
    setCurrentSubTab(subTab);
    window.location.hash = subTab ? `${tab}/${subTab}` : tab;
  };

  const handleSetupComplete = () => {
    switchTab('overview');
  };

  return (
    <div class="">
      <Show when={isPaused()}>
        <div class="im:bg-red-600 im:text-white im:py-2 im:px-4 im:text-sm im:font-medium im:flex im:items-center im:justify-center im:gap-3">
          <span>Email sending is paused. Emails are being logged but not delivered.</span>
          <button
            onClick={() => switchTab('advanced')}
            class="im:px-2.5 im:py-1 im:bg-white im:text-red-600 im:rounded im:text-xs im:font-semibold hover:im:bg-red-50 im:transition-colors"
          >
            Settings
          </button>
        </div>
      </Show>
      <div class="im:bg-white im:border-b im:border-gray-200">
        <div class="im:max-w-7xl im:mx-auto im:px-8">
          <div class="im:flex im:items-center im:justify-between im:py-4">
            <div class="im:flex im:items-center im:gap-2.5">
              <svg class="im:w-8 im:h-8" viewBox="0 0 32 32" fill="none">
                <defs>
                  <linearGradient id="im-logo-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="#6366f1"/>
                    <stop offset="100%" stop-color="#8b5cf6"/>
                  </linearGradient>
                </defs>
                <rect width="32" height="32" rx="8" fill="url(#im-logo-grad)"/>
                <path d="M8 12L16 17L24 12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M8 12V20C8 20.5523 8.44772 21 9 21H23C23.5523 21 24 20.5523 24 20V12" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="22" cy="10" r="4" fill="#fbbf24" stroke="white" stroke-width="1.5"/>
                <path d="M22 8V10.5L23.5 11.5" stroke="white" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <h1 class="im:text-xl im:font-bold">
                <span class="im:bg-gradient-to-r im:from-indigo-600 im:to-violet-600 im:bg-clip-text im:text-transparent">Insane</span>
                <span class="im:text-gray-700">Mailer</span>
              </h1>
            </div>

            <nav class="im:flex im:gap-1">
              <button
                class={`im:px-4 im:py-2 im:text-sm im:font-medium im:rounded-md im:transition-colors ${
                  currentTab() === 'overview'
                    ? 'im:bg-gray-100 im:text-gray-900'
                    : 'im:text-gray-500 hover:im:text-gray-900 hover:im:bg-gray-50'
                }`}
                onClick={() => switchTab('overview')}
              >
                Overview
              </button>
              <button
                class={`im:px-4 im:py-2 im:text-sm im:font-medium im:rounded-md im:transition-colors ${
                  currentTab() === 'advanced'
                    ? 'im:bg-gray-100 im:text-gray-900'
                    : 'im:text-gray-500 hover:im:text-gray-900 hover:im:bg-gray-50'
                }`}
                onClick={() => switchTab('advanced')}
              >
                Settings
              </button>
              <button
                class={`im:px-4 im:py-2 im:text-sm im:font-medium im:rounded-md im:transition-colors ${
                  currentTab() === 'logs'
                    ? 'im:bg-gray-100 im:text-gray-900'
                    : 'im:text-gray-500 hover:im:text-gray-900 hover:im:bg-gray-50'
                }`}
                onClick={() => switchTab('logs')}
              >
                Logs
              </button>
              <button
                class={`im:px-4 im:py-2 im:text-sm im:font-medium im:rounded-md im:transition-colors ${
                  currentTab() === 'docs'
                    ? 'im:bg-gray-100 im:text-gray-900'
                    : 'im:text-gray-500 hover:im:text-gray-900 hover:im:bg-gray-50'
                }`}
                onClick={() => switchTab('docs')}
              >
                Docs
              </button>
            </nav>
          </div>
        </div>
      </div>

      <Show when={currentTab() !== 'setup'}>
        <Show when={missingSender()}>
          <div class="im:max-w-7xl im:mx-auto im:px-8 im:pt-6">
            <div class="im:bg-red-50 im:border im:border-red-200 im:rounded-lg im:px-4 im:py-3 im:flex im:items-center im:justify-between">
              <div class="im:flex im:items-center im:gap-3">
                <svg class="im:w-5 im:h-5 im:text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="im:text-sm im:text-red-800">
                  Sender details are incomplete. Emails will be sent from WordPress's default address, which many providers reject.
                </span>
              </div>
              <button
                onClick={() => switchTab('advanced', 'provider')}
                class="im:px-3 im:py-1.5 im:bg-red-600 im:text-white im:rounded im:text-sm im:font-medium hover:im:bg-red-700 im:transition-colors im:shrink-0 im:ml-4"
              >
                Set Sender
              </button>
            </div>
          </div>
        </Show>
        <Show when={isDefaultMailer()}>
          <div class="im:max-w-7xl im:mx-auto im:px-8 im:pt-6">
            <div class="im:bg-amber-50 im:border im:border-amber-200 im:rounded-lg im:px-4 im:py-3 im:flex im:items-center im:justify-between">
              <div class="im:flex im:items-center im:gap-3">
                <svg class="im:w-5 im:h-5 im:text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span class="im:text-sm im:text-amber-800">
                  Using PHP default mailer. Configure an SMTP provider for better deliverability.
                </span>
              </div>
              <button
                onClick={() => switchTab('setup')}
                class="im:px-3 im:py-1.5 im:bg-amber-600 im:text-white im:rounded im:text-sm im:font-medium hover:im:bg-amber-700 im:transition-colors"
              >
                Setup Provider
              </button>
            </div>
          </div>
        </Show>
        <div class="im:max-w-7xl im:mx-auto im:px-8 im:py-12">
          {currentTab() === 'overview' && <Overview onSwitchTab={switchTab} />}
          {currentTab() === 'advanced' && <Advanced subTab={currentSubTab()} onSubTabChange={(sub) => switchTab('advanced', sub)} />}
          {currentTab() === 'logs' && <Logs />}
          {currentTab() === 'docs' && <Docs />}
        </div>
      </Show>

      <Show when={currentTab() === 'setup'}>
        <Setup onComplete={handleSetupComplete} />
      </Show>

      <ToastContainer />
    </div>
  );
}

const root = document.getElementById('insane-mailer-app');
if (root) {
  render(() => <App />, root);
}
