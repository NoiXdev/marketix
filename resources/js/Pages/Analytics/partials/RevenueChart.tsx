import { formatMoney } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { formatAxisLabel, formatBucket, Interval, spansSeveralDays } from '@/Pages/Analytics/partials/period';
import ChartDateAxis from '@/Pages/Dashboard/ChartDateAxis';
import { KeyboardEvent, useLayoutEffect, useRef, useState } from 'react';

export type RevenuePoint = { date: string; revenue: number; orders: number };

const SLOT_GAP = 1;

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

export default function RevenueChart({
  data,
  currency,
  interval,
  intervals,
  onIntervalChange,
}: {
  data: RevenuePoint[];
  currency: string;
  interval: Interval;
  intervals: Interval[];
  onIntervalChange: (interval: Interval) => void;
}) {
  const { t, locale } = useTranslation();
  const [hovered, setHovered] = useState<number | null>(null);
  const [width, setWidth] = useState(0);
  const [tipLeft, setTipLeft] = useState(0);
  const plotRef = useRef<HTMLDivElement>(null);
  const tipRef = useRef<HTMLDivElement>(null);

  const multiDay = spansSeveralDays(data.map((d) => d.date));
  const max = Math.max(0, ...data.map((d) => d.revenue));
  const slotWidth = data.length > 0 ? (width - SLOT_GAP * (data.length - 1)) / data.length : 0;
  const barWidth = clamp(slotWidth * 0.7, 2, 24);
  const point = hovered !== null ? data[hovered] : null;

  useLayoutEffect(() => {
    const el = plotRef.current;
    if (!el) return;
    setWidth(el.getBoundingClientRect().width);
    const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  useLayoutEffect(() => {
    if (hovered === null || !tipRef.current) return;
    const tipWidth = tipRef.current.offsetWidth;
    const slotLeft = hovered * (slotWidth + SLOT_GAP);
    let left = slotLeft + slotWidth + 8;
    if (left + tipWidth > width) left = slotLeft - 8 - tipWidth;
    setTipLeft(clamp(left, 0, Math.max(0, width - tipWidth)));
  }, [hovered, width, slotWidth]);

  function onKeyDown(e: KeyboardEvent<HTMLDivElement>) {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      e.preventDefault();
      setHovered((h) => clamp((h ?? data.length - 1) + (e.key === 'ArrowRight' ? 1 : -1), 0, data.length - 1));
    } else if (e.key === 'Escape') {
      setHovered(null);
    }
  }

  return (
    <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border p-6 shadow-[var(--shadow-sm)]">
      <div className="mb-5 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-foreground text-sm font-semibold">{t('analytics.dashboard.revenue.chart_title')}</h2>
        {intervals.length > 1 && (
          <div role="group" aria-label={t('analytics.dashboard.interval.label')} className="border-line inline-flex overflow-hidden rounded-lg border">
            {intervals.map((option) => (
              <button
                key={option}
                type="button"
                aria-pressed={option === interval}
                onClick={() => option !== interval && onIntervalChange(option)}
                className={`border-line border-r px-2.5 py-1 text-xs font-semibold last:border-r-0 focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
                  option === interval ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'
                }`}
              >
                {t(`analytics.dashboard.interval.${option}`)}
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="relative" onMouseLeave={() => setHovered(null)}>
        <div
          ref={plotRef}
          tabIndex={0}
          role="group"
          aria-label={t('analytics.dashboard.revenue.chart_title')}
          onKeyDown={onKeyDown}
          onFocus={() => setHovered((h) => h ?? data.length - 1)}
          onBlur={() => setHovered(null)}
          className="border-line flex h-44 border-b focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
          style={{ gap: SLOT_GAP }}
        >
          {data.map((d, i) => (
            <div
              key={d.date}
              onMouseEnter={() => setHovered(i)}
              className={`relative flex h-full min-w-0 flex-1 items-end justify-center rounded-t-[4px] pt-3 ${hovered === i ? 'bg-elevated' : ''}`}
            >
              <div
                className={`${barWidth >= 6 ? 'rounded-t-[4px]' : 'rounded-t-[2px]'} bg-accent transition-[height] duration-300`}
                style={{ width: barWidth, height: d.revenue > 0 && max > 0 ? `max(2px, ${(d.revenue / max) * 100}%)` : '0px' }}
              />
            </div>
          ))}
        </div>

        {point && (
          <div
            ref={tipRef}
            className="border-line bg-surface pointer-events-none absolute top-2 z-20 min-w-40 rounded-lg border px-3 py-2 text-xs shadow-[var(--shadow)]"
            style={{ left: tipLeft }}
          >
            <p className="text-muted mb-1 font-semibold whitespace-nowrap">
              {formatBucket(point.date, interval, multiDay, locale, (date) => t('analytics.dashboard.chart.week_of', { date }))}
            </p>
            <p className="text-foreground text-sm font-bold whitespace-nowrap tabular-nums">{formatMoney(point.revenue, currency, locale)}</p>
            <p className="text-muted whitespace-nowrap">{t('analytics.dashboard.revenue.orders_count', { count: point.orders.toLocaleString(locale) })}</p>
          </div>
        )}
      </div>

      <ChartDateAxis dates={data.map((d) => d.date)} gapClass="gap-px" format={(key) => formatAxisLabel(key, interval, multiDay, locale)} />
    </section>
  );
}
