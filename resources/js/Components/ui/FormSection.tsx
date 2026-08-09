import { ReactNode } from 'react';

export function FormSection({ title, description, children }: { title?: ReactNode; description?: ReactNode; children: ReactNode }) {
  return (
    <div className="rounded-[var(--radius)] border border-line bg-surface p-5">
      {title && <h2 className="text-sm font-semibold text-foreground">{title}</h2>}
      {description && <p className="mt-1 text-xs text-muted">{description}</p>}
      <div className={title || description ? 'mt-4 space-y-4' : 'space-y-4'}>{children}</div>
    </div>
  );
}
