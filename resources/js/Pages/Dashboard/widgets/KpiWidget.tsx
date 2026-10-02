import KpiTile from '@/Pages/Dashboard/KpiTile';
import { useTranslation } from '@/lib/i18n';
import { useWidgetData } from '@/lib/widgets/useWidgetData';
import type { WidgetConfig } from '@/lib/widgets/schema';
import { BarChart3 } from 'lucide-react';
import WidgetFrame from './WidgetFrame';

type Kpi = { value: number; deltaPct: number | null };

export default function KpiWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { t } = useTranslation();
  const { data, loading, error, reload } = useWidgetData<Kpi>('kpi', config);
  const title = config.title || t(`widgets.metric.${config.metric}`);

  return (
    <WidgetFrame title={title} loading={loading} error={error} onReload={reload} editing={editing} onConfigure={onConfigure} onRemove={onRemove}>
      {data && <KpiTile label={title} value={data.value} deltaPct={data.deltaPct} icon={BarChart3} />}
    </WidgetFrame>
  );
}
