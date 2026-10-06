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
          <span className="font-medium">{k}</span>: {k in old && <span className="text-danger-foreground line-through">{JSON.stringify(old[k])}</span>}{' '}
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
    <div className="border-line bg-surface rounded-xl border">
      <button type="button" onClick={toggle} className="text-foreground flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        {t('activity.history.title')}
      </button>
      {open && (
        <div className="border-line border-t px-5 py-3">
          {!history ? (
            <p className="text-subtle text-sm">{t('activity.history.loading')}</p>
          ) : history.length === 0 ? (
            <p className="text-subtle text-sm">{t('activity.history.empty')}</p>
          ) : (
            <ul className="divide-line divide-y">
              {history.map((a) => (
                <li key={a.id} className="py-2">
                  <p className="text-muted text-sm">
                    <span className="text-foreground font-medium">{a.causer?.name ?? 'System'}</span> {a.description}{' '}
                    <span className="text-subtle text-xs">{new Date(a.created_at).toLocaleString()}</span>
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
