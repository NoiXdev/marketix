import { ActivityEntry } from '@/types';

type Translate = (key: string, replacements?: Record<string, string | number>) => string;

const LABELS = new Set([
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
  'stats_reset',
]);

const SUBJECT_EVENTS = new Set(['created', 'updated', 'deleted', 'moved_in', 'moved_out']);

function translateOr(t: Translate, key: string, fallback: string): string {
  const value = t(key);
  return value === key ? fallback : value;
}

export function describeActivity(activity: ActivityEntry, t: Translate): string {
  const known = (code: string | null): code is string => code !== null && (LABELS.has(code) || SUBJECT_EVENTS.has(code));
  const code = known(activity.description) ? activity.description : known(activity.event) ? activity.event : null;

  if (code === null) {
    return activity.description;
  }

  if (SUBJECT_EVENTS.has(code) && activity.subject_type) {
    return t(`activity.feed.events.${code}`, {
      subject: translateOr(t, `activity.subjects.${activity.subject_type}`, activity.subject_type),
      project: String(activity.properties.project ?? ''),
    });
  }

  return LABELS.has(code) ? t(`activity.feed.labels.${code}`) : activity.description;
}

export function logNameLabel(name: string, t: Translate): string {
  return translateOr(t, `activity.log_names.${name}`, name);
}
