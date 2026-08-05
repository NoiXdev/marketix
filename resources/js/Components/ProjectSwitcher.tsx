import { PageProps, Project } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, FolderKanban } from 'lucide-react';
import { useRef, useState } from 'react';

export default function ProjectSwitcher() {
  const { projects } = usePage<PageProps>().props;
  const currentProject = usePage().props.project;
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  const switchProject = (project: Project) => {
    setOpen(false);
    router.visit(route('app.project.dashboard', { project: project.id }));
  };

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((o) => !o)}
        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-muted transition-colors hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
      >
        <FolderKanban className="h-4 w-4 shrink-0 text-subtle" />
        <span className="flex-1 truncate text-left">{currentProject?.name}</span>
        <ChevronDown className="h-4 w-4 shrink-0 text-subtle" />
      </button>

      {open && (
        <>
          {/* Backdrop */}
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
          {/* Dropdown */}
          <div className="absolute top-full left-0 z-20 mb-1 w-56 rounded-md border border-line bg-surface py-1 shadow-lg">
            <p className="px-3 py-1.5 text-xs font-semibold tracking-wider text-subtle uppercase">Projects</p>
            {projects.map((p) => (
              <button
                key={p.id}
                onClick={() => switchProject(p)}
                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-muted hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
              >
                <FolderKanban className="h-4 w-4 shrink-0 text-subtle" />
                <span className="flex-1 truncate text-left">{p.name}</span>
                {p.id === currentProject?.id && <Check className="h-4 w-4 text-accent" />}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
