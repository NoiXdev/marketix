import RankedList, { RankRow } from '@/Pages/Dashboard/RankedList';
import { useState } from 'react';

export type BreakdownTab = { key: string; label: string; rows: RankRow[] };

export default function BreakdownCard({ title, tabs, emptyLabel }: { title: string; tabs: BreakdownTab[]; emptyLabel: string }) {
  const [activeKey, setActiveKey] = useState(tabs[0]?.key);
  const active = tabs.find((tab) => tab.key === activeKey) ?? tabs[0];

  return (
    <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
        <h2 className="text-sm font-semibold text-foreground">{title}</h2>
        {tabs.length > 1 && (
          <div role="tablist" className="flex flex-wrap gap-1">
            {tabs.map((tab) => (
              <button
                key={tab.key}
                type="button"
                role="tab"
                aria-selected={tab.key === active.key}
                onClick={() => setActiveKey(tab.key)}
                className={`rounded-md px-2 py-1 text-xs font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                  tab.key === active.key ? 'bg-accent-soft text-accent-soft-foreground' : 'text-muted hover:bg-elevated'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        )}
      </div>
      <div role="tabpanel">
        <RankedList rows={active?.rows ?? []} emptyLabel={emptyLabel} />
      </div>
    </section>
  );
}
