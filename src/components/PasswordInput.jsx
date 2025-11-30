import { createSignal } from 'solid-js';

export default function PasswordInput(props) {
  const [visible, setVisible] = createSignal(false);

  const borderClass = () => props.hasError
    ? 'im:border-red-300 im:ring-1 im:ring-red-300'
    : 'im:border-gray-300';

  return (
    <div class="im:relative">
      <input
        type={visible() ? 'text' : 'password'}
        class={props.class || `im:w-full im:px-3 im:py-2 im:pr-10 im:border im:rounded-md im:text-sm im:outline-none ${borderClass()}`}
        value={props.value}
        onInput={props.onInput}
        placeholder={props.placeholder}
      />
      <button
        type="button"
        class="im:absolute im:inset-y-0 im:right-0 im:flex im:items-center im:pr-3 im:text-gray-400 hover:im:text-gray-600 im:transition-colors"
        onClick={() => setVisible(!visible())}
      >
        {visible() ? (
          <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
          </svg>
        ) : (
          <svg class="im:w-4 im:h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
        )}
      </button>
    </div>
  );
}
