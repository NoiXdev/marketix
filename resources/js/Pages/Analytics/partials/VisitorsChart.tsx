import { Checkbox } from '@/Components/ui';
import { Interval, parseDate } from '@/Pages/Analytics/partials/period';
import ChartDateAxis from '@/Pages/Dashboard/ChartDateAxis';
import { formatDuration } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { KeyboardEvent, useEffect, useLayoutEffect, useRef, useState } from 'react';

export type SeriesPoint = {
  date: string;
  views: number;
  visitors: number;
  sessions: number;
  bounce_rate: number | null;
  avg_duration: number | null;
  campaign_share: number | null;
};

type MetricKey = 'views' | 'visitors' | 'bounce_rate' | 'avg_duration' | 'campaign_share';

type Metric = {
  key: MetricKey;
  kind: 'bar' | 'line';
  color: string;
  label: string;
  format: (value: number) => string;
};

const STORAGE_KEY = 'marketix.analytics.chart-metrics';
const METRIC_KEYS: MetricKey[] = ['views', 'visitors', 'bounce_rate', 'avg_duration', 'campaign_share'];
const DEFAULT_SELECTION: MetricKey[] = ['views', 'visitors'];
const SLOT_GAP = 1;
const MARK_GAP = 2;
const PLOT_TOP = 12;

function loadSelection(): MetricKey[] {
  try {
    const parsed: unknown = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? 'null');
    return Array.isArray(parsed) ? METRIC_KEYS.filter((k) => parsed.includes(k)) : DEFAULT_SELECTION;
  } catch {
    return DEFAULT_SELECTION;
  }
}

function saveSelection(selection: MetricKey[]) {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(selection));
  } catch {
    return;
  }
}

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

function barHeight(value: number | null, max: number) {
  if (!value || max <= 0) return '0px';
  return `max(2px, ${(value / max) * 100}%)`;
}

export default function VisitorsChart({
  title,
  data,
  interval,
  intervals,
  onIntervalChange,
}: {
  title: string;
  data: SeriesPoint[];
  interval: Interval;
  intervals: Interval[];
  onIntervalChange: (interval: Interval) => void;
}) {
  const { t, locale } = useTranslation();
  const [selection, setSelection] = useState<MetricKey[]>(loadSelection);
  const [hovered, setHovered] = useState<number | null>(null);
  const [showTable, setShowTable] = useState(false);
  const [size, setSize] = useState({ width: 0, height: 0 });
  const [tipLeft, setTipLeft] = useState(0);
  const plotRef = useRef<HTMLDivElement>(null);
  const tipRef = useRef<HTMLDivElement>(null);

  const multiDay = data.length > 0 && data[0].date.slice(0, 10) !== data[data.length - 1].date.slice(0, 10);
  const count = (n: number) => n.toLocaleString(locale);
  const percent = (n: number) => `${n.toLocaleString(locale)} %`;

  const metrics: Metric[] = [
    { key: 'views', kind: 'bar', color: 'var(--chart-views)', label: t('analytics.dashboard.kpi.page_views'), format: count },
    { key: 'visitors', kind: 'bar', color: 'var(--chart-visitors)', label: t('analytics.dashboard.kpi.unique_visitors'), format: count },
    { key: 'bounce_rate', kind: 'line', color: 'var(--chart-bounce)', label: t('analytics.dashboard.kpi.bounce_rate'), format: percent },
    { key: 'avg_duration', kind: 'line', color: 'var(--chart-duration)', label: t('analytics.dashboard.kpi.avg_duration'), format: formatDuration },
    { key: 'campaign_share', kind: 'line', color: 'var(--chart-campaign)', label: t('analytics.dashboard.kpi.from_campaigns'), format: percent },
  ];

  const active = metrics.filter((m) => selection.includes(m.key));
  const bars = active.filter((m) => m.kind === 'bar');
  const lines = active.filter((m) => m.kind === 'line');

  const barMax = Math.max(0, ...data.flatMap((p) => bars.map((m) => p[m.key] ?? 0)));

  const slotWidth = data.length > 0 ? (size.width - SLOT_GAP * (data.length - 1)) / data.length : 0;
  const barWidth = bars.length > 0 ? clamp((slotWidth * 0.72 - MARK_GAP * (bars.length - 1)) / bars.length, 2, 24) : 0;
  const innerHeight = Math.max(0, size.height - PLOT_TOP);
  const slotCenter = (i: number) => i * (slotWidth + SLOT_GAP) + slotWidth / 2;

  const linePaths = lines.map((m) => {
    const max = Math.max(0, ...data.map((p) => p[m.key] ?? 0));
    const y = (value: number) => PLOT_TOP + (max > 0 ? 1 - value / max : 1) * innerHeight;
    const segments: { x: number; y: number }[][] = [];
    let current: { x: number; y: number }[] = [];
    data.forEach((p, i) => {
      const value = p[m.key];
      if (value === null) {
        if (current.length > 0) segments.push(current);
        current = [];
        return;
      }
      current.push({ x: slotCenter(i), y: y(value) });
    });
    if (current.length > 0) segments.push(current);

    return {
      metric: m,
      segments,
      pointAt: (i: number) => {
        const value = data[i]?.[m.key];
        return value === null || value === undefined ? null : { x: slotCenter(i), y: y(value) };
      },
    };
  });

  useEffect(() => saveSelection(selection), [selection]);

  useLayoutEffect(() => {
    const el = plotRef.current;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    setSize({ width: rect.width, height: el.clientHeight });
    const observer = new ResizeObserver(([entry]) => setSize({ width: entry.contentRect.width, height: entry.contentRect.height }));
    observer.observe(el);
    return () => observer.disconnect();
  }, [showTable]);

  useLayoutEffect(() => {
    if (hovered === null || !tipRef.current) return;
    const tipWidth = tipRef.current.offsetWidth;
    const slotLeft = hovered * (slotWidth + SLOT_GAP);
    let left = slotLeft + slotWidth + 8;
    if (left + tipWidth > size.width) left = slotLeft - 8 - tipWidth;
    setTipLeft(clamp(left, 0, Math.max(0, size.width - tipWidth)));
  }, [hovered, size.width, slotWidth, selection]);

  function toggle(key: MetricKey) {
    setSelection((current) => (current.includes(key) ? current.filter((k) => k !== key) : METRIC_KEYS.filter((k) => k === key || current.includes(k))));
  }

  function formatBucket(key: string) {
    const date = parseDate(key);
    switch (interval) {
      case 'hour': {
        const hour = Number(key.slice(11, 13));
        const hours = `${String(hour).padStart(2, '0')}:00 – ${String((hour + 1) % 24).padStart(2, '0')}:00`;
        return multiDay ? `${date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short' })}, ${hours}` : hours;
      }
      case 'week':
        return t('analytics.dashboard.chart.week_of', {
          date: date.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' }),
        });
      case 'month':
        return date.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
      default:
        return date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    }
  }

  function formatAxis(key: string) {
    const date = parseDate(key);
    switch (interval) {
      case 'hour':
        return multiDay ? `${date.toLocaleDateString(locale, { day: 'numeric', month: 'short' })} ${key.slice(11, 16)}` : key.slice(11, 16);
      case 'month':
        return date.toLocaleDateString(locale, { month: 'short', year: '2-digit' });
      default:
        return date.toLocaleDateString(locale, { day: 'numeric', month: 'short' });
    }
  }

  function onKeyDown(e: KeyboardEvent<HTMLDivElement>) {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      e.preventDefault();
      const step = e.key === 'ArrowRight' ? 1 : -1;
      setHovered((h) => clamp((h ?? data.length - 1) + step, 0, data.length - 1));
    } else if (e.key === 'Escape') {
      setHovered(null);
    }
  }

  const point = hovered !== null ? data[hovered] : null;

  return (
    <section className="mb-6 rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-foreground">{title}</h2>
        <div className="flex flex-wrap items-center gap-3">
          {intervals.length > 1 && (
            <div role="group" aria-label={t('analytics.dashboard.interval.label')} className="inline-flex overflow-hidden rounded-lg border border-line">
              {intervals.map((option) => (
                <button
                  key={option}
                  type="button"
                  aria-pressed={option === interval}
                  onClick={() => option !== interval && onIntervalChange(option)}
                  className={`border-r border-line px-2.5 py-1 text-xs font-semibold last:border-r-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                    option === interval ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'
                  }`}
                >
                  {t(`analytics.dashboard.interval.${option}`)}
                </button>
              ))}
            </div>
          )}
          <button
            type="button"
            onClick={() => setShowTable((v) => !v)}
            className="text-xs font-semibold text-accent-soft-foreground hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
          >
            {showTable ? t('analytics.dashboard.chart.show_chart') : t('analytics.dashboard.chart.show_table')}
          </button>
        </div>
      </div>

      <div className="mb-5 flex flex-wrap gap-2">
        {metrics.map((m) => {
          const on = selection.includes(m.key);
          return (
            <label
              key={m.key}
              className={`inline-flex cursor-pointer select-none items-center gap-2 rounded-lg border px-2.5 py-1.5 text-[13px] font-semibold transition-colors ${
                on ? 'border-line-strong bg-elevated text-foreground' : 'border-line text-muted hover:bg-elevated'
              }`}
            >
              <Checkbox checked={on} onChange={() => toggle(m.key)} />
              <span
                aria-hidden
                className={m.kind === 'bar' ? 'h-3 w-2.5 rounded-t-[2px]' : 'h-0.5 w-4 rounded-full'}
                style={{ background: m.color }}
              />
              {m.label}
            </label>
          );
        })}
      </div>

      {showTable ? (
        <div className="max-h-96 overflow-auto rounded-lg border border-line">
          <table className="w-full text-sm">
            <thead className="sticky top-0 bg-surface">
              <tr>
                <th className="px-3 py-2 text-left text-xs font-semibold text-muted">
                  {t(`analytics.dashboard.interval.${interval}`)}
                </th>
                {metrics.map((m) => (
                  <th key={m.key} className="whitespace-nowrap px-3 py-2 text-right text-xs font-semibold text-muted">
                    {m.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {[...data].reverse().map((p) => (
                <tr key={p.date} className="border-t border-line">
                  <td className="whitespace-nowrap px-3 py-1.5 text-muted">{formatBucket(p.date)}</td>
                  {metrics.map((m) => {
                    const value = p[m.key];
                    return (
                      <td key={m.key} className="px-3 py-1.5 text-right font-semibold tabular-nums text-foreground">
                        {value === null ? '—' : m.format(value)}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <>
          <div className="relative" onMouseLeave={() => setHovered(null)}>
            <div
              ref={plotRef}
              tabIndex={0}
              role="group"
              aria-label={title}
              onKeyDown={onKeyDown}
              onFocus={() => setHovered((h) => h ?? data.length - 1)}
              onBlur={() => setHovered(null)}
              className="relative flex h-48 border-b border-line focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
              style={{ gap: SLOT_GAP }}
            >
              {data.map((p, i) => (
                <div
                  key={p.date}
                  onMouseEnter={() => setHovered(i)}
                  className={`relative h-full min-w-0 flex-1 rounded-t-[4px] ${hovered === i ? 'bg-elevated' : ''}`}
                >
                  {bars.length > 0 && (
                    <div className="absolute inset-x-0 bottom-0 flex items-end justify-center" style={{ top: PLOT_TOP, gap: MARK_GAP }}>
                      {bars.map((m) => (
                        <div
                          key={m.key}
                          className={`${barWidth >= 6 ? 'rounded-t-[4px]' : 'rounded-t-[2px]'} transition-[height] duration-300`}
                          style={{ width: barWidth, height: barHeight(p[m.key], barMax), background: m.color }}
                        />
                      ))}
                    </div>
                  )}
                </div>
              ))}

              {lines.length > 0 && size.width > 0 && (
                <svg
                  aria-hidden
                  className="pointer-events-none absolute inset-0 overflow-visible"
                  width={size.width}
                  height={size.height}
                  viewBox={`0 0 ${size.width} ${size.height}`}
                >
                  {linePaths.map(({ metric, segments, pointAt }) => {
                    const hoverPoint = hovered !== null ? pointAt(hovered) : null;
                    return (
                      <g key={metric.key}>
                        {segments.map((segment, s) =>
                          segment.length === 1 ? (
                            <circle key={s} cx={segment[0].x} cy={segment[0].y} r={2} fill={metric.color} />
                          ) : (
                            <polyline
                              key={s}
                              points={segment.map((pt) => `${pt.x},${pt.y}`).join(' ')}
                              fill="none"
                              stroke={metric.color}
                              strokeWidth={2}
                              strokeLinejoin="round"
                              strokeLinecap="round"
                            />
                          ),
                        )}
                        {hoverPoint && (
                          <circle cx={hoverPoint.x} cy={hoverPoint.y} r={4} fill={metric.color} stroke="var(--surface)" strokeWidth={2} />
                        )}
                      </g>
                    );
                  })}
                </svg>
              )}
            </div>

            {active.length === 0 && (
              <p className="pointer-events-none absolute inset-0 grid place-items-center text-sm text-subtle">
                {t('analytics.dashboard.chart.select_metric')}
              </p>
            )}

            {point && active.length > 0 && (
              <div
                ref={tipRef}
                className="pointer-events-none absolute top-2 z-20 min-w-44 rounded-lg border border-line bg-surface px-3 py-2 text-xs shadow-[var(--shadow)]"
                style={{ left: tipLeft }}
              >
                <p className="mb-1.5 whitespace-nowrap font-semibold text-muted">{formatBucket(point.date)}</p>
                {active.map((m) => {
                  const value = point[m.key];
                  return (
                    <div key={m.key} className="flex items-center gap-2 whitespace-nowrap py-0.5">
                      <span aria-hidden className="h-0.5 w-3 shrink-0 rounded-full" style={{ background: m.color }} />
                      <span className="font-bold tabular-nums text-foreground">{value === null ? '—' : m.format(value)}</span>
                      <span className="text-muted">{m.label}</span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          <ChartDateAxis dates={data.map((d) => d.date)} gapClass="gap-px" format={formatAxis} />

          {lines.length > 0 && <p className="mt-3 text-xs text-subtle">{t('analytics.dashboard.chart.scale_hint')}</p>}
        </>
      )}
    </section>
  );
}
