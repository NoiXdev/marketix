import ActivityFeed, { FeedItem } from '@/Pages/Dashboard/ActivityFeed';
import { useTranslation } from '@/lib/i18n';
import type { WidgetConfig } from '@/lib/widgets/schema';
import { useWidgetData } from '@/lib/widgets/useWidgetData';
import WidgetFrame from './WidgetFrame';

export default function ActivityWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { t } = useTranslation();
  const { data, loading, error, reload } = useWidgetData<{ items: FeedItem[] }>('activity', config);
  return (
    <WidgetFrame
      title={config.title || t('widgets.types.activity')}
      loading={loading}
      error={error}
      onReload={reload}
      editing={editing}
      onConfigure={onConfigure}
      onRemove={onRemove}
      frameless={!!config.hide_frame}
      scroll
    >
      {data && <ActivityFeed items={data.items} emptyLabel={t('widgets.no_data')} />}
    </WidgetFrame>
  );
}
