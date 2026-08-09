import { ReactNode } from 'react';

export function RowActions({ children }: { children: ReactNode }) {
  return (
    <td className="px-4 py-3">
      <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
        {children}
      </div>
    </td>
  );
}
