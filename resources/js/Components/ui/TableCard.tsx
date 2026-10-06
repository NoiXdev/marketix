import { ReactNode } from 'react';

export function TableCard({ columns, children }: { columns: { label: ReactNode; align?: 'left' | 'right'; className?: string }[]; children: ReactNode }) {
  return (
    <div className="border-line bg-surface overflow-x-auto rounded-[var(--radius)] border">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-line border-b">
            {columns.map((col, i) => (
              <th
                key={i}
                className={`text-muted px-4 py-3 text-xs font-semibold tracking-wider uppercase ${col.align === 'right' ? 'text-right' : 'text-left'} ${col.className ?? ''}`}
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
