import { Badge } from '@/Components/ui';
import { describeActivity, logNameLabel } from '@/lib/activity';
import { useTranslation } from '@/lib/i18n';
import { ActivityEntry } from '@/types';

export default function ActivityFeed({ activities, showProject = false }: { activities: ActivityEntry[]; showProject?: boolean }) {
  const { t } = useTranslation();

  if (activities.length === 0) {
    return <p className="text-subtle py-12 text-center text-sm">{t('common.dashboard.no_activity')}</p>;
  }

  return (
    <ul className="divide-line divide-y">
      {activities.map((a) => (
        <li key={a.id} className="flex items-center gap-3 py-3">
          <span className="bg-accent-soft text-accent-soft-foreground flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
            {(a.causer?.name ?? '•').slice(0, 2).toUpperCase()}
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-muted truncate text-sm">
              <span className="text-foreground font-medium">{a.causer?.name ?? 'System'}</span> {describeActivity(a, t)}
            </p>
            <p className="text-subtle text-xs">
              <Badge>{logNameLabel(a.log_name, t)}</Badge> {showProject && a.project && <Badge>{a.project.name}</Badge>}
              {showProject && a.project && ' '}
              {new Date(a.created_at).toLocaleString()}
            </p>
          </div>
        </li>
      ))}
    </ul>
  );
}
