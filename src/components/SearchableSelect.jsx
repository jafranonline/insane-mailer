import { createSignal, For, Show, onCleanup, createEffect } from 'solid-js';

export default function SearchableSelect(props) {
  const [isOpen, setIsOpen] = createSignal(false);
  const [isAnimating, setIsAnimating] = createSignal(false);
  const [search, setSearch] = createSignal('');
  const [highlightedIndex, setHighlightedIndex] = createSignal(-1);
  const [openDirection, setOpenDirection] = createSignal('bottom');

  let containerRef;
  let inputRef;

  const selectedOption = () => props.options.find((o) => o.value === props.value);

  const getSelectedIndex = () => {
    const options = filteredOptions();
    return options.findIndex((o) => o.value === props.value);
  };

  const filteredOptions = () => {
    const query = search().toLowerCase();
    if (!query) return props.options;
    return props.options.filter(
      (o) => o.label.toLowerCase().includes(query) || o.value.toLowerCase().includes(query)
    );
  };

  const closeDropdown = () => {
    setIsAnimating(false);
    setTimeout(() => {
      setIsOpen(false);
      setSearch('');
      setHighlightedIndex(-1);
    }, 150);
  };

  const handleClickOutside = (e) => {
    if (containerRef && !containerRef.contains(e.target)) {
      closeDropdown();
    }
  };

  const calculateOpenDirection = () => {
    if (!containerRef) return 'bottom';
    const rect = containerRef.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const dropdownHeight = 240;
    return spaceBelow < dropdownHeight && spaceAbove > spaceBelow ? 'top' : 'bottom';
  };

  const handleOpen = () => {
    setOpenDirection(calculateOpenDirection());
    setIsOpen(true);
    setSearch('');
    setHighlightedIndex(-1);
    setTimeout(() => {
      setIsAnimating(true);
      inputRef?.focus();
    }, 10);
  };

  const handleSelect = (value) => {
    props.onChange(value);
    closeDropdown();
  };

  const handleKeyDown = (e) => {
    const options = filteredOptions();

    switch (e.key) {
      case 'ArrowDown':
        e.preventDefault();
        setHighlightedIndex((i) => Math.min(i + 1, options.length - 1));
        break;
      case 'ArrowUp':
        e.preventDefault();
        setHighlightedIndex((i) => Math.max(i - 1, 0));
        break;
      case 'Enter':
        e.preventDefault();
        if (highlightedIndex() >= 0 && options[highlightedIndex()]) {
          handleSelect(options[highlightedIndex()].value);
        }
        break;
      case 'Escape':
        closeDropdown();
        break;
    }
  };

  createEffect(() => {
    if (isOpen()) {
      document.addEventListener('mousedown', handleClickOutside);
    } else {
      document.removeEventListener('mousedown', handleClickOutside);
    }
  });

  onCleanup(() => {
    document.removeEventListener('mousedown', handleClickOutside);
  });

  createEffect(() => {
    search();
    setHighlightedIndex(-1);
  });

  return (
    <div ref={containerRef} class={`im:relative ${props.class || ''}`}>
      <Show when={!isOpen()}>
        <button
          type="button"
          onClick={handleOpen}
          class="im:w-full im:min-h-[38px] im:px-3 im:py-2 im:border im:border-gray-300 im:rounded-md im:text-sm im:text-left im:bg-white hover:im:border-gray-400 im:outline-none im:transition-all"
        >
          <span class={selectedOption() ? 'im:text-gray-900' : 'im:text-gray-500'}>
            {selectedOption()?.label || props.placeholder || 'Select...'}
          </span>
          <span class="im:absolute im:inset-y-0 im:right-0 im:flex im:items-center im:pr-2 im:pointer-events-none">
            <svg class="im:h-4 im:w-4 im:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </span>
        </button>
      </Show>

      <Show when={isOpen()}>
        <div class="im:w-full im:border im:border-gray-900 im:rounded-md im:bg-white im:transition-all">
          <input
            ref={inputRef}
            type="text"
            value={search()}
            onInput={(e) => setSearch(e.currentTarget.value)}
            onKeyDown={handleKeyDown}
            placeholder="Type to search..."
            class="im:w-full im:px-3 im:py-2 im:text-sm im:bg-transparent im:rounded-md im:border-none im:ring-0 focus:im:ring-0 focus:im:outline-none"
            style={{ outline: 'none !important', 'box-shadow': 'none !important', border: 'none !important' }}
          />
        </div>

        <div
          class={`im:absolute im:z-50 im:w-full im:bg-white im:border im:border-gray-200 im:rounded-md im:shadow-lg im:max-h-60 im:overflow-auto im:transform im:transition-all im:duration-150 im:ease-out ${
            openDirection() === 'top' ? 'im:bottom-full im:mb-1 im:origin-bottom' : 'im:top-full im:mt-1 im:origin-top'
          } ${isAnimating() ? 'im:opacity-100 im:scale-y-100' : 'im:opacity-0 im:scale-y-95'}`}
        >
          <Show when={filteredOptions().length === 0}>
            <div class="im:px-3 im:py-2 im:text-sm im:text-gray-500">No results found</div>
          </Show>
          <For each={filteredOptions()}>
            {(option, index) => (
              <button
                type="button"
                onClick={() => handleSelect(option.value)}
                onMouseEnter={() => setHighlightedIndex(index())}
                onMouseLeave={() => setHighlightedIndex(-1)}
                class={`im:w-full im:px-3 im:py-2 im:text-sm im:text-left im:transition-colors im:flex im:items-center im:gap-2 im:outline-none ${
                  props.value === option.value
                    ? 'im:bg-gray-100 im:text-gray-900 im:font-medium'
                    : highlightedIndex() === index()
                      ? 'im:bg-gray-50 im:text-gray-900'
                      : 'im:text-gray-700 hover:im:bg-gray-50'
                }`}
              >
                <span>{option.label}</span>
                <Show when={props.value === option.value}>
                  <svg class="im:w-4 im:h-4 im:ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                  </svg>
                </Show>
              </button>
            )}
          </For>
        </div>
      </Show>
    </div>
  );
}
