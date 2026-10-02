import { formatCompactNumber } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useWidgetData } from '@/lib/widgets/useWidgetData';
import type { WidgetConfig } from '@/lib/widgets/schema';
import WidgetFrame from './WidgetFrame';

type Kpi = { value: number; deltaPct: number | null };

export default function KpiWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { t } = useTranslation();
  const { data, loading, error, reload } = useWidgetData<Kpi>('kpi', config);
  const title = config.title || t(`widgets.metric.${config.metric}`);
  const frameless = !!config.hide_frame;
  const up = (data?.deltaPct ?? 0) >= 0;

  return (
    <WidgetFrame title={title} loading={loading} error={error} onReload={reload} editing={editing} onConfigure={onConfigure} onRemove={onRemove} frameless={frameless}>
      {data && (
        // The frame already shows the metric name; render only the value (+ delta)
        // here so a KPI fits a short cell and isn't a redundant card-in-a-card.
        // When frameless there's no frame title, so show the label too.
        <div className="flex h-full flex-col justify-center">
          {frameless && <p className="text-[12.5px] font-semibold text-muted">{title}</p>}
          <p className="text-[28px] font-bold leading-none tracking-tight text-foreground tabular-nums" title={data.value.toLocaleString()}>
            {formatCompactNumber(data.value)}
          </p>
          <p className={`mt-1 inline-flex items-center gap-1 text-xs font-bold ${data.deltaPct === null ? 'text-muted' : up ? 'text-success-foreground' : 'text-danger-foreground'}`}>
            {data.deltaPct === null ? '—' : `${up ? '▲' : '▼'} ${Math.abs(data.deltaPct)} %`}
            <span className="font-medium text-muted">{t('common.dashboard.vs_previous')}</span>
          </p>
        </div>
      )}
    </WidgetFrame>
  );
}
