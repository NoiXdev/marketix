import ScaleLegend from '@/Components/ScaleLegend';
import { scrubHandlers } from '@/lib/chartScrub';
import { scaleFill } from '@/lib/colorScale';
import { browserTimeZone, HourBucket, weekdayHourGrid } from '@/lib/heatmap';
import { useTranslation } from '@/lib/i18n';
import { useMemo, useState } from 'react';

const HOURS = Array.from({ length: 24 }, (_, h) => h);

function hourRange(hour: number) {
  return `${String(hour).padStart(2, '0')}:00 – ${String((hour + 1) % 24).padStart(2, '0')}:00`;
}

export default function ActivityHeatmap({ buckets }: { buckets: HourBucket[] }) {
  const { t, locale } = useTranslation();
  const [hovered, setHovered] = useState<{ day: number; hour: number } | null>(null);
  const timezone = useMemo(() => browserTimeZone(), []);
  const grid = useMemo(() => weekdayHourGrid(buckets, timezone), [buckets, timezone]);

  const weekdays = Array.from({ length: 7 }, (_, i) => new Date(2024, 0, 1 + i).toLocaleDateString(locale, { weekday: 'short' }));
  const max = Math.max(0, ...grid.flat());

  let peak = { day: 0, hour: 0, value: 0 };
  grid.forEach((row, day) =>
    row.forEach((value, hour) => {
      if (value > peak.value) peak = { day, hour, value };
    }),
  );

  const focus = hovered ? { ...hovered, value: grid[hovered.day]?.[hovered.hour] ?? 0 } : null;

  return (
    <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border p-4 shadow-[var(--shadow-sm)] sm:p-6">
      <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-foreground text-sm font-semibold">{t('analytics.dashboard.heatmap.title')}</h2>
        <p className="text-muted text-xs">
          {focus
            ? t('analytics.dashboard.heatmap.cell', { day: weekdays[focus.day], hours: hourRange(focus.hour), count: focus.value.toLocaleString(locale) })
            : peak.value > 0
              ? t('analytics.dashboard.heatmap.peak', { day: weekdays[peak.day], hours: hourRange(peak.hour) })
              : t('analytics.dashboard.no_data')}
        </p>
      </div>

      {/* All 24 hours fit the width, so phones see the whole day without scrolling */}
      <div
        className="grid touch-pan-y grid-cols-[2rem_repeat(24,minmax(0,1fr))] gap-[2px] sm:grid-cols-[2.5rem_repeat(24,minmax(0,1fr))] sm:gap-[3px]"
        onMouseLeave={() => setHovered(null)}
        {...scrubHandlers((slot) => {
          const [day, hour] = slot.split(':').map(Number);
          setHovered({ day, hour });
        })}
      >
        <span />
        {HOURS.map((h) => (
          <span key={h} className="text-subtle text-center text-[10px] leading-4">
            {h % 3 === 0 && <span className={h % 6 === 0 ? '' : 'hidden sm:inline'}>{String(h).padStart(2, '0')}</span>}
          </span>
        ))}
        {grid.map((row, day) => (
          <div key={day} className="contents">
            <span className="text-muted self-center pr-1 text-[11px] font-semibold">{weekdays[day]}</span>
            {row.map((value, hour) => (
              <span
                key={hour}
                role="img"
                aria-label={t('analytics.dashboard.heatmap.cell', { day: weekdays[day], hours: hourRange(hour), count: value })}
                data-slot={`${day}:${hour}`}
                className={`h-4 rounded-[2px] sm:h-5 sm:rounded-[3px] ${hovered?.day === day && hovered?.hour === hour ? 'ring-2 ring-[color:var(--foreground)] ring-inset' : ''}`}
                style={{ background: scaleFill(value, max) }}
              />
            ))}
          </div>
        ))}
      </div>

      <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
        {max > 0 ? <ScaleLegend /> : <span />}
        <span className="text-subtle text-xs">{t('analytics.dashboard.heatmap.timezone', { zone: timezone })}</span>
      </div>
    </section>
  );
}
