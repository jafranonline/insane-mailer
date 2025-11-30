import { createSignal, Show } from 'solid-js';

const periods = [
  { value: 7, label: 'Last 7 days' },
  { value: 30, label: 'Last 30 days' },
  { value: 90, label: 'Last 90 days' },
];

export default function DateRangeSelect({ value, onChange }) {
  const [isOpen, setIsOpen] = createSignal(false);

  const currentLabel = () => {
    const period = periods.find(p => p.value === value);
    return period?.label || 'Select period';
  };

  const handleSelect = (periodValue) => {
    onChange(periodValue);
    setIsOpen(false);
  };

  return (
    <div class="im:relative">
      <button
        type="button"
        class="im:flex im:items-center im:gap-2 im:px-3 im:py-1.5 im:bg-white im:border im:border-gray-200 im:rounded-lg im:text-sm im:text-gray-700 im:font-medium hover:im:bg-gray-50 im:transition-colors"
        onClick={() => setIsOpen(!isOpen())}
      >
        <svg class="im:w-4 im:h-4 im:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        {currentLabel()}
        <svg class={`im:w-4 im:h-4 im:text-gray-400 im:transition-transform ${isOpen() ? 'im:rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      <Show when={isOpen()}>
        <div class="im:absolute im:right-0 im:mt-1 im:w-40 im:bg-white im:border im:border-gray-200 im:rounded-lg im:shadow-lg im:z-10">
          {periods.map((period) => (
            <button
              type="button"
              class={`im:w-full im:px-3 im:py-2 im:text-left im:text-sm im:transition-colors first:im:rounded-t-lg last:im:rounded-b-lg ${
                value === period.value
                  ? 'im:bg-gray-100 im:text-gray-900 im:font-medium'
                  : 'im:text-gray-600 hover:im:bg-gray-50'
              }`}
              onClick={() => handleSelect(period.value)}
            >
              {period.label}
            </button>
          ))}
        </div>
      </Show>

      {/* Backdrop to close dropdown */}
      <Show when={isOpen()}>
        <div
          class="im:fixed im:inset-0 im:z-0"
          onClick={() => setIsOpen(false)}
        />
      </Show>
    </div>
  );
}
