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
        className={`flex w-full items-center gap-2.5 rounded-[var(--radius)] text-left transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
          collapsed ? 'justify-center p-1.5' : 'border-line bg-surface hover:border-line-strong border px-2.5 py-2'
        }`}
      >
        <span className="bg-accent-soft text-accent-soft-foreground flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-[7px] text-xs font-bold">
          {initial(project?.name)}
        </span>
        {!collapsed && (
          <>
            <span className="min-w-0 flex-1">
              <span className="text-subtle block text-[9px] font-bold tracking-wider uppercase">{t('common.projects.label')}</span>
              <span className="text-foreground block truncate text-[13.5px] font-semibold">{project?.name}</span>
            </span>
            <ChevronsUpDown className="text-subtle h-[15px] w-[15px] shrink-0" />
          </>
        )}
      </button>

      {open && (
        <div className="border-line bg-surface absolute top-full left-0 z-30 mt-2 w-56 rounded-xl border p-1.5 shadow-[var(--shadow)]">
          <p className="text-subtle px-2.5 py-1 text-[10.5px] font-bold tracking-wider uppercase">{t('common.projects.label')}</p>
          {projects.map((p) => (
            <button
              key={p.id}
              onClick={() => switchTo(p)}
              className="text-foreground hover:bg-elevated flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            >
              <span className="bg-accent-soft text-accent-soft-foreground flex h-[22px] w-[22px] shrink-0 items-center justify-center rounded-md text-[11px] font-bold">
                {initial(p.name)}
              </span>
              <span className="min-w-0 flex-1 truncate text-left">{p.name}</span>
              {p.id === project?.id && <Check className="text-accent-soft-foreground h-4 w-4 shrink-0" />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
