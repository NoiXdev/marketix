import { useTranslation } from '@/lib/i18n';
import { ActivityEntry } from '@/types';
import { router } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';

function Diff({ changes }: { changes: ActivityEntry['changes'] }) {
  const attrs = (changes.attributes ?? {}) as Record<string, unknown>;
  const old = (changes.old ?? {}) as Record<string, unknown>;
  const keys = Object.keys(attrs);

  if (keys.length === 0) {
    return null;
  }

  return (
    <ul className="mt-1 space-y-0.5 text-xs">
      {keys.map((k) => (
        <li key={k} className="text-muted">
          <span className="font-medium">{k}</span>:{' '}
          {k in old && <span className="text-danger-foreground line-through">{JSON.stringify(old[k])}</span>}{' '}
          <span className="text-success-foreground">{JSON.stringify(attrs[k])}</span>
        </li>
      ))}
    </ul>
  );
}

export default function ActivityHistory({ history }: { history?: ActivityEntry[] }) {
  const { t } = useTranslation();
  const [open, setOpen] = useState(false);

  function toggle() {
    const next = !open;
    setOpen(next);
    if (next && !history) {
      router.reload({ only: ['history'] });
    }
  }

  return (
    <div className="rounded-xl border border-line bg-surface">
      <button type="button" onClick={toggle} className="flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold text-foreground">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        {t('activity.history.title')}
      </button>
      {open && (
        <div className="border-t border-line px-5 py-3">
          {!history ? (
            <p className="text-sm text-subtle">{t('activity.history.loading')}</p>
          ) : history.length === 0 ? (
            <p className="text-sm text-subtle">{t('activity.history.empty')}</p>
          ) : (
            <ul className="divide-y divide-line">
              {history.map((a) => (
                <li key={a.id} className="py-2">
                  <p className="text-sm text-muted">
                    <span className="font-medium text-foreground">{a.causer?.name ?? 'System'}</span> {a.description}{' '}
                    <span className="text-xs text-subtle">{new Date(a.created_at).toLocaleString()}</span>
                  </p>
                  <Diff changes={a.changes} />
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
