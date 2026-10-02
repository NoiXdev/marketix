import { useTranslation } from '@/lib/i18n';
import type { Widget as W } from '@/lib/widgets/schema';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, LayoutGrid, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface DashboardMeta {
  id: string;
  name: string;
  is_default: boolean;
  position: number;
}

interface ActiveDashboard {
  id: string;
  name: string;
  widgets: W[];
}

interface Props {
  dashboards: DashboardMeta[];
  active: ActiveDashboard;
}

export default function DashboardSwitcher({ dashboards, active }: Props) {
  const project = usePage<PageProps>().props.project!;
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

  function switchTo(d: DashboardMeta) {
    setOpen(false);
    if (d.id === active.id) return;
    router.get(route('app.project.dashboard', { project: project.id, dashboard: d.id }), {}, { preserveState: false });
  }

  function createDashboard() {
    const name = window.prompt(t('dashboards.switcher.new_prompt'));
    if (!name || !name.trim()) return;
    setOpen(false);
    router.post(route('app.project.dashboards.store', { project: project.id }), { name: name.trim() }, { preserveState: false });
  }

  function renameActive() {
    const name = window.prompt(t('dashboards.switcher.rename_prompt'), active.name);
    if (!name || !name.trim() || name.trim() === active.name) return;
    setOpen(false);
    router.put(
      route('app.project.dashboards.update', { project: project.id, dashboard: active.id }),
      // active.widgets is already plain JSON-safe data; round-trip it so its
      // structural type matches Inertia's FormDataConvertible payload shape.
      { name: name.trim(), widgets: JSON.parse(JSON.stringify(active.widgets)) },
      { preserveState: false },
    );
  }

  function destroy(d: DashboardMeta) {
    if (!window.confirm(t('dashboards.switcher.delete_confirm', { name: d.name }))) return;
    setOpen(false);
    router.delete(route('app.project.dashboards.destroy', { project: project.id, dashboard: d.id }), { preserveState: false });
  }

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="true"
        className="flex items-center gap-2 rounded-[var(--radius)] border border-line bg-surface px-3 py-2 text-sm font-semibold text-foreground transition-colors hover:border-line-strong focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
      >
        <LayoutGrid className="h-4 w-4 shrink-0 text-subtle" />
        <span className="max-w-[12rem] truncate">{active.name}</span>
        <ChevronDown className="h-4 w-4 shrink-0 text-subtle" />
      </button>

      {open && (
        <div className="absolute left-0 top-full z-30 mt-2 w-64 rounded-xl border border-line bg-surface p-1.5 shadow-[var(--shadow)]">
          <p className="px-2.5 py-1 text-[10.5px] font-bold uppercase tracking-wider text-subtle">{t('dashboards.switcher.label')}</p>
          <div className="max-h-64 overflow-y-auto">
            {dashboards.map((d) => (
              <div key={d.id} className="group flex items-center gap-1 rounded-lg hover:bg-elevated">
                <button
                  onClick={() => switchTo(d)}
                  className="flex min-w-0 flex-1 items-center gap-2.5 px-2.5 py-2 text-left text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                >
                  <span className="min-w-0 flex-1 truncate">{d.name}</span>
                  {d.id === active.id && <Check className="h-4 w-4 shrink-0 text-accent-soft-foreground" />}
                </button>
                <button
                  onClick={() => destroy(d)}
                  aria-label={t('dashboards.switcher.delete')}
                  className="shrink-0 rounded p-1.5 text-subtle opacity-0 transition-opacity hover:bg-danger-soft hover:text-danger-foreground focus-visible:opacity-100 focus-visible:outline-none group-hover:opacity-100"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </div>
            ))}
          </div>
          <div className="mt-1 border-t border-line pt-1">
            <button
              onClick={renameActive}
              className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-foreground transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
            >
              <Pencil className="h-4 w-4 shrink-0 text-subtle" />
              {t('dashboards.switcher.rename')}
            </button>
            <button
              onClick={createDashboard}
              className="flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm text-foreground transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
            >
              <Plus className="h-4 w-4 shrink-0 text-subtle" />
              {t('dashboards.switcher.new')}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
