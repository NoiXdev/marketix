import { Badge } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { ActivityEntry } from '@/types';

const KNOWN_LABEL_CODES = new Set([
  'created',
  'updated',
  'deleted',
  'login',
  'password_changed',
  'password_reset',
  'two_factor_enabled',
  'two_factor_disabled',
  'passkey_added',
  'passkey_removed',
  'passkey_renamed',
  'member_removed',
  'role_changed',
  'invitation_sent',
  'invitation_revoked',
  'invitation_resent',
  'invitation_accepted',
]);

function describe(a: ActivityEntry, t: (key: string, replacements?: Record<string, string | number>) => string): string {
  const verb = KNOWN_LABEL_CODES.has(a.description) ? t(`activity.feed.labels.${a.description}`) : a.description;
  if (['created', 'updated', 'deleted'].includes(a.description) && a.subject_type) {
    return t('activity.feed.subject_line', { verb, subject: a.subject_type });
  }
  return verb;
}

export default function ActivityFeed({ activities, showProject = false }: { activities: ActivityEntry[]; showProject?: boolean }) {
  const { t } = useTranslation();

  if (activities.length === 0) {
    return <p className="py-12 text-center text-sm text-subtle">{t('common.dashboard.no_activity')}</p>;
  }

  return (
    <ul className="divide-y divide-line">
      {activities.map((a) => (
        <li key={a.id} className="flex items-center gap-3 py-3">
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent-soft-foreground">
            {(a.causer?.name ?? '•').slice(0, 2).toUpperCase()}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm text-muted">
              <span className="font-medium text-foreground">{a.causer?.name ?? 'System'}</span>{' '}
              {describe(a, t)}
            </p>
            <p className="text-xs text-subtle">
              <Badge>{a.log_name}</Badge>{' '}
              {showProject && a.project && <Badge>{a.project.name}</Badge>}
              {showProject && a.project && ' '}
              {new Date(a.created_at).toLocaleString()}
            </p>
          </div>
        </li>
      ))}
    </ul>
  );
}
