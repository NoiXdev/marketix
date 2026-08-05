import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

const styles = {
  success: 'bg-success-soft text-success-foreground',
  error: 'bg-danger-soft text-danger-foreground',
  warning: 'bg-warning-soft text-warning-foreground',
} as const;

export function Flash() {
  const { flash } = usePage<PageProps>().props;
  const items: [keyof typeof styles, string | undefined][] = [
    ['success', flash?.success],
    ['error', flash?.error],
    ['warning', flash?.warning],
  ];
  const visible = items.filter(([, msg]) => msg);
  if (visible.length === 0) return null;
  return (
    <div className="mb-4 space-y-2">
      {visible.map(([kind, msg]) => (
        <div key={kind} className={`rounded-[var(--radius-sm)] px-4 py-3 text-sm ${styles[kind]}`}>
          {msg}
        </div>
      ))}
    </div>
  );
}
