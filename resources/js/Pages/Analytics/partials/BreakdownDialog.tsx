import { Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import RankedList, { RankRow } from '@/Pages/Dashboard/RankedList';
import { Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { Search, X } from 'lucide-react';
import { useEffect, useState } from 'react';

type Status = 'loading' | 'ready' | 'error';

export default function BreakdownDialog({
  onClose,
  title,
  url,
  unit,
  emptyLabel,
  toRows,
}: {
  onClose: () => void;
  title: string;
  url: string;
  unit?: string;
  emptyLabel: string;
  toRows: (data: unknown[]) => RankRow[];
}) {
  const { t, locale } = useTranslation();
  const [status, setStatus] = useState<Status>('loading');
  const [data, setData] = useState<{ rows: unknown[]; limit: number }>({ rows: [], limit: 0 });
  const [search, setSearch] = useState('');

  useEffect(() => {
    const controller = new AbortController();
    window.axios
      .get<{ rows: unknown[]; limit: number }>(url, { signal: controller.signal })
      .then(({ data }) => {
        setData(data);
        setStatus('ready');
      })
      .catch(() => controller.signal.aborted || setStatus('error'));
    return () => controller.abort();
  }, [url]);

  const needle = search.trim().toLocaleLowerCase(locale);
  const rows = toRows(data.rows)
    .filter((row) => !needle || [row.label, row.sub, row.note].some((text) => text?.toLocaleLowerCase(locale).includes(needle)))
    .map((row) => {
      const select = row.onClick;
      return select
        ? {
            ...row,
            onClick: () => {
              onClose();
              select();
            },
          }
        : row;
    });

  return (
    <Dialog open onClose={onClose} className="relative z-[100]">
      <DialogBackdrop transition className="fixed inset-0 bg-[rgb(10_14_22/0.45)] backdrop-blur-[2px] transition-opacity duration-150 ease-out data-[closed]:opacity-0" />
      <div className="fixed inset-0 flex items-end justify-center p-4 sm:items-center">
        <DialogPanel
          transition
          className="border-line bg-surface flex max-h-[85vh] w-full max-w-xl flex-col overflow-hidden rounded-[var(--radius)] border shadow-[var(--shadow)] transition duration-150 ease-out data-[closed]:translate-y-2 data-[closed]:scale-[0.98] data-[closed]:opacity-0"
        >
          <div className="border-line flex items-center justify-between gap-3 border-b px-4 py-3">
            <DialogTitle className="text-foreground text-sm font-semibold">{title}</DialogTitle>
            <button
              type="button"
              onClick={onClose}
              aria-label={t('analytics.dashboard.list.close')}
              className="text-subtle hover:bg-elevated hover:text-foreground rounded-md p-1 focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
          <div className="border-line border-b p-3">
            <div className="relative">
              <Search className="text-subtle pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
              <Input
                autoFocus
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t('analytics.dashboard.list.search')}
                aria-label={t('analytics.dashboard.list.search')}
                className="pl-9"
              />
            </div>
          </div>
          {unit && status === 'ready' && rows.length > 0 && <p className="text-subtle px-4 pt-2 text-right text-[11px] font-semibold">{unit}</p>}
          <div className="min-h-32 flex-1 overflow-y-auto">
            {status === 'loading' ? (
              <p className="text-subtle px-4 py-6 text-center text-sm">{t('analytics.dashboard.list.loading')}</p>
            ) : status === 'error' ? (
              <p className="text-danger-foreground px-4 py-6 text-center text-sm">{t('analytics.dashboard.list.error')}</p>
            ) : (
              <RankedList rows={rows} emptyLabel={needle ? t('analytics.dashboard.list.no_match') : emptyLabel} />
            )}
          </div>
          {status === 'ready' && data.rows.length >= data.limit && (
            <p className="border-line text-subtle border-t px-4 py-2.5 text-xs">{t('analytics.dashboard.list.capped', { count: data.limit.toLocaleString(locale) })}</p>
          )}
        </DialogPanel>
      </div>
    </Dialog>
  );
}
