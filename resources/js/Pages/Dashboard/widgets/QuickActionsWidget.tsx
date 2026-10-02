import QuickActions from '@/Pages/Dashboard/QuickActions';
import { PageProps } from '@/types';
import { useTranslation } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';
import type { WidgetConfig } from '@/lib/widgets/schema';
import WidgetFrame from './WidgetFrame';

export default function QuickActionsWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { t } = useTranslation();
  const project = usePage<PageProps>().props.project!;
  return (
    <WidgetFrame title={config.title || t('widgets.types.quick_actions')} editing={editing} onConfigure={onConfigure} onRemove={onRemove}>
      <QuickActions projectId={project.id} />
    </WidgetFrame>
  );
}
