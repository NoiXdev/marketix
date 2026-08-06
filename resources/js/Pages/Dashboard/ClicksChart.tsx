import { useTranslation } from '@/lib/i18n';

interface DayClicks { date: string; clicks: number; unique: number }

export default function ClicksChart({ data }: { data: DayClicks[] }) {
  const { t } = useTranslation();
  const max = Math.max(...data.map((d) => Math.max(d.clicks, d.unique)), 1);
  return (
    <div className="flex h-[170px] items-end gap-[3px]">
      {data.map((d) => (
        <div key={d.date} className="group relative flex flex-1 flex-col-reverse gap-[2px]">
          <div
            className="rounded-t-[3px] bg-[color:color-mix(in_srgb,var(--accent)_40%,var(--surface))]"
            style={{ height: `${Math.max((d.unique / max) * 100, d.unique > 0 ? 4 : 0)}%` }}
            title={`${d.date}: ${d.clicks} · ${d.unique}`}
          />
          <div
            className="rounded-t-[3px] bg-accent"
            style={{ height: `${Math.max(((d.clicks - d.unique) / max) * 100, d.clicks - d.unique > 0 ? 4 : 0)}%` }}
            title={`${d.date}: ${d.clicks} · ${d.unique}`}
          />
          <div className="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs text-canvas shadow-[var(--shadow)] group-hover:block">
            <p className="text-subtle">{d.date}</p>
            <p className="font-semibold">{d.clicks.toLocaleString()} · {d.unique.toLocaleString()} {t('common.dashboard.unique')}</p>
          </div>
        </div>
      ))}
    </div>
  );
}
