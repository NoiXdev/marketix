import { useTranslation } from '@/lib/i18n';
import { Filter, X } from 'lucide-react';
import { ReactNode } from 'react';

export type ActiveFilter = { key: string; label: string; value: ReactNode };

export default function FilterBar({ items, onRemove, onClear }: { items: ActiveFilter[]; onRemove: (key: string) => void; onClear: () => void }) {
  const { t } = useTranslation();
  if (items.length === 0) return null;

  return (
    <div className="mb-6 flex flex-wrap items-center gap-2">
      <span className="inline-flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted">
        <Filter className="h-3.5 w-3.5" />
        {t('analytics.dashboard.filters.title')}
      </span>
      {items.map((item) => (
        <span
          key={item.key}
          className="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-line-strong bg-elevated py-1 pl-2.5 pr-1 text-[13px] text-foreground"
        >
          <span className="text-muted">{item.label}:</span>
          <span className="min-w-0 truncate font-semibold">{item.value}</span>
          <button
            type="button"
            onClick={() => onRemove(item.key)}
            aria-label={t('analytics.dashboard.filters.remove', { name: item.label })}
            className="grid h-5 w-5 place-items-center rounded text-muted hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </span>
      ))}
      <button type="button" onClick={onClear} className="text-xs font-semibold text-accent-soft-foreground hover:underline">
        {t('analytics.dashboard.filters.clear')}
      </button>
    </div>
  );
}
