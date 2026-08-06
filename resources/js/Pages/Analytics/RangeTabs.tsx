import { useTranslation } from '@/lib/i18n';

export default function RangeTabs({
  days,
  onChange,
  ranges = [1, 7, 30, 90],
}: {
  days: number;
  onChange: (d: number) => void;
  ranges?: number[];
}) {
  const { t } = useTranslation();
  return (
    <div className="inline-flex overflow-hidden rounded-lg border border-line">
      {ranges.map((d) => (
        <button
          key={d}
          onClick={() => onChange(d)}
          className={`border-r border-line px-3 py-1.5 text-sm font-semibold last:border-r-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
            days === d ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'
          }`}
        >
          {d === 1 ? t('analytics.dashboard.range_today') : `${d}${t('common.dashboard.range_days')}`}
        </button>
      ))}
    </div>
  );
}
