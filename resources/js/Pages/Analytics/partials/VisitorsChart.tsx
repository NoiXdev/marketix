import { Checkbox } from '@/Components/ui';
import { formatAxisLabel, formatBucket as formatBucketLabel, Interval, spansSeveralDays } from '@/Pages/Analytics/partials/period';
import ChartDateAxis from '@/Pages/Dashboard/ChartDateAxis';
import { scrubHandlers } from '@/lib/chartScrub';
import { formatDuration, percentChange } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { KeyboardEvent, useEffect, useLayoutEffect, useRef, useState } from 'react';

export type SeriesPoint = {
  date: string;
  views: number;
  visitors: number;
  sessions: number;
  bounce_rate: number | null;
  engagement_rate: number | null;
  avg_duration: number | null;
  campaign_share: number | null;
};

type MetricKey = 'views' | 'visitors' | 'engagement_rate' | 'bounce_rate' | 'avg_duration' | 'campaign_share';

type Metric = {
  key: MetricKey;
  kind: 'bar' | 'line';
  color: string;
  label: string;
  format: (value: number) => string;
  lowerIsBetter?: boolean;
};

type PlotPoint = { x: number; y: number };

const STORAGE_KEY = 'marketix.analytics.chart-metrics';
const COMPARISON_KEY = 'marketix.analytics.chart-comparison';
const METRIC_KEYS: MetricKey[] = ['views', 'visitors', 'engagement_rate', 'bounce_rate', 'avg_duration', 'campaign_share'];
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

function loadComparison(): boolean {
  try {
    return window.localStorage.getItem(COMPARISON_KEY) !== 'off';
  } catch {
    return true;
  }
}

function saveComparison(on: boolean) {
  try {
    window.localStorage.setItem(COMPARISON_KEY, on ? 'on' : 'off');
  } catch {
    return;
  }
}

function Trace({ segments, color, dashed = false }: { segments: PlotPoint[][]; color: string; dashed?: boolean }) {
  return (
    <>
      {segments.map((segment, s) =>
        segment.length === 1 ? (
          <circle key={s} cx={segment[0].x} cy={segment[0].y} r={dashed ? 1.5 : 2} fill={color} />
        ) : (
          <polyline
            key={s}
            points={segment.map((pt) => `${pt.x},${pt.y}`).join(' ')}
            fill="none"
            stroke={color}
            strokeWidth={dashed ? 1.5 : 2}
            strokeDasharray={dashed ? '4 3' : undefined}
            strokeLinejoin="round"
            strokeLinecap="round"
          />
        ),
      )}
    </>
  );
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
  previous = null,
  comparisonRange = null,
  interval,
  intervals,
  onIntervalChange,
}: {
  title: string;
  data: SeriesPoint[];
  previous?: SeriesPoint[] | null;
  comparisonRange?: string | null;
  interval: Interval;
  intervals: Interval[];
  onIntervalChange: (interval: Interval) => void;
}) {
  const { t, locale } = useTranslation();
  const [selection, setSelection] = useState<MetricKey[]>(loadSelection);
  const [showComparison, setShowComparison] = useState(loadComparison);
  const [hovered, setHovered] = useState<number | null>(null);
  const [showTable, setShowTable] = useState(false);
  const [size, setSize] = useState({ width: 0, height: 0 });
  const [tipLeft, setTipLeft] = useState(0);
  const plotRef = useRef<HTMLDivElement>(null);
  const tipRef = useRef<HTMLDivElement>(null);

  const multiDay = spansSeveralDays(data.map((d) => d.date));
  const count = (n: number) => n.toLocaleString(locale);
  const percent = (n: number) => `${n.toLocaleString(locale)} %`;

  const metrics: Metric[] = [
    { key: 'views', kind: 'bar', color: 'var(--chart-views)', label: t('analytics.dashboard.kpi.page_views'), format: count },
    { key: 'visitors', kind: 'bar', color: 'var(--chart-visitors)', label: t('analytics.dashboard.kpi.unique_visitors'), format: count },
    { key: 'engagement_rate', kind: 'line', color: 'var(--chart-engagement)', label: t('analytics.dashboard.kpi.engagement_rate'), format: percent },
    { key: 'bounce_rate', kind: 'line', color: 'var(--chart-bounce)', label: t('analytics.dashboard.kpi.bounce_rate'), format: percent, lowerIsBetter: true },
    { key: 'avg_duration', kind: 'line', color: 'var(--chart-duration)', label: t('analytics.dashboard.kpi.avg_duration'), format: formatDuration },
    { key: 'campaign_share', kind: 'line', color: 'var(--chart-campaign)', label: t('analytics.dashboard.kpi.from_campaigns'), format: percent },
  ];

  const active = metrics.filter((m) => selection.includes(m.key));
  const bars = active.filter((m) => m.kind === 'bar');
  const lines = active.filter((m) => m.kind === 'line');

  const hasComparison = previous !== null && previous.length > 0;
  const comparison = hasComparison && showComparison ? previous.slice(0, data.length) : null;
  const scaled = comparison ? [...data, ...comparison] : data;

  const barMax = Math.max(0, ...scaled.flatMap((p) => bars.map((m) => p[m.key] ?? 0)));

  const slotWidth = data.length > 0 ? (size.width - SLOT_GAP * (data.length - 1)) / data.length : 0;
  const barWidth = bars.length > 0 ? clamp((slotWidth * 0.72 - MARK_GAP * (bars.length - 1)) / bars.length, 2, 24) : 0;
  const innerHeight = Math.max(0, size.height - PLOT_TOP);
  const slotCenter = (i: number) => i * (slotWidth + SLOT_GAP) + slotWidth / 2;

  const xFor = (m: Metric, i: number) => slotCenter(i) + (m.kind === 'bar' ? (bars.indexOf(m) - (bars.length - 1) / 2) * (barWidth + MARK_GAP) : 0);
  const yScale = (m: Metric) => {
    const max = m.kind === 'bar' ? barMax : Math.max(0, ...scaled.map((p) => p[m.key] ?? 0));
    return (value: number) => PLOT_TOP + (max > 0 ? 1 - value / max : 1) * innerHeight;
  };

  function trace(m: Metric, points: SeriesPoint[]): PlotPoint[][] {
    const y = yScale(m);
    const segments: PlotPoint[][] = [];
    let current: PlotPoint[] = [];
    points.forEach((p, i) => {
      const value = p[m.key];
      if (value === null) {
        if (current.length > 0) segments.push(current);
        current = [];
        return;
      }
      current.push({ x: xFor(m, i), y: y(value) });
    });
    if (current.length > 0) segments.push(current);
    return segments;
  }

  function pointAt(m: Metric, points: SeriesPoint[] | null, i: number): PlotPoint | null {
    const value = points?.[i]?.[m.key];
    return value === null || value === undefined ? null : { x: xFor(m, i), y: yScale(m)(value) };
  }

  const linePaths = lines.map((m) => ({ metric: m, segments: trace(m, data) }));
  const comparisonPaths = comparison ? active.map((m) => ({ metric: m, segments: trace(m, comparison) })) : [];

  useEffect(() => saveSelection(selection), [selection]);
  useEffect(() => saveComparison(showComparison), [showComparison]);

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
  }, [hovered, size.width, slotWidth, selection, showComparison]);

  function toggle(key: MetricKey) {
    setSelection((current) => (current.includes(key) ? current.filter((k) => k !== key) : METRIC_KEYS.filter((k) => k === key || current.includes(k))));
  }

  const formatBucket = (key: string) => formatBucketLabel(key, interval, multiDay, locale, (date) => t('analytics.dashboard.chart.week_of', { date }));
  const formatAxis = (key: string) => formatAxisLabel(key, interval, multiDay, locale);

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
  const previousPoint = hovered !== null ? (comparison?.[hovered] ?? null) : null;
  const sameHeadings = point && previousPoint && formatBucket(point.date) === formatBucket(previousPoint.date);
  const currentHeading = point ? (sameHeadings ? t('analytics.dashboard.chart.current') : formatBucket(point.date)) : '';
  const previousHeading = previousPoint ? (sameHeadings ? t('analytics.dashboard.chart.previous') : formatBucket(previousPoint.date)) : '';

  return (
    <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border p-4 shadow-[var(--shadow-sm)] sm:p-6">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 className="text-foreground text-sm font-semibold">{title}</h2>
        <div className="flex flex-wrap items-center gap-3">
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
          <button
            type="button"
            onClick={() => setShowTable((v) => !v)}
            className="text-accent-soft-foreground text-xs font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
          >
            {showTable ? t('analytics.dashboard.chart.show_chart') : t('analytics.dashboard.chart.show_table')}
          </button>
        </div>
      </div>

      <div className="mb-5 flex flex-wrap gap-1.5 sm:gap-2">
        {metrics.map((m) => {
          const on = selection.includes(m.key);
          return (
            <label
              key={m.key}
              className={`inline-flex cursor-pointer items-center gap-1.5 rounded-lg border px-2 py-1 text-xs font-semibold transition-colors select-none sm:gap-2 sm:px-2.5 sm:py-1.5 sm:text-[13px] ${
                on ? 'border-line-strong bg-elevated text-foreground' : 'border-line text-muted hover:bg-elevated'
              }`}
            >
              <Checkbox checked={on} onChange={() => toggle(m.key)} />
              <span aria-hidden className={m.kind === 'bar' ? 'h-3 w-2.5 rounded-t-[2px]' : 'h-0.5 w-4 rounded-full'} style={{ background: m.color }} />
              {m.label}
            </label>
          );
        })}
        {hasComparison && (
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
        )}
      </div>

      {showTable ? (
        <div className="border-line max-h-96 overflow-auto rounded-lg border">
          <table className="w-full text-sm">
            <thead className="bg-surface sticky top-0">
              <tr>
                <th className="text-muted px-3 py-2 text-left text-xs font-semibold">{t(`analytics.dashboard.interval.${interval}`)}</th>
                {metrics.map((m) => (
                  <th key={m.key} className="text-muted px-3 py-2 text-right text-xs font-semibold whitespace-nowrap">
                    {m.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {[...data].reverse().map((p) => (
                <tr key={p.date} className="border-line border-t">
                  <td className="text-muted px-3 py-1.5 whitespace-nowrap">{formatBucket(p.date)}</td>
                  {metrics.map((m) => {
                    const value = p[m.key];
                    return (
                      <td key={m.key} className="text-foreground px-3 py-1.5 text-right font-semibold tabular-nums">
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
              {...scrubHandlers((slot) => setHovered(Number(slot)))}
              className="border-line relative flex h-48 touch-pan-y border-b focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
              style={{ gap: SLOT_GAP }}
            >
              {data.map((p, i) => (
                <div key={p.date} data-slot={i} className={`relative h-full min-w-0 flex-1 rounded-t-[4px] ${hovered === i ? 'bg-elevated' : ''}`}>
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

              {(lines.length > 0 || comparisonPaths.length > 0) && size.width > 0 && (
                <svg
                  aria-hidden
                  className="pointer-events-none absolute inset-0 overflow-visible"
                  width={size.width}
                  height={size.height}
                  viewBox={`0 0 ${size.width} ${size.height}`}
                >
                  {comparisonPaths.map(({ metric, segments }) => {
                    const hoverPoint = hovered !== null ? pointAt(metric, comparison, hovered) : null;
                    return (
                      <g key={`previous-${metric.key}`}>
                        <Trace segments={segments} color={metric.color} dashed />
                        {hoverPoint && <circle cx={hoverPoint.x} cy={hoverPoint.y} r={3.5} fill="var(--surface)" stroke={metric.color} strokeWidth={1.5} />}
                      </g>
                    );
                  })}
                  {linePaths.map(({ metric, segments }) => {
                    const hoverPoint = hovered !== null ? pointAt(metric, data, hovered) : null;
                    return (
                      <g key={metric.key}>
                        <Trace segments={segments} color={metric.color} />
                        {hoverPoint && <circle cx={hoverPoint.x} cy={hoverPoint.y} r={4} fill={metric.color} stroke="var(--surface)" strokeWidth={2} />}
                      </g>
                    );
                  })}
                </svg>
              )}
            </div>

            {active.length === 0 && (
              <p className="text-subtle pointer-events-none absolute inset-0 grid place-items-center text-sm">{t('analytics.dashboard.chart.select_metric')}</p>
            )}

            {point && active.length > 0 && (
              <div
                ref={tipRef}
                className="border-line bg-surface pointer-events-none absolute top-2 z-20 max-w-full min-w-44 rounded-lg border px-3 py-2 text-xs shadow-[var(--shadow)]"
                style={{ left: tipLeft }}
              >
                {previousPoint ? (
                  <table>
                    <thead>
                      <tr className="text-muted">
                        <th />
                        <th className="pb-1 pl-3 text-right align-bottom font-semibold sm:pl-4">{currentHeading}</th>
                        <th className="pb-1 pl-3 text-right align-bottom font-semibold sm:pl-4">{previousHeading}</th>
                        <th className="pb-1 pl-3 text-right align-bottom font-semibold sm:pl-4">{t('analytics.dashboard.chart.change')}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {active.map((m) => {
                        const value = point[m.key];
                        const before = previousPoint[m.key];
                        const change = value !== null && before !== null ? percentChange(value, before) : null;
                        const good = change !== null && (m.lowerIsBetter ? change < 0 : change > 0);
                        return (
                          <tr key={m.key}>
                            <td className="py-0.5">
                              <span className="text-muted inline-flex items-center gap-2">
                                <span aria-hidden className="h-0.5 w-3 shrink-0 rounded-full" style={{ background: m.color }} />
                                {m.label}
                              </span>
                            </td>
                            <td className="text-foreground pl-3 text-right font-bold whitespace-nowrap tabular-nums sm:pl-4">{value === null ? '—' : m.format(value)}</td>
                            <td className="text-muted pl-3 text-right whitespace-nowrap tabular-nums sm:pl-4">{before === null ? '—' : m.format(before)}</td>
                            <td
                              className={`pl-3 text-right font-bold whitespace-nowrap tabular-nums sm:pl-4 ${change === null || change === 0 ? 'text-muted' : good ? 'text-success-foreground' : 'text-danger-foreground'}`}
                            >
                              {change === null ? '—' : change === 0 ? '± 0 %' : `${change > 0 ? '▲' : '▼'} ${Math.abs(change).toLocaleString(locale)} %`}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                ) : (
                  <>
                    <p className="text-muted mb-1.5 font-semibold whitespace-nowrap">{formatBucket(point.date)}</p>
                    {active.map((m) => {
                      const value = point[m.key];
                      return (
                        <div key={m.key} className="flex items-center gap-2 py-0.5 whitespace-nowrap">
                          <span aria-hidden className="h-0.5 w-3 shrink-0 rounded-full" style={{ background: m.color }} />
                          <span className="text-foreground font-bold tabular-nums">{value === null ? '—' : m.format(value)}</span>
                          <span className="text-muted">{m.label}</span>
                        </div>
                      );
                    })}
                  </>
                )}
              </div>
            )}
          </div>

          <ChartDateAxis dates={data.map((d) => d.date)} gapClass="gap-px" format={formatAxis} />

          {(lines.length > 0 || comparison) && (
            <p className="text-subtle mt-3 text-xs">
              {[comparison && active.length > 0 ? t('analytics.dashboard.chart.comparison_hint') : null, lines.length > 0 ? t('analytics.dashboard.chart.scale_hint') : null]
                .filter(Boolean)
                .join(' ')}
            </p>
          )}
        </>
      )}
    </section>
  );
}
