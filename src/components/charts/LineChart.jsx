import { createSignal, For, Show } from 'solid-js';

const colors = {
  sent: '#10b981',
  failed: '#ef4444',
  bounced: '#f59e0b',
  complained: '#f97316',
};

export default function LineChart({ data, lines = ['sent', 'failed'], height = 200 }) {
  const [tooltip, setTooltip] = createSignal(null);

  const padding = { top: 20, right: 20, bottom: 30, left: 45 };
  const width = 600;
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;

  const maxValue = () => {
    if (!data || data.length === 0) return 100;
    let max = 0;
    data.forEach(d => {
      lines.forEach(line => {
        if (d[line] > max) max = d[line];
      });
    });
    return Math.max(max * 1.1, 10);
  };

  const points = (line) => {
    if (!data || data.length === 0) return '';
    const max = maxValue();
    return data.map((d, i) => {
      const x = padding.left + (i / Math.max(data.length - 1, 1)) * chartWidth;
      const y = padding.top + chartHeight - (d[line] / max) * chartHeight;
      return `${x},${y}`;
    }).join(' ');
  };

  const pathD = (line) => {
    if (!data || data.length === 0) return '';
    const max = maxValue();
    return data.map((d, i) => {
      const x = padding.left + (i / Math.max(data.length - 1, 1)) * chartWidth;
      const y = padding.top + chartHeight - (d[line] / max) * chartHeight;
      return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');
  };

  const areaD = (line) => {
    if (!data || data.length === 0) return '';
    const path = pathD(line);
    const lastX = padding.left + chartWidth;
    const firstX = padding.left;
    const bottom = padding.top + chartHeight;
    return `${path} L ${lastX} ${bottom} L ${firstX} ${bottom} Z`;
  };

  const gridLines = () => {
    const max = maxValue();
    const step = Math.ceil(max / 4);
    const lines = [];
    for (let i = 0; i <= 4; i++) {
      const value = step * i;
      const y = padding.top + chartHeight - (value / max) * chartHeight;
      lines.push({ y, value });
    }
    return lines;
  };

  const xLabels = () => {
    if (!data || data.length === 0) return [];
    const step = Math.max(Math.floor(data.length / 5), 1);
    return data.filter((_, i) => i % step === 0 || i === data.length - 1).map((d, i, arr) => ({
      label: formatDate(d.date),
      x: padding.left + (data.indexOf(d) / Math.max(data.length - 1, 1)) * chartWidth,
    }));
  };

  const formatDate = (dateStr) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  };

  const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toString();
  };

  const handleMouseMove = (e) => {
    if (!data || data.length === 0) return;
    const svg = e.currentTarget;
    const rect = svg.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const relX = x - padding.left;
    const index = Math.round((relX / chartWidth) * (data.length - 1));

    if (index >= 0 && index < data.length) {
      setTooltip({
        x: padding.left + (index / Math.max(data.length - 1, 1)) * chartWidth,
        data: data[index],
        index,
      });
    }
  };

  const handleMouseLeave = () => setTooltip(null);

  return (
    <div class="im:relative">
      <svg
        viewBox={`0 0 ${width} ${height}`}
        class="im:w-full im:h-auto"
        onMouseMove={handleMouseMove}
        onMouseLeave={handleMouseLeave}
      >
        {/* Grid lines */}
        <For each={gridLines()}>
          {(line) => (
            <>
              <line
                x1={padding.left}
                y1={line.y}
                x2={width - padding.right}
                y2={line.y}
                stroke="#e5e7eb"
                stroke-width="1"
              />
              <text
                x={padding.left - 8}
                y={line.y + 4}
                text-anchor="end"
                class="im:text-[10px] im:fill-gray-400"
              >
                {formatNumber(line.value)}
              </text>
            </>
          )}
        </For>

        {/* X-axis labels */}
        <For each={xLabels()}>
          {(label) => (
            <text
              x={label.x}
              y={height - 8}
              text-anchor="middle"
              class="im:text-[10px] im:fill-gray-400"
            >
              {label.label}
            </text>
          )}
        </For>

        {/* Area fills */}
        <For each={lines}>
          {(line) => (
            <path
              d={areaD(line)}
              fill={colors[line]}
              fill-opacity="0.1"
            />
          )}
        </For>

        {/* Lines */}
        <For each={lines}>
          {(line) => (
            <path
              d={pathD(line)}
              stroke={colors[line]}
              stroke-width="2"
              fill="none"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          )}
        </For>

        {/* Data points */}
        <For each={lines}>
          {(line) => (
            <For each={data || []}>
              {(d, i) => {
                const max = maxValue();
                const x = padding.left + (i() / Math.max((data?.length || 1) - 1, 1)) * chartWidth;
                const y = padding.top + chartHeight - (d[line] / max) * chartHeight;
                return (
                  <circle
                    cx={x}
                    cy={y}
                    r={tooltip()?.index === i() ? 5 : 3}
                    fill={colors[line]}
                    class="im:transition-all"
                  />
                );
              }}
            </For>
          )}
        </For>

        {/* Tooltip line */}
        <Show when={tooltip()}>
          <line
            x1={tooltip().x}
            y1={padding.top}
            x2={tooltip().x}
            y2={padding.top + chartHeight}
            stroke="#9ca3af"
            stroke-width="1"
            stroke-dasharray="4"
          />
        </Show>
      </svg>

      {/* Tooltip popup */}
      <Show when={tooltip()}>
        <div
          class="im:absolute im:bg-gray-900 im:text-white im:text-xs im:rounded-lg im:px-3 im:py-2 im:shadow-lg im:pointer-events-none im:z-10"
          style={{
            left: `${(tooltip().x / width) * 100}%`,
            top: '20px',
            transform: 'translateX(-50%)',
          }}
        >
          <div class="im:font-medium im:mb-1">{formatDate(tooltip().data.date)}</div>
          <For each={lines}>
            {(line) => (
              <div class="im:flex im:items-center im:gap-2">
                <span
                  class="im:w-2 im:h-2 im:rounded-full"
                  style={{ background: colors[line] }}
                />
                <span class="im:capitalize">{line}:</span>
                <span class="im:font-medium">{tooltip().data[line]}</span>
              </div>
            )}
          </For>
        </div>
      </Show>

      {/* Legend */}
      <div class="im:flex im:items-center im:justify-center im:gap-4 im:mt-3">
        <For each={lines}>
          {(line) => (
            <div class="im:flex im:items-center im:gap-1.5">
              <span
                class="im:w-3 im:h-3 im:rounded-full"
                style={{ background: colors[line] }}
              />
              <span class="im:text-xs im:text-gray-600 im:capitalize">{line}</span>
            </div>
          )}
        </For>
      </div>
    </div>
  );
}
