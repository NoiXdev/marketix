import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import { useTranslation } from '@/lib/i18n';
import type { WidgetConfig } from '@/lib/widgets/schema';
import { useWidgetData } from '@/lib/widgets/useWidgetData';
import WidgetFrame from './WidgetFrame';

export default function GeoMapWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { t } = useTranslation();
  const { data, loading, error, reload } = useWidgetData<{ data: CountryDatum[] }>('geo_map', config);
  return (
    <WidgetFrame
      title={config.title || t('widgets.types.geo_map')}
      loading={loading}
      error={error}
      onReload={reload}
      editing={editing}
      onConfigure={onConfigure}
      onRemove={onRemove}
      frameless={!!config.hide_frame}
    >
      {data && <WorldMap data={data.data} title={config.title ?? undefined} bare />}
    </WidgetFrame>
  );
}
