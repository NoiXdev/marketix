import ChartDateAxis from '@/Pages/Dashboard/ChartDateAxis';
import { useTranslation } from '@/lib/i18n';

interface DayClicks {
  date: string;
  clicks: number;
  unique: number;
}

export default function ClicksChart({ data, fill }: { data: DayClicks[]; fill?: boolean }) {
  const { t } = useTranslation();
  const max = Math.max(...data.map((d) => Math.max(d.clicks, d.unique)), 1);

  // `fill` makes the chart grow to its parent's height (dashboard widget cell);
  // the default keeps the fixed 170px bar area used on the full-width pages.
  // `pt-7` reserves headroom so the hover tooltip stays inside the container.
  const outerClass = fill ? 'flex h-full flex-col' : '';
  const barsClass = fill ? 'flex min-h-0 flex-1 items-end gap-[3px] pt-9' : 'flex h-[170px] items-end gap-[3px]';

  return (
    <div className={outerClass}>
      <div className={barsClass}>
        {data.map((d) => (
          <div key={d.date} className="group relative flex h-full flex-1 flex-col-reverse gap-[2px]">
            <div
              className="rounded-t-[3px] bg-[color:color-mix(in_srgb,var(--accent)_40%,var(--surface))]"
              style={{ height: `${Math.max((d.unique / max) * 100, d.unique > 0 ? 4 : 0)}%` }}
              title={`${d.date}: ${d.clicks} · ${d.unique}`}
            />
            <div
              className="bg-accent rounded-t-[3px]"
              style={{ height: `${Math.max(((d.clicks - d.unique) / max) * 100, d.clicks - d.unique > 0 ? 4 : 0)}%` }}
              title={`${d.date}: ${d.clicks} · ${d.unique}`}
            />
            <div className="bg-foreground text-canvas pointer-events-none absolute bottom-full left-1/2 z-20 mb-1 hidden -translate-x-1/2 rounded-md px-2 py-1 text-xs whitespace-nowrap shadow-[var(--shadow)] group-hover:block">
              <p className="text-subtle">{d.date}</p>
              <p className="font-semibold">
                {d.clicks.toLocaleString()} · {d.unique.toLocaleString()} {t('common.dashboard.unique')}
              </p>
            </div>
          </div>
        ))}
      </div>
      <ChartDateAxis dates={data.map((d) => d.date)} gapClass="gap-[3px]" />
    </div>
  );
}
