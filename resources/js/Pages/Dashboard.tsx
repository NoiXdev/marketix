import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Responsive, WidthProvider, type Layout } from 'react-grid-layout/legacy';
import { Check, Pencil, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { WIDGET_DEFS, newWidget, type Widget as W, type WidgetConfig } from '@/lib/widgets/schema';
import Widget from '@/Pages/Dashboard/widgets/Widget';
import DashboardSwitcher from '@/Pages/Dashboard/DashboardSwitcher';
import AddWidgetPicker from '@/Pages/Dashboard/AddWidgetPicker';
import WidgetConfigForm from '@/Pages/Dashboard/WidgetConfigForm';

const Grid = WidthProvider(Responsive);

interface DashboardMeta {
  id: string;
  name: string;
  is_default: boolean;
  position: number;
}
interface Props {
  dashboards: DashboardMeta[];
  active: { id: string; name: string; widgets: W[] };
}

export default function Dashboard({ dashboards, active }: Props) {
  const project = usePage<PageProps>().props.project!;
  const { t } = useTranslation();

  const [editing, setEditing] = useState(false);
  const [widgets, setWidgets] = useState<W[]>(active.widgets);
  const [picking, setPicking] = useState(false);
  const [configuring, setConfiguring] = useState<W | null>(null);
  // Tracks the RGL breakpoint actually in effect. Editing is a desktop-only
  // action: below `lg` the grid collapses to a single derived column, and
  // persisting that collapsed layout would clobber the saved desktop layout.
  const [breakpoint, setBreakpoint] = useState('lg');

  // Reset local edit state whenever the active dashboard changes (switcher navigation).
  useEffect(() => {
    setWidgets(active.widgets);
    setEditing(false);
    setPicking(false);
    setConfiguring(null);
  }, [active.id]);

  // If the viewport shrinks below `lg` while editing, drop out of edit mode —
  // editing is desktop-only.
  useEffect(() => {
    if (breakpoint !== 'lg' && editing) {
      setEditing(false);
    }
  }, [breakpoint, editing]);

  function save(next: W[]) {
    setWidgets(next);
    router.put(
      route('app.project.dashboards.update', { project: project.id, dashboard: active.id }),
      // next is already plain JSON-safe data; round-trip it so its structural
      // type matches Inertia's FormDataConvertible payload shape.
      { name: active.name, widgets: JSON.parse(JSON.stringify(next)) },
      { preserveScroll: true, preserveState: true },
    );
  }

  function onLayoutChange(l: Layout) {
    if (!editing) return;
    // Never persist a layout derived from a non-desktop breakpoint — RGL
    // hands back a collapsed 1-column layout (x:0, w:1 per item) below `lg`,
    // which would overwrite the stored desktop arrangement.
    if (breakpoint !== 'lg') return;
    const byId = new Map(l.map((it) => [it.i, it]));
    save(
      widgets.map((w) => {
        const it = byId.get(w.id);
        return it ? { ...w, layout: { x: it.x, y: it.y, w: it.w, h: it.h } } : w;
      }),
    );
  }

  function addWidget(type: W['type']) {
    // newWidget() sets layout.y = Infinity so RGL drops it at the bottom on
    // first render, but Infinity round-trips through JSON as null, which the
    // backend then coalesces to 0 — colliding with existing widgets on
    // reload. Compute a real, finite bottom-Y up front instead.
    const bottomY = widgets.reduce((m, w) => Math.max(m, w.layout.y + w.layout.h), 0);
    const w = newWidget(type);
    w.layout = { ...w.layout, x: 0, y: bottomY };
    save([...widgets, w]);
    setPicking(false);
  }

  function removeWidget(id: string) {
    save(widgets.filter((w) => w.id !== id));
  }

  function configureWidget(id: string, config: WidgetConfig) {
    save(widgets.map((w) => (w.id === id ? { ...w, config } : w)));
    setConfiguring(null);
  }

  const layout: Layout = widgets.map((w) => {
    const def = WIDGET_DEFS[w.type].defaultLayout;
    return { i: w.id, x: w.layout.x, y: w.layout.y, w: w.layout.w, h: w.layout.h, minW: def.minW, minH: def.minH, maxH: def.maxH };
  });

  return (
    <AppLayout title={project.name}>
      <div className="px-8 py-6">
        <div className="mb-4 flex items-center justify-between gap-3">
          <DashboardSwitcher dashboards={dashboards} active={active} />
          <div className="flex items-center gap-2">
            {editing && (
              <Button variant="secondary" size="sm" onClick={() => setPicking(true)}>
                <Plus className="h-4 w-4" />
                {t('dashboards.add_widget')}
              </Button>
            )}
            {/* Editing is a desktop-only action: hidden below `lg` so a narrow
                viewport can never enter edit mode and save a collapsed layout. */}
            <div className="hidden lg:block">
              <Button variant={editing ? 'primary' : 'secondary'} size="sm" onClick={() => setEditing((e) => !e)}>
                {editing ? <Check className="h-4 w-4" /> : <Pencil className="h-4 w-4" />}
                {editing ? t('dashboards.done_editing') : t('dashboards.edit')}
              </Button>
            </div>
          </div>
        </div>
        <Grid
          className="layout"
          layouts={{ lg: layout }}
          breakpoints={{ lg: 1024, xs: 0 }}
          cols={{ lg: 12, xs: 1 }}
          rowHeight={64}
          isDraggable={editing}
          isResizable={editing}
          draggableCancel=".widget-no-drag"
          margin={[14, 14]}
          onBreakpointChange={(bp) => setBreakpoint(bp)}
          onDragStop={(l) => onLayoutChange(l)}
          onResizeStop={(l) => onLayoutChange(l)}
        >
          {widgets.map((w) => (
            <div key={w.id}>
              <Widget widget={w} editing={editing} onConfigure={() => setConfiguring(w)} onRemove={() => removeWidget(w.id)} />
            </div>
          ))}
        </Grid>
      </div>

      {picking && <AddWidgetPicker onPick={addWidget} onClose={() => setPicking(false)} />}
      {configuring && (
        <WidgetConfigForm widget={configuring} onSave={(config) => configureWidget(configuring.id, config)} onClose={() => setConfiguring(null)} />
      )}
    </AppLayout>
  );
}
