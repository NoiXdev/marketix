import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

export function EmptyState({ icon: Icon, title, hint, action }: { icon: LucideIcon; title: string; hint?: string; action?: ReactNode }) {
  return (
    <div className="border-line-strong bg-surface flex flex-col items-center justify-center rounded-[var(--radius)] border border-dashed py-16 text-center">
      <Icon className="text-subtle mb-3 h-10 w-10" />
      <p className="text-muted text-sm font-medium">{title}</p>
      {hint && <p className="text-subtle mt-1 text-xs">{hint}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
