import type { Widget as W } from '@/lib/widgets/schema';
import ActivityWidget from './ActivityWidget';
import GeoMapWidget from './GeoMapWidget';
import KpiWidget from './KpiWidget';
import QuickActionsWidget from './QuickActionsWidget';
import TimeseriesWidget from './TimeseriesWidget';
import TopListWidget from './TopListWidget';

export default function Widget({ widget, editing, onConfigure, onRemove }: { widget: W; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const p = { config: widget.config, editing, onConfigure, onRemove };
  switch (widget.type) {
    case 'kpi': return <KpiWidget {...p} />;
    case 'timeseries': return <TimeseriesWidget {...p} />;
    case 'top_list': return <TopListWidget {...p} />;
    case 'geo_map': return <GeoMapWidget {...p} />;
    case 'activity': return <ActivityWidget {...p} />;
    case 'quick_actions': return <QuickActionsWidget {...p} />;
  }
}
