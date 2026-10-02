import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import { useWidgetData } from '@/lib/widgets/useWidgetData';
import type { WidgetConfig } from '@/lib/widgets/schema';
import WidgetFrame from './WidgetFrame';

export default function GeoMapWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { data, loading, error, reload } = useWidgetData<{ data: CountryDatum[] }>('geo_map', config);
  return (
    <WidgetFrame title={config.title ?? undefined} loading={loading} error={error} onReload={reload} editing={editing} onConfigure={onConfigure} onRemove={onRemove}>
      {data && <WorldMap data={data.data} title={config.title ?? undefined} bare />}
    </WidgetFrame>
  );
}
