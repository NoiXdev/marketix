import { ReactNode } from 'react';

export interface RankRow { key: string; label: string; sub?: string; prefix?: ReactNode; value: number; onClick?: () => void; title?: string }

export default function RankedList({ rows, emptyLabel }: { rows: RankRow[]; emptyLabel: string }) {
  const max = Math.max(...rows.map((r) => r.value), 1);
  if (rows.length === 0) return <p className="px-4 py-6 text-center text-sm text-subtle">{emptyLabel}</p>;
  return (
    <div className="p-1.5">
      {rows.map((r) => {
        const content = (
          <>
            {r.prefix && <span className="w-5 shrink-0 text-center">{r.prefix}</span>}
            <div className="min-w-0 flex-1">
              <p className="truncate text-[13.5px] font-semibold text-foreground">{r.label}</p>
              {r.sub && <p className="truncate font-mono text-[11.5px] text-muted">{r.sub}</p>}
            </div>
            <div className="text-right">
              <p className="text-sm font-bold tabular-nums text-foreground">{r.value.toLocaleString()}</p>
              <span className="mt-1 block h-1 w-16 overflow-hidden rounded-full bg-elevated">
                <span className="block h-full rounded-full bg-accent" style={{ width: `${(r.value / max) * 100}%` }} />
              </span>
            </div>
          </>
        );

        return r.onClick ? (
          <button
            key={r.key}
            type="button"
            onClick={r.onClick}
            title={r.title}
            className="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
          >
            {content}
          </button>
        ) : (
          <div key={r.key} className="flex items-center gap-3 rounded-lg px-2.5 py-2 hover:bg-elevated">
            {content}
          </div>
        );
      })}
    </div>
  );
}
