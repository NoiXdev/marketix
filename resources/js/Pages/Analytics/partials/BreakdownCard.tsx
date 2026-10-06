import RankedList, { RankRow } from '@/Pages/Dashboard/RankedList';
import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { ReactNode, useState } from 'react';

export type BreakdownTab = { key: string; label: string; rows: RankRow[]; emptyLabel?: string; empty?: ReactNode };

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
  const [activeKey, setActiveKey] = useState(tabs[0]?.key);
  const active = tabs.find((tab) => tab.key === activeKey) ?? tabs[0];

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
      <div role="tabpanel" className="flex-1">
        {active && active.rows.length === 0 && active.empty ? active.empty : <RankedList rows={active?.rows ?? []} emptyLabel={active?.emptyLabel ?? emptyLabel} />}
      </div>
      {hint && <p className="border-line text-subtle border-t px-4 py-3 text-xs">{hint}</p>}
      {more && (
        <Link
          href={more.href}
          className="border-line text-accent-soft-foreground hover:bg-elevated inline-flex items-center justify-end gap-1 rounded-b-[var(--radius)] border-t px-4 py-2.5 text-xs font-semibold"
        >
          {more.label}
          <ArrowRight className="h-3.5 w-3.5" />
        </Link>
      )}
    </section>
  );
}
