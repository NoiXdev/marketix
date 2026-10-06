import { ReactNode } from 'react';

export interface RankRow {
  key: string;
  label: string;
  sub?: string;
  note?: string;
  prefix?: ReactNode;
  value: number;
  display?: string;
  onClick?: () => void;
  title?: string;
}

export default function RankedList({ rows, emptyLabel }: { rows: RankRow[]; emptyLabel: string }) {
  const max = Math.max(...rows.map((r) => r.value), 1);
  if (rows.length === 0) return <p className="text-subtle px-4 py-6 text-center text-sm">{emptyLabel}</p>;
  return (
    <div className="p-1.5">
      {rows.map((r) => {
        const content = (
          <>
            {r.prefix && <span className="w-5 shrink-0 text-center">{r.prefix}</span>}
            <div className="min-w-0 flex-1">
              <p className="text-foreground truncate text-[13.5px] font-semibold">{r.label}</p>
              {r.sub && <p className="text-muted truncate font-mono text-[11.5px]">{r.sub}</p>}
              {r.note && <p className="text-muted truncate text-xs">{r.note}</p>}
            </div>
            <div className="text-right">
              <p className="text-foreground text-sm font-bold tabular-nums">{r.display ?? r.value.toLocaleString()}</p>
              <span className="bg-elevated mt-1 block h-1 w-16 overflow-hidden rounded-full">
                <span className="bg-accent block h-full rounded-full" style={{ width: `${(r.value / max) * 100}%` }} />
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
            className="hover:bg-elevated flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
          >
            {content}
          </button>
        ) : (
          <div key={r.key} className="hover:bg-elevated flex items-center gap-3 rounded-lg px-2.5 py-2">
            {content}
          </div>
        );
      })}
    </div>
  );
}
