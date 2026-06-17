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
        className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
      >
        <FolderKanban className="h-4 w-4 shrink-0 text-slate-400" />
        <span className="flex-1 truncate text-left">{currentProject?.name}</span>
        <ChevronDown className="h-4 w-4 shrink-0 text-slate-400" />
      </button>

      {open && (
        <>
          {/* Backdrop */}
          <div className="fixed inset-0 z-10" onClick={() => setOpen(false)} />
          {/* Dropdown */}
          <div className="absolute bottom-full left-0 z-20 mb-1 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
            <p className="px-3 py-1.5 text-xs font-semibold tracking-wider text-slate-400 uppercase">Projects</p>
            {projects.map((p) => (
              <button
                key={p.id}
                onClick={() => switchProject(p)}
                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700"
              >
                <FolderKanban className="h-4 w-4 shrink-0 text-slate-400" />
                <span className="flex-1 truncate text-left">{p.name}</span>
                {p.id === currentProject?.id && <Check className="h-4 w-4 text-indigo-600" />}
              </button>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
