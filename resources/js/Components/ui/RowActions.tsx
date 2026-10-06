import { ReactNode } from 'react';

export function RowActions({ children }: { children: ReactNode }) {
  return (
    <td className="px-4 py-3">
      <div className="flex items-center justify-end gap-1 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 [@media(hover:hover)]:opacity-0">
        {children}
      </div>
    </td>
  );
}
