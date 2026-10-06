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
    <div className="border-line bg-surface rounded-[var(--radius)] border">
      <button type="button" onClick={toggle} className="text-foreground flex w-full items-center gap-2 px-5 py-3 text-left text-sm font-semibold">
        {open ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
        {t('qr.versions.title')}
      </button>
      {open && (
        <div className="border-line border-t px-5 py-3">
          {!versions ? (
            <p className="text-subtle text-sm">Loading…</p>
          ) : versions.length === 0 ? (
            <p className="text-subtle text-sm">{t('qr.versions.empty')}</p>
          ) : (
            <ul className="divide-line divide-y">
              {versions.map((v, i) => (
                <li key={v.version} className="flex items-center justify-between py-2">
                  <div>
                    <p className="text-foreground text-sm">
                      <span className="font-medium">v{v.version}</span> · {v.is_dynamic ? t('qr.versions.dynamic') : t('qr.versions.static')}
                      {i === 0 && (
                        <Badge variant="accent" className="ml-2">
                          {t('qr.versions.current')}
                        </Badge>
                      )}
                    </p>
                    <p className="text-subtle text-xs">
                      {t('qr.versions.by', { name: v.created_by_name ?? 'System' })} · {new Date(v.created_at).toLocaleString()}
                    </p>
                  </div>
                  {i !== 0 && (
                    <button
                      type="button"
                      onClick={() => restore(v.version)}
                      className="border-line text-muted hover:bg-elevated inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs"
                    >
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
