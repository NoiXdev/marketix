import { PropsWithChildren } from 'react';

type Variant = 'neutral' | 'success' | 'warning' | 'danger' | 'accent';

const map: Record<Variant, string> = {
  neutral: 'bg-neutral-soft text-neutral-foreground',
  success: 'bg-success-soft text-success-foreground',
  warning: 'bg-warning-soft text-warning-foreground',
  danger: 'bg-danger-soft text-danger-foreground',
  accent: 'bg-accent-soft text-accent-soft-foreground',
};

export function Badge({
  variant = 'neutral',
  className = '',
  children,
}: PropsWithChildren<{ variant?: Variant; className?: string }>) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ${map[variant]} ${className}`}
    >
      {children}
    </span>
  );
}
