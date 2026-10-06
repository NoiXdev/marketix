import { useId } from 'react';

const WIDTH = 100;
const HEIGHT = 32;

export default function Sparkline({ values, className = 'h-12 w-full' }: { values: number[]; className?: string }) {
  const gradientId = useId();
  const max = Math.max(1, ...values);
  const step = values.length > 1 ? WIDTH / (values.length - 1) : WIDTH;
  const points = values.map((value, i) => `${(i * step).toFixed(2)},${(HEIGHT - (value / max) * (HEIGHT - 2) - 1).toFixed(2)}`);
  const line = `M${points.join(' L')}`;
  const area = `${line} L${WIDTH},${HEIGHT} L0,${HEIGHT} Z`;

  return (
    <svg aria-hidden viewBox={`0 0 ${WIDTH} ${HEIGHT}`} preserveAspectRatio="none" className={className}>
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="var(--accent)" stopOpacity="0.22" />
          <stop offset="100%" stopColor="var(--accent)" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={area} fill={`url(#${gradientId})`} />
      <path d={line} fill="none" stroke="var(--accent)" strokeWidth="1.75" strokeLinejoin="round" strokeLinecap="round" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}
