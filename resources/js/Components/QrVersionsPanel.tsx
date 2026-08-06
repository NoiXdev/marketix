import { Badge } from '@/Components/ui';
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
    <div className="rounded-[var(--radius)] border border-line bg-surface">
      <button type="button" onClick={toggle}
        className="flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold text-foreground">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        {t('qr.versions.title')}
      </button>
      {open && (
        <div className="border-t border-line px-5 py-3">
          {!versions ? (
            <p className="text-sm text-subtle">Loading…</p>
          ) : versions.length === 0 ? (
            <p className="text-sm text-subtle">{t('qr.versions.empty')}</p>
          ) : (
            <ul className="divide-y divide-line">
              {versions.map((v, i) => (
                <li key={v.version} className="flex items-center justify-between py-2">
                  <div>
                    <p className="text-sm text-foreground">
                      <span className="font-medium">v{v.version}</span> ·{' '}
                      {v.is_dynamic ? t('qr.versions.dynamic') : t('qr.versions.static')}
                      {i === 0 && (
                        <Badge variant="accent" className="ml-2">
                          {t('qr.versions.current')}
                        </Badge>
                      )}
                    </p>
                    <p className="text-xs text-subtle">
                      {t('qr.versions.by', { name: v.created_by_name ?? 'System' })} ·{' '}
                      {new Date(v.created_at).toLocaleString()}
                    </p>
                  </div>
                  {i !== 0 && (
                    <button type="button" onClick={() => restore(v.version)}
                      className="inline-flex items-center gap-1 rounded-md border border-line px-2 py-1 text-xs text-muted hover:bg-elevated">
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
