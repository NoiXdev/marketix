import { ReactNode } from 'react';
import { Badge } from './Badge';

type Status = 'neutral' | 'success' | 'warning' | 'danger';

const dot: Record<Status, string> = {
  neutral: 'bg-[var(--neutral-dot)]',
  success: 'bg-[var(--success-dot)]',
  warning: 'bg-[var(--warning-dot)]',
  danger: 'bg-[var(--danger-dot)]',
};

export function StatusPill({ status, children }: { status: Status; children: ReactNode }) {
  return (
    <Badge variant={status}>
      <span className={`h-1.5 w-1.5 rounded-full ${dot[status]}`} />
      {children}
    </Badge>
  );
}
