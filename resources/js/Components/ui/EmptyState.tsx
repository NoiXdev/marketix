import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

export function EmptyState({ icon: Icon, title, hint, action }: { icon: LucideIcon; title: string; hint?: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center rounded-[var(--radius)] border border-dashed border-line-strong bg-surface py-16 text-center">
      <Icon className="mb-3 h-10 w-10 text-subtle" />
      <p className="text-sm font-medium text-muted">{title}</p>
      {hint && <p className="mt-1 text-xs text-subtle">{hint}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
