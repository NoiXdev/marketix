import { ActivityEntry } from '@/types';

const LABELS: Record<string, string> = {
  created: 'created',
  updated: 'updated',
  deleted: 'deleted',
  login: 'signed in',
  password_changed: 'changed their password',
  password_reset: 'reset their password',
  two_factor_enabled: 'enabled two-factor auth',
  two_factor_disabled: 'disabled two-factor auth',
  passkey_added: 'added a passkey',
  passkey_removed: 'removed a passkey',
  passkey_renamed: 'renamed a passkey',
  member_removed: 'removed a member',
  role_changed: 'changed a member role',
  invitation_sent: 'sent an invitation',
  invitation_revoked: 'revoked an invitation',
  invitation_resent: 'resent an invitation',
  invitation_accepted: 'accepted an invitation',
};

function describe(a: ActivityEntry): string {
  const verb = LABELS[a.description] ?? a.description;
  if (['created', 'updated', 'deleted'].includes(a.description) && a.subject_type) {
    return `${verb} a ${a.subject_type}`;
  }
  return verb;
}

export default function ActivityFeed({ activities }: { activities: ActivityEntry[] }) {
  if (activities.length === 0) {
    return <p className="py-12 text-center text-sm text-slate-400">No activity yet.</p>;
  }

  return (
    <ul className="divide-y divide-slate-100 dark:divide-slate-800">
      {activities.map((a) => (
        <li key={a.id} className="flex items-center gap-3 py-3">
          <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
            {(a.causer?.name ?? '•').slice(0, 2).toUpperCase()}
          </span>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm text-slate-700 dark:text-slate-200">
              <span className="font-medium text-slate-900 dark:text-white">{a.causer?.name ?? 'System'}</span>{' '}
              {describe(a)}
            </p>
            <p className="text-xs text-slate-400">
              <span className="rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800">{a.log_name}</span>{' '}
              {new Date(a.created_at).toLocaleString()}
            </p>
          </div>
        </li>
      ))}
    </ul>
  );
}
