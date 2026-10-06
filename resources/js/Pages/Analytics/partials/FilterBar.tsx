import { useTranslation } from '@/lib/i18n';
import { Filter, X } from 'lucide-react';
import { ReactNode } from 'react';

export type ActiveFilter = { key: string; label: string; value: ReactNode };

export default function FilterBar({ items, onRemove, onClear }: { items: ActiveFilter[]; onRemove: (key: string) => void; onClear: () => void }) {
  const { t } = useTranslation();
  if (items.length === 0) return null;

  return (
    <div className="mb-6 flex flex-wrap items-center gap-2">
      <span className="text-muted inline-flex items-center gap-1.5 text-xs font-semibold tracking-wide uppercase">
        <Filter className="h-3.5 w-3.5" />
        {t('analytics.dashboard.filters.title')}
      </span>
      {items.map((item) => (
        <span key={item.key} className="border-line-strong bg-elevated text-foreground inline-flex max-w-full items-center gap-1.5 rounded-lg border py-1 pr-1 pl-2.5 text-[13px]">
          <span className="text-muted">{item.label}:</span>
          <span className="min-w-0 truncate font-semibold">{item.value}</span>
          <button
            type="button"
            onClick={() => onRemove(item.key)}
            aria-label={t('analytics.dashboard.filters.remove', { name: item.label })}
            className="text-muted hover:bg-surface hover:text-foreground grid h-5 w-5 place-items-center rounded focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        </span>
      ))}
      <button type="button" onClick={onClear} className="text-accent-soft-foreground text-xs font-semibold hover:underline">
        {t('analytics.dashboard.filters.clear')}
      </button>
    </div>
  );
}
