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
        <li key={k} className="text-slate-500 dark:text-slate-400">
          <span className="font-medium">{k}</span>:{' '}
          {k in old && <span className="text-red-500 line-through">{JSON.stringify(old[k])}</span>}{' '}
          <span className="text-green-600 dark:text-green-400">{JSON.stringify(attrs[k])}</span>
        </li>
      ))}
    </ul>
  );
}

export default function ActivityHistory({ history }: { history?: ActivityEntry[] }) {
  const [open, setOpen] = useState(false);

  function toggle() {
    const next = !open;
    setOpen(next);
    if (next && !history) {
      router.reload({ only: ['history'] });
    }
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <button type="button" onClick={toggle} className="flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        History
      </button>
      {open && (
        <div className="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
          {!history ? (
            <p className="text-sm text-slate-400">Loading…</p>
          ) : history.length === 0 ? (
            <p className="text-sm text-slate-400">No history yet.</p>
          ) : (
            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
              {history.map((a) => (
                <li key={a.id} className="py-2">
                  <p className="text-sm text-slate-700 dark:text-slate-200">
                    <span className="font-medium">{a.causer?.name ?? 'System'}</span> {a.description}{' '}
                    <span className="text-xs text-slate-400">{new Date(a.created_at).toLocaleString()}</span>
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
