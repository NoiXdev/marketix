import { useTranslation } from '@/lib/i18n';

export default function RangeTabs({ days, onChange, ranges = [1, 7, 30, 90] }: { days: number; onChange: (d: number) => void; ranges?: number[] }) {
  const { t } = useTranslation();
  return (
    <div className="border-line inline-flex overflow-hidden rounded-lg border">
      {ranges.map((d) => (
        <button
          key={d}
          onClick={() => onChange(d)}
          className={`border-line border-r px-3 py-1.5 text-sm font-semibold last:border-r-0 focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
            days === d ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'
          }`}
        >
          {d === 1 ? t('analytics.dashboard.range_today') : `${d}${t('common.dashboard.range_days')}`}
        </button>
      ))}
    </div>
  );
}
