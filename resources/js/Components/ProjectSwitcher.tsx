import { useTranslation } from '@/lib/i18n';
import { PageProps, Project } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

function initial(name?: string): string {
  return (name ?? '?').trim().charAt(0).toUpperCase() || '?';
}

export default function ProjectSwitcher({ collapsed = false }: { collapsed?: boolean }) {
  const { projects, project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') setOpen(false);
    }
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  }, []);

  function switchTo(p: Project) {
    setOpen(false);
    router.visit(route('app.project.dashboard', { project: p.id }));
  }

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="true"
        className={`flex w-full items-center gap-2.5 rounded-[var(--radius)] text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
          collapsed ? 'justify-center p-1.5' : 'border border-line bg-surface px-2.5 py-2 hover:border-line-strong'
        }`}
      >
        <span className="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-[7px] bg-accent-soft text-xs font-bold text-accent-soft-foreground">
          {initial(project?.name)}
        </span>
        {!collapsed && (
          <>
            <span className="min-w-0 flex-1">
              <span className="block text-[9px] font-bold uppercase tracking-wider text-subtle">{t('common.projects.label')}</span>
              <span className="block truncate text-[13.5px] font-semibold text-foreground">{project?.name}</span>
            </span>
            <ChevronsUpDown className="h-[15px] w-[15px] shrink-0 text-subtle" />
          </>
        )}
      </button>

      {open && (
        <div className="absolute left-0 top-full z-30 mt-2 w-56 rounded-xl border border-line bg-surface p-1.5 shadow-[var(--shadow)]">
          <p className="px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wider text-subtle">{t('common.projects.label')}</p>
          {projects.map((p) => (
            <button
              key={p.id}
              onClick={() => switchTo(p)}
              className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-foreground transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
            >
              <span className="flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-md bg-accent-soft text-[11px] font-bold text-accent-soft-foreground">
                {initial(p.name)}
              </span>
              <span className="min-w-0 flex-1 truncate text-left">{p.name}</span>
              {p.id === project?.id && <Check className="h-4 w-4 shrink-0 text-accent-soft-foreground" />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
