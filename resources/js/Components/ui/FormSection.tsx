import { ReactNode } from 'react';

export function FormSection({ title, description, children }: { title?: ReactNode; description?: ReactNode; children: ReactNode }) {
  return (
    <div className="border-line bg-surface rounded-[var(--radius)] border p-5">
      {title && <h2 className="text-foreground text-sm font-semibold">{title}</h2>}
      {description && <p className="text-muted mt-1 text-xs">{description}</p>}
      <div className={title || description ? 'mt-4 space-y-4' : 'space-y-4'}>{children}</div>
    </div>
  );
}
