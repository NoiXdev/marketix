import ClicksChart from '@/Pages/Dashboard/ClicksChart';
import { useTranslation } from '@/lib/i18n';
import { useWidgetData } from '@/lib/widgets/useWidgetData';
import type { WidgetConfig } from '@/lib/widgets/schema';
import WidgetFrame from './WidgetFrame';

type Series = { series: { date: string; clicks: number; unique: number }[] };

export default function TimeseriesWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { t } = useTranslation();
  const { data, loading, error, reload } = useWidgetData<Series>('timeseries', config);
  return (
    <WidgetFrame title={config.title || t('widgets.types.timeseries')} loading={loading} error={error} onReload={reload} editing={editing} onConfigure={onConfigure} onRemove={onRemove} frameless={!!config.hide_frame}>
      {data && <ClicksChart data={data.series} fill />}
    </WidgetFrame>
  );
}
