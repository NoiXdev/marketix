import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Responsive, WidthProvider, type Layout } from 'react-grid-layout/legacy';
import type { Widget as W } from '@/lib/widgets/schema';
import Widget from '@/Pages/Dashboard/widgets/Widget';
import DashboardSwitcher from '@/Pages/Dashboard/DashboardSwitcher';

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
  const layout: Layout = active.widgets.map((w) => ({ i: w.id, ...w.layout }));

  return (
    <AppLayout title={project.name}>
      <div className="px-8 py-6">
        <div className="mb-4 flex items-center justify-between gap-3">
          <DashboardSwitcher dashboards={dashboards} active={active} />
          {/* Edit toggle added in Task 11 */}
        </div>
        <Grid
          className="layout"
          layouts={{ lg: layout }}
          breakpoints={{ lg: 1024, xs: 0 }}
          cols={{ lg: 12, xs: 1 }}
          rowHeight={64}
          isDraggable={false}
          isResizable={false}
          margin={[14, 14]}
        >
          {active.widgets.map((w) => (
            <div key={w.id}>
              <Widget widget={w} editing={false} onConfigure={() => {}} onRemove={() => {}} />
            </div>
          ))}
        </Grid>
      </div>
    </AppLayout>
  );
}
