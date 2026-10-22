import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import BreakdownDialog from '@/Pages/Analytics/partials/BreakdownDialog';
import RankedList, { RankRow } from '@/Pages/Dashboard/RankedList';
import { Link } from '@inertiajs/react';
import { ArrowRight, List } from 'lucide-react';
import { ReactNode, useState } from 'react';

const SHOW_ALL_FROM = 8;

export type BreakdownTab = {
  key: string;
  label: string;
  rows: RankRow[];
  unit?: string;
  emptyLabel?: string;
  empty?: ReactNode;
  all?: { dimension: string; toRows: (data: unknown[]) => RankRow[] };
};

export function breakdownTab<T>({
  items,
  toRows,
  dimension,
  ...tab
}: Omit<BreakdownTab, 'rows' | 'all'> & { items: T[]; toRows: (items: T[]) => RankRow[]; dimension?: string }): BreakdownTab {
  return { ...tab, rows: toRows(items), all: dimension ? { dimension, toRows: (data) => toRows(data as T[]) } : undefined };
}

export default function BreakdownCard({
  title,
  tabs,
  emptyLabel,
  more,
  hint,
}: {
  title: string;
  tabs: BreakdownTab[];
  emptyLabel: string;
  more?: { href: string; label: string };
  hint?: string;
}) {
  const { t } = useTranslation();
  const { breakdownUrl } = useAnalytics();
  const [activeKey, setActiveKey] = useState(tabs[0]?.key);
  const [showAll, setShowAll] = useState(false);
  const active = tabs.find((tab) => tab.key === activeKey) ?? tabs[0];
  const canShowAll = active?.all !== undefined && active.rows.length >= SHOW_ALL_FROM;

  return (
    <section className="border-line bg-surface flex flex-col rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
      <div className="border-line flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
        <h2 className="text-foreground text-sm font-semibold">{title}</h2>
        {tabs.length > 1 && (
          <div role="tablist" className="flex flex-wrap gap-1">
            {tabs.map((tab) => (
              <button
                key={tab.key}
                type="button"
                role="tab"
                aria-selected={tab.key === active.key}
                onClick={() => setActiveKey(tab.key)}
                className={`rounded-md px-2 py-1 text-xs font-semibold focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
                  tab.key === active.key ? 'bg-accent-soft text-accent-soft-foreground' : 'text-muted hover:bg-elevated'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        )}
      </div>
      {active?.unit && active.rows.length > 0 && <p className="text-subtle px-4 pt-2 text-right text-[11px] font-semibold">{active.unit}</p>}
      <div role="tabpanel" className="flex-1">
        {active && active.rows.length === 0 && active.empty ? active.empty : <RankedList rows={active?.rows ?? []} emptyLabel={active?.emptyLabel ?? emptyLabel} />}
      </div>
      {hint && <p className="border-line text-subtle border-t px-4 py-3 text-xs">{hint}</p>}
      {(canShowAll || more) && (
        <div className="border-line flex items-center justify-end gap-4 border-t px-4 py-2.5">
          {canShowAll && (
            <button
              type="button"
              onClick={() => setShowAll(true)}
              className="text-accent-soft-foreground inline-flex items-center gap-1 rounded text-xs font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            >
              <List className="h-3.5 w-3.5" />
              {t('analytics.dashboard.list.show_all')}
            </button>
          )}
          {more && (
            <Link href={more.href} className="text-accent-soft-foreground inline-flex items-center gap-1 text-xs font-semibold hover:underline">
              {more.label}
              <ArrowRight className="h-3.5 w-3.5" />
            </Link>
          )}
        </div>
      )}
      {showAll && active?.all && (
        <BreakdownDialog
          onClose={() => setShowAll(false)}
          title={tabs.length > 1 ? `${title} · ${active.label}` : title}
          url={breakdownUrl(active.all.dimension)}
          unit={active.unit}
          emptyLabel={active.emptyLabel ?? emptyLabel}
          toRows={active.all.toRows}
        />
      )}
    </section>
  );
}
