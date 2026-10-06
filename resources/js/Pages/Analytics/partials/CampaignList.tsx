import { CampaignRow } from '@/Pages/Analytics/types';

export default function CampaignList({ title, rows, emptyLabel, onSelect }: { title: string; rows: CampaignRow[]; emptyLabel: string; onSelect?: (value: string) => void }) {
  return (
    <section className="border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
      <div className="border-line border-b px-4 py-3">
        <h2 className="text-foreground text-sm font-semibold">{title}</h2>
      </div>
      {rows.length === 0 ? (
        <p className="text-subtle px-4 py-6 text-center text-sm">{emptyLabel}</p>
      ) : (
        <ul className="p-1.5">
          {rows.map((r, i) => {
            const content = (
              <>
                <span className="text-foreground truncate text-[13.5px] font-semibold">{r.value}</span>
                <span className="text-foreground text-sm font-bold whitespace-nowrap tabular-nums">
                  {r.sessions.toLocaleString()}
                  <span className="text-subtle ml-1 text-xs font-normal">({r.visitors.toLocaleString()})</span>
                </span>
              </>
            );
            return (
              <li key={i}>
                {onSelect ? (
                  <button
                    type="button"
                    onClick={() => onSelect(r.value)}
                    className="hover:bg-elevated flex w-full items-center justify-between gap-3 rounded-lg px-2.5 py-2 text-left focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
                  >
                    {content}
                  </button>
                ) : (
                  <div className="hover:bg-elevated flex items-center justify-between gap-3 rounded-lg px-2.5 py-2">{content}</div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
