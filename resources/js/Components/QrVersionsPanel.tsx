import { confirmAction } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronRight, RotateCcw } from 'lucide-react';
import { useState } from 'react';

export interface QrVersionEntry {
  version: number;
  name: string;
  type: string;
  is_dynamic: boolean;
  created_at: string;
  created_by_name: string | null;
}

export default function QrVersionsPanel({ qrId, versions }: { qrId: string; versions?: QrVersionEntry[] }) {
  const { t } = useTranslation();
  const { project } = usePage<PageProps>().props;
  const [open, setOpen] = useState(false);

  function toggle() {
    const next = !open;
    setOpen(next);
    if (next && !versions) router.reload({ only: ['versions'] });
  }

  async function restore(version: number) {
    const ok = await confirmAction({
      title: t('qr.versions.restore_confirm.title'),
      text: t('qr.versions.restore_confirm.text'),
      confirmText: t('qr.versions.restore_confirm.button'),
    });
    if (!ok) return;
    router.post(route('app.project.qrcodes.versions.restore', { project: project!.id, qrCode: qrId, version }));
  }

  return (
    <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <button type="button" onClick={toggle}
        className="flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold text-slate-900 dark:text-white">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        {t('qr.versions.title')}
      </button>
      {open && (
        <div className="border-t border-slate-100 px-5 py-3 dark:border-slate-800">
          {!versions ? (
            <p className="text-sm text-slate-400">Loading…</p>
          ) : versions.length === 0 ? (
            <p className="text-sm text-slate-400">{t('qr.versions.empty')}</p>
          ) : (
            <ul className="divide-y divide-slate-100 dark:divide-slate-800">
              {versions.map((v, i) => (
                <li key={v.version} className="flex items-center justify-between py-2">
                  <div>
                    <p className="text-sm text-slate-700 dark:text-slate-200">
                      <span className="font-medium">v{v.version}</span> ·{' '}
                      {v.is_dynamic ? t('qr.versions.dynamic') : t('qr.versions.static')}
                      {i === 0 && (
                        <span className="ml-2 rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                          {t('qr.versions.current')}
                        </span>
                      )}
                    </p>
                    <p className="text-xs text-slate-400">
                      {t('qr.versions.by', { name: v.created_by_name ?? 'System' })} ·{' '}
                      {new Date(v.created_at).toLocaleString()}
                    </p>
                  </div>
                  {i !== 0 && (
                    <button type="button" onClick={() => restore(v.version)}
                      className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                      <RotateCcw className="h-3.5 w-3.5" /> {t('qr.versions.restore')}
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
