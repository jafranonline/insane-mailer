import { createSignal, For, Show } from 'solid-js';

const statusColors = {
  sent: '#10b981',
  pending: '#6366f1',
  processing: '#8b5cf6',
  failed: '#ef4444',
  bounced: '#f59e0b',
  complained: '#f97316',
  paused: '#6b7280',
};

const statusLabels = {
  sent: 'Sent',
  pending: 'Pending',
  processing: 'Processing',
  failed: 'Failed',
  bounced: 'Bounced',
  complained: 'Complained',
  paused: 'Paused',
};

export default function DonutChart({ data, size = 180, onSegmentClick }) {
  const [hovered, setHovered] = createSignal(null);

  const total = () => {
    if (!data) return 0;
    return Object.values(data).reduce((sum, val) => sum + val, 0);
  };

  const segments = () => {
    if (!data || total() === 0) return [];
    const entries = Object.entries(data).filter(([_, value]) => value > 0);
    let currentAngle = -90;

    return entries.map(([status, value]) => {
      const percentage = (value / total()) * 100;
      const angle = (value / total()) * 360;
      const segment = {
        status,
        value,
        percentage,
        startAngle: currentAngle,
        endAngle: currentAngle + angle,
        color: statusColors[status] || '#6b7280',
        label: statusLabels[status] || status,
      };
      currentAngle += angle;
      return segment;
    });
  };

  const describeArc = (startAngle, endAngle, radius, innerRadius) => {
    const start = polarToCartesian(radius, endAngle);
    const end = polarToCartesian(radius, startAngle);
    const innerStart = polarToCartesian(innerRadius, endAngle);
    const innerEnd = polarToCartesian(innerRadius, startAngle);
    const largeArcFlag = endAngle - startAngle <= 180 ? 0 : 1;

    return [
      'M', start.x, start.y,
      'A', radius, radius, 0, largeArcFlag, 0, end.x, end.y,
      'L', innerEnd.x, innerEnd.y,
      'A', innerRadius, innerRadius, 0, largeArcFlag, 1, innerStart.x, innerStart.y,
      'Z',
    ].join(' ');
  };

  const polarToCartesian = (radius, angleInDegrees) => {
    const angleInRadians = (angleInDegrees * Math.PI) / 180;
    return {
      x: size / 2 + radius * Math.cos(angleInRadians),
      y: size / 2 + radius * Math.sin(angleInRadians),
    };
  };

  const formatNumber = (num) => {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return num.toLocaleString();
  };

  const radius = size / 2 - 10;
  const innerRadius = radius * 0.6;

  return (
    <div class="im:flex im:flex-col im:items-center">
      <div class="im:relative">
        <svg
          width={size}
          height={size}
          viewBox={`0 0 ${size} ${size}`}
          class="im:transform im:-rotate-90"
        >
          <Show when={total() > 0} fallback={
            <circle
              cx={size / 2}
              cy={size / 2}
              r={radius}
              fill="none"
              stroke="#e5e7eb"
              stroke-width={radius - innerRadius}
            />
          }>
            <For each={segments()}>
              {(segment) => (
                <path
                  d={describeArc(segment.startAngle, segment.endAngle, radius, innerRadius)}
                  fill={segment.color}
                  class="im:cursor-pointer im:transition-opacity hover:im:opacity-80"
                  onMouseEnter={() => setHovered(segment)}
                  onMouseLeave={() => setHovered(null)}
                  onClick={() => onSegmentClick?.(segment.status)}
                  style={{
                    opacity: hovered() && hovered().status !== segment.status ? 0.5 : 1,
                  }}
                />
              )}
            </For>
          </Show>
        </svg>

        {/* Center text */}
        <div class="im:absolute im:inset-0 im:flex im:flex-col im:items-center im:justify-center">
          <Show when={hovered()} fallback={
            <>
              <span class="im:text-2xl im:font-bold im:text-gray-900">
                {formatNumber(total())}
              </span>
              <span class="im:text-xs im:text-gray-500">Total</span>
            </>
          }>
            <span class="im:text-2xl im:font-bold" style={{ color: hovered().color }}>
              {hovered().percentage.toFixed(1)}%
            </span>
            <span class="im:text-xs im:text-gray-500">{hovered().label}</span>
          </Show>
        </div>
      </div>

      {/* Legend */}
      <div class="im:grid im:grid-cols-2 im:gap-x-4 im:gap-y-1.5 im:mt-4">
        <For each={segments()}>
          {(segment) => (
            <button
              type="button"
              class="im:flex im:items-center im:gap-2 im:text-left hover:im:bg-gray-50 im:rounded im:px-1 im:-mx-1 im:transition-colors"
              onClick={() => onSegmentClick?.(segment.status)}
              onMouseEnter={() => setHovered(segment)}
              onMouseLeave={() => setHovered(null)}
            >
              <span
                class="im:w-2.5 im:h-2.5 im:rounded-full im:shrink-0"
                style={{ background: segment.color }}
              />
              <span class="im:text-xs im:text-gray-600">{segment.label}</span>
              <span class="im:text-xs im:font-medium im:text-gray-900 im:ml-auto">
                {formatNumber(segment.value)}
              </span>
            </button>
          )}
        </For>
      </div>
    </div>
  );
}
