import { ReactNode } from 'react';

export function TableCard({ columns, children }: { columns: { label: ReactNode; align?: 'left' | 'right' }[]; children: ReactNode }) {
  return (
    <div className="overflow-hidden rounded-[var(--radius)] border border-line bg-surface">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-line">
            {columns.map((col, i) => (
              <th
                key={i}
                className={`px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted ${col.align === 'right' ? 'text-right' : 'text-left'}`}
              >
                {col.label}
              </th>
            ))}
          </tr>
        </thead>
        {children}
      </table>
    </div>
  );
}
