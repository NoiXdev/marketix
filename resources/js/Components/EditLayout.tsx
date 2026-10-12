import { Button } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Trash2 } from 'lucide-react';
import { ReactNode } from 'react';

export function EditLayout({ aside, children }: { aside: ReactNode; children: ReactNode }) {
  return (
    <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
      <div className="min-w-0 space-y-5">{children}</div>
      <aside className="space-y-4 lg:sticky lg:top-6">{aside}</aside>
    </div>
  );
}

export function SidebarSection({ title, description, action, children }: { title: ReactNode; description?: ReactNode; action?: ReactNode; children?: ReactNode }) {
  return (
    <section className="border-line bg-surface rounded-[var(--radius)] border p-5 shadow-[var(--shadow-sm)]">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-foreground text-sm font-semibold">{title}</h2>
        {action}
      </div>
      {description && <p className="text-muted mt-1 text-xs leading-relaxed">{description}</p>}
      {children && <div className="mt-3">{children}</div>}
    </section>
  );
}

export function DangerSection({ title, text, label, onDelete }: { title?: string; text?: string; label?: string; onDelete: () => void }) {
  const { t } = useTranslation();

  return (
    <section className="bg-surface rounded-[var(--radius)] border border-[color:color-mix(in_srgb,var(--danger-foreground)_30%,transparent)] p-5">
      <h2 className="text-danger-foreground text-sm font-semibold">{title ?? t('common.danger.title')}</h2>
      <p className="text-muted mt-1 text-xs leading-relaxed">{text ?? t('common.danger.text')}</p>
      <Button variant="danger" size="sm" className="mt-3" onClick={onDelete}>
        <Trash2 className="h-3.5 w-3.5" />
        {label ?? t('common.actions.delete')}
      </Button>
    </section>
  );
}
