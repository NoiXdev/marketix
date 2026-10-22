import { Checkbox } from '@/Components/ui';
import { niceTicks } from '@/lib/chartAxis';
import { scrubHandlers } from '@/lib/chartScrub';
import { formatAxisMoney, formatMoney, percentChange } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { formatAxisLabel, formatBucket, Interval, spansSeveralDays } from '@/Pages/Analytics/partials/period';
import ChartDateAxis from '@/Pages/Dashboard/ChartDateAxis';
import { KeyboardEvent, useLayoutEffect, useRef, useState } from 'react';

export type RevenuePoint = { date: string; revenue: number; orders: number };

const SLOT_GAP = 1;
const PLOT_TOP = 12;

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

export default function RevenueChart({
  data,
  previous = null,
  comparisonRange = null,
  currency,
  interval,
  intervals,
  currentBucket = null,
  onIntervalChange,
}: {
  data: RevenuePoint[];
  previous?: RevenuePoint[] | null;
  comparisonRange?: string | null;
  currency: string;
  interval: Interval;
  intervals: Interval[];
  currentBucket?: string | null;
  onIntervalChange: (interval: Interval) => void;
}) {
  const { t, locale } = useTranslation();
  const [hovered, setHovered] = useState<number | null>(null);
  const [showComparison, setShowComparison] = useState(true);
  const [size, setSize] = useState({ width: 0, height: 0 });
  const [tipLeft, setTipLeft] = useState(0);
  const plotRef = useRef<HTMLDivElement>(null);
  const tipRef = useRef<HTMLDivElement>(null);

  const comparison = previous && previous.length > 0 && showComparison ? previous.slice(0, data.length) : null;
  const multiDay = spansSeveralDays(data.map((d) => d.date));
  const ticks = niceTicks(Math.max(0, ...data.map((d) => d.revenue), ...(comparison ?? []).map((d) => d.revenue)));
  const max = ticks[ticks.length - 1];
  const innerHeight = Math.max(0, size.height - PLOT_TOP);
  const slotWidth = data.length > 0 ? (size.width - SLOT_GAP * (data.length - 1)) / data.length : 0;
  const barWidth = clamp(slotWidth * 0.7, 2, 24);
  const yFor = (value: number) => PLOT_TOP + (max > 0 ? 1 - value / max : 1) * innerHeight;
  const xFor = (i: number) => i * (slotWidth + SLOT_GAP) + slotWidth / 2;
  const partialIndex = currentBucket === null ? -1 : data.findIndex((d) => d.date === currentBucket);
  const point = hovered !== null ? data[hovered] : null;
  const previousPoint = hovered !== null ? (comparison?.[hovered] ?? null) : null;
  const money = (value: number) => formatMoney(value, currency, locale);
  const bucketLabel = (key: string) => formatBucket(key, interval, multiDay, locale, (date) => t('analytics.dashboard.chart.week_of', { date }));

  useLayoutEffect(() => {
    const el = plotRef.current;
    if (!el) return;
    setSize({ width: el.getBoundingClientRect().width, height: el.clientHeight });
    const observer = new ResizeObserver(([entry]) => setSize({ width: entry.contentRect.width, height: entry.contentRect.height }));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  useLayoutEffect(() => {
    if (hovered === null || !tipRef.current) return;
    const tipWidth = tipRef.current.offsetWidth;
    const slotLeft = hovered * (slotWidth + SLOT_GAP);
    let left = slotLeft + slotWidth + 8;
    if (left + tipWidth > size.width) left = slotLeft - 8 - tipWidth;
    setTipLeft(clamp(left, 0, Math.max(0, size.width - tipWidth)));
  }, [hovered, size.width, slotWidth, showComparison]);

  function onKeyDown(e: KeyboardEvent<HTMLDivElement>) {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      e.preventDefault();
      setHovered((h) => clamp((h ?? data.length - 1) + (e.key === 'ArrowRight' ? 1 : -1), 0, data.length - 1));
    } else if (e.key === 'Escape') {
      setHovered(null);
    }
  }

  const change = point && previousPoint ? percentChange(point.revenue, previousPoint.revenue) : null;

  return (
    <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border p-4 shadow-[var(--shadow-sm)] sm:p-6">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
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

      {previous && previous.length > 0 && (
        <div className="mb-5 flex flex-wrap gap-2">
          <label
            className={`inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2 py-1 text-xs font-semibold transition-colors select-none sm:gap-2 sm:px-2.5 sm:py-1.5 sm:text-[13px] ${
              showComparison ? 'border-line-strong bg-elevated text-foreground' : 'border-line text-muted hover:bg-elevated'
            }`}
          >
            <Checkbox checked={showComparison} onChange={() => setShowComparison((on) => !on)} />
            <svg aria-hidden width="16" height="2" className="text-muted shrink-0">
              <line x1="0" y1="1" x2="16" y2="1" stroke="currentColor" strokeWidth="2" strokeDasharray="4 3" />
            </svg>
            {t('analytics.dashboard.chart.comparison')}
            {comparisonRange && <span className="text-muted font-medium">{comparisonRange}</span>}
          </label>
        </div>
      )}

      <div className="flex gap-2">
        <div aria-hidden className="relative h-44 w-16 shrink-0">
          {ticks.map((tick) => (
            <span key={tick} className="text-subtle absolute right-0 -translate-y-1/2 text-[10px] leading-none whitespace-nowrap tabular-nums" style={{ top: yFor(tick) }}>
              {formatAxisMoney(tick, currency, locale)}
            </span>
          ))}
        </div>

        <div className="min-w-0 flex-1">
          <div className="relative" onMouseLeave={() => setHovered(null)}>
            <div
              ref={plotRef}
              tabIndex={0}
              role="group"
              aria-label={t('analytics.dashboard.revenue.chart_title')}
              onKeyDown={onKeyDown}
              onFocus={() => setHovered((h) => h ?? data.length - 1)}
              onBlur={() => setHovered(null)}
              {...scrubHandlers((slot) => setHovered(Number(slot)))}
              className="border-line relative flex h-44 touch-pan-y border-b focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
              style={{ gap: SLOT_GAP }}
            >
              {ticks.slice(1).map((tick) => (
                <span key={tick} aria-hidden className="border-line pointer-events-none absolute inset-x-0 border-t border-dashed" style={{ top: yFor(tick) }} />
              ))}
              {data.map((d, i) => (
                <div
                  key={d.date}
                  data-slot={i}
                  className={`relative flex h-full min-w-0 flex-1 items-end justify-center rounded-t-[4px] ${hovered === i ? 'bg-elevated' : ''}`}
                  style={{ paddingTop: PLOT_TOP }}
                >
                  <div
                    className={`${barWidth >= 6 ? 'rounded-t-[4px]' : 'rounded-t-[2px]'} bg-accent transition-[height] duration-300 ${i === partialIndex ? 'opacity-40' : ''}`}
                    style={{ width: barWidth, height: d.revenue > 0 && max > 0 ? `max(2px, ${(d.revenue / max) * 100}%)` : '0px' }}
                  />
                </div>
              ))}

              {comparison && size.width > 0 && (
                <svg aria-hidden className="pointer-events-none absolute inset-0 overflow-visible" width={size.width} height={size.height}>
                  <polyline
                    points={comparison.map((d, i) => `${xFor(i)},${yFor(d.revenue)}`).join(' ')}
                    fill="none"
                    stroke="var(--muted)"
                    strokeWidth={1.5}
                    strokeDasharray="4 3"
                    strokeLinejoin="round"
                    strokeLinecap="round"
                  />
                  {previousPoint && hovered !== null && (
                    <circle cx={xFor(hovered)} cy={yFor(previousPoint.revenue)} r={3.5} fill="var(--surface)" stroke="var(--muted)" strokeWidth={1.5} />
                  )}
                </svg>
              )}
            </div>

            {point && (
              <div
                ref={tipRef}
                className="border-line bg-surface pointer-events-none absolute top-2 z-20 min-w-40 rounded-lg border px-3 py-2 text-xs shadow-[var(--shadow)]"
                style={{ left: tipLeft }}
              >
                <p className="text-muted mb-1 font-semibold whitespace-nowrap">
                  {bucketLabel(point.date)}
                  {hovered === partialIndex && ` (${t('analytics.dashboard.chart.partial')})`}
                </p>
                <p className="text-foreground text-sm font-bold whitespace-nowrap tabular-nums">{money(point.revenue)}</p>
                <p className="text-muted whitespace-nowrap">{t('analytics.dashboard.revenue.orders_count', { count: point.orders.toLocaleString(locale) })}</p>
                {previousPoint && (
                  <p className="border-line text-muted mt-1.5 border-t pt-1.5 whitespace-nowrap">
                    {bucketLabel(previousPoint.date)}: <span className="text-foreground font-semibold tabular-nums">{money(previousPoint.revenue)}</span>
                    {change !== null && change !== 0 && (
                      <span className={`ml-1.5 font-bold ${change > 0 ? 'text-success-foreground' : 'text-danger-foreground'}`}>
                        {change > 0 ? '▲' : '▼'} {Math.abs(change).toLocaleString(locale)} %
                      </span>
                    )}
                  </p>
                )}
              </div>
            )}
          </div>

          <ChartDateAxis dates={data.map((d) => d.date)} gapClass="gap-px" format={(key) => formatAxisLabel(key, interval, multiDay, locale)} />
        </div>
      </div>

      {(comparison || partialIndex >= 0) && (
        <p className="text-subtle mt-3 text-xs">
          {[comparison ? t('analytics.dashboard.chart.comparison_hint') : null, partialIndex >= 0 ? t('analytics.dashboard.chart.partial_hint') : null].filter(Boolean).join(' ')}
        </p>
      )}
    </section>
  );
}
