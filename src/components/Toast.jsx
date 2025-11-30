import { createSignal, For, onMount } from 'solid-js';

const [toasts, setToasts] = createSignal([]);

let toastId = 0;

export function toast(message, type = 'success', duration = 3000) {
  const id = ++toastId;

  setToasts((prev) => [...prev, { id, message, type, duration, entering: true, leaving: false }]);

  setTimeout(() => {
    setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, entering: false } : t)));
  }, 10);

  setTimeout(() => {
    dismissToast(id);
  }, duration);
}

function dismissToast(id) {
  setToasts((prev) => prev.map((t) => (t.id === id ? { ...t, leaving: true } : t)));
  setTimeout(() => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, 300);
}

toast.success = (message, duration) => toast(message, 'success', duration);
toast.error = (message, duration) => toast(message, 'error', duration);

function ToastItem(props) {
  return (
    <div
      class={`im:relative im:flex im:items-center im:gap-3 im:pl-3 im:pr-2 im:py-2.5 im:rounded-lg im:shadow-lg im:text-sm im:overflow-hidden im:transition-all im:duration-300 im:ease-out ${
        props.toast.entering
          ? 'im:opacity-0 im:translate-x-8 im:scale-95'
          : props.toast.leaving
            ? 'im:opacity-0 im:translate-x-8 im:scale-95'
            : 'im:opacity-100 im:translate-x-0 im:scale-100'
      } ${
        props.toast.type === 'success'
          ? 'im:bg-white im:text-gray-800 im:border im:border-gray-200'
          : 'im:bg-white im:text-gray-800 im:border im:border-red-200'
      }`}
    >
      {props.toast.type === 'success' ? (
        <div class="im:w-5 im:h-5 im:rounded-full im:bg-green-500 im:flex im:items-center im:justify-center im:flex-shrink-0">
          <svg class="im:w-3 im:h-3 im:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
      ) : (
        <div class="im:w-5 im:h-5 im:rounded-full im:bg-red-500 im:flex im:items-center im:justify-center im:flex-shrink-0">
          <svg class="im:w-3 im:h-3 im:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
      )}

      <span class="im:flex-1">{props.toast.message}</span>

      <button
        type="button"
        onClick={() => dismissToast(props.toast.id)}
        class="im:p-1 im:rounded im:text-gray-400 hover:im:text-gray-600 hover:im:bg-gray-100 im:transition-colors"
      >
        <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div
        class={`im:absolute im:bottom-0 im:left-0 im:h-0.5 ${
          props.toast.type === 'success' ? 'im:bg-green-500' : 'im:bg-red-500'
        }`}
        style={{
          animation: props.toast.entering ? 'none' : `toast-progress ${props.toast.duration}ms linear forwards`,
        }}
      />
    </div>
  );
}

export default function ToastContainer() {
  onMount(() => {
    if (!document.getElementById('toast-keyframes')) {
      const style = document.createElement('style');
      style.id = 'toast-keyframes';
      style.textContent = `
        @keyframes toast-progress {
          from { width: 100%; }
          to { width: 0%; }
        }
      `;
      document.head.appendChild(style);
    }
  });

  return (
    <div class="im:fixed im:bottom-5 im:right-5 im:z-50 im:flex im:flex-col im:gap-2">
      <For each={toasts()}>
        {(t) => <ToastItem toast={t} />}
      </For>
    </div>
  );
}
