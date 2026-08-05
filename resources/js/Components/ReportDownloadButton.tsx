import { Button, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Download } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

type Props = { projectId: string; urlId?: string };

export default function ReportDownloadButton({ projectId, urlId }: Props) {
  const [open, setOpen] = useState(false);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const ref = useRef<HTMLDivElement>(null);
  const { t } = useTranslation();

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') setOpen(false);
    }
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  }, []);

  const build = (params: Record<string, string | number>) => {
    const base = { project: projectId, ...(urlId ? { url: urlId } : {}), ...params };
    return urlId
      ? route('app.project.links.reports.download', base)
      : route('app.project.reports.download', base);
  };

  const go = (params: Record<string, string | number>) => {
    window.open(build(params), '_blank');
    setOpen(false);
  };

  return (
    <div className="relative" ref={ref}>
      <Button
        type="button"
        variant="secondary"
        onClick={() => setOpen((v) => !v)}
        aria-haspopup="true"
        aria-expanded={open}
      >
        <Download className="h-4 w-4" /> {t('common.report.download_pdf')}
      </Button>
      {open && (
        <div className="absolute right-0 z-20 mt-2 w-64 rounded-[var(--radius)] border border-line bg-surface p-3 shadow-[var(--shadow)]">
          <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-subtle">
            {t('common.report.preset')}
          </div>
          <div className="flex gap-2">
            {[7, 30, 90].map((d) => (
              <button
                key={d}
                type="button"
                onClick={() => go({ range: d })}
                className="flex-1 rounded-[var(--radius-sm)] bg-elevated px-2 py-1.5 text-sm font-medium text-foreground transition-colors hover:bg-accent-soft hover:text-accent-soft-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
              >
                {d}
                {t('common.report.day_suffix')}
              </button>
            ))}
          </div>
          <div className="my-2 text-xs font-semibold uppercase tracking-wide text-subtle">
            {t('common.report.custom')}
          </div>
          <div className="flex flex-col gap-2">
            <Input
              type="date"
              aria-label={t('common.report.from')}
              value={from}
              onChange={(e) => setFrom(e.target.value)}
            />
            <Input
              type="date"
              aria-label={t('common.report.to')}
              value={to}
              onChange={(e) => setTo(e.target.value)}
            />
            <Button type="button" disabled={!from || !to} onClick={() => go({ from, to })}>
              {t('common.report.download_range')}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
