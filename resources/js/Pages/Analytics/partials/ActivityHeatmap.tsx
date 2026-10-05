import { useTranslation } from '@/lib/i18n';
import { useState } from 'react';

const HOURS = Array.from({ length: 24 }, (_, h) => h);
const LEGEND_STEPS = [0, 0.25, 0.5, 0.75, 1];

function cellColor(ratio: number) {
  if (ratio <= 0) return 'var(--elevated)';
  return `color-mix(in srgb, var(--accent) ${Math.round(6 + Math.pow(ratio, 1.4) * 94)}%, var(--elevated))`;
}

function hourRange(hour: number) {
  return `${String(hour).padStart(2, '0')}:00 – ${String((hour + 1) % 24).padStart(2, '0')}:00`;
}

export default function ActivityHeatmap({ grid, timezone }: { grid: number[][]; timezone: string }) {
  const { t, locale } = useTranslation();
  const [hovered, setHovered] = useState<{ day: number; hour: number } | null>(null);

  const weekdays = Array.from({ length: 7 }, (_, i) => new Date(2024, 0, 1 + i).toLocaleDateString(locale, { weekday: 'short' }));
  const max = Math.max(0, ...grid.flat());

  let peak = { day: 0, hour: 0, value: 0 };
  grid.forEach((row, day) => row.forEach((value, hour) => {
    if (value > peak.value) peak = { day, hour, value };
  }));

  const focus = hovered ? { ...hovered, value: grid[hovered.day]?.[hovered.hour] ?? 0 } : null;

  return (
    <section className="mb-6 rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
      <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-sm font-semibold text-foreground">{t('analytics.dashboard.heatmap.title')}</h2>
        <p className="text-xs text-muted">
          {focus
            ? t('analytics.dashboard.heatmap.cell', { day: weekdays[focus.day], hours: hourRange(focus.hour), count: focus.value.toLocaleString(locale) })
            : peak.value > 0
              ? t('analytics.dashboard.heatmap.peak', { day: weekdays[peak.day], hours: hourRange(peak.hour) })
              : t('analytics.dashboard.no_data')}
        </p>
      </div>

      <div className="overflow-x-auto">
        <div className="grid min-w-[520px] grid-cols-[2.5rem_repeat(24,minmax(0,1fr))] gap-[3px]" onMouseLeave={() => setHovered(null)}>
          <span />
          {HOURS.map((h) => (
            <span key={h} className="text-center text-[10px] leading-4 text-subtle">
              {h % 3 === 0 ? String(h).padStart(2, '0') : ''}
            </span>
          ))}
          {grid.map((row, day) => (
            <div key={day} className="contents">
              <span className="self-center pr-1 text-[11px] font-semibold text-muted">{weekdays[day]}</span>
              {row.map((value, hour) => (
                <span
                  key={hour}
                  role="img"
                  aria-label={t('analytics.dashboard.heatmap.cell', { day: weekdays[day], hours: hourRange(hour), count: value })}
                  onMouseEnter={() => setHovered({ day, hour })}
                  className={`h-5 rounded-[3px] ${hovered?.day === day && hovered?.hour === hour ? 'ring-2 ring-[color:var(--foreground)]' : ''}`}
                  style={{ background: cellColor(max > 0 ? value / max : 0) }}
                />
              ))}
            </div>
          ))}
        </div>
      </div>

      <div className="mt-3 flex flex-wrap items-center justify-between gap-2 text-[11px] text-subtle">
        <span>{t('analytics.dashboard.heatmap.timezone', { zone: timezone })}</span>
        <span className="inline-flex items-center gap-1.5">
          {t('analytics.dashboard.heatmap.less')}
          {LEGEND_STEPS.map((step) => (
            <span key={step} className="h-3 w-3 rounded-[3px]" style={{ background: cellColor(step) }} />
          ))}
          {t('analytics.dashboard.heatmap.more')}
        </span>
      </div>
    </section>
  );
}
