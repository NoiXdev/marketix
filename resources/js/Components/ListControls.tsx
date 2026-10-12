import { Input, Pagination, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Paginated } from '@/types';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export type ListFilters = { search: string; per_page: number };

const PAGE_SIZES = [25, 50, 100];

export function visitList(url: string, { search, per_page }: ListFilters) {
  const query = { ...(search ? { search } : {}), ...(per_page !== PAGE_SIZES[0] ? { per_page } : {}) };
  router.get(url, query, { preserveState: true, preserveScroll: true, replace: true });
}

export function ListSearch({ value, placeholder, onSearch }: { value: string; placeholder: string; onSearch: (search: string) => void }) {
  const [term, setTerm] = useState(value);
  const timer = useRef<number | undefined>(undefined);

  useEffect(() => () => window.clearTimeout(timer.current), []);

  function change(next: string) {
    setTerm(next);
    window.clearTimeout(timer.current);
    timer.current = window.setTimeout(() => onSearch(next.trim()), 300);
  }

  return (
    <div className="relative mb-4 w-full sm:max-w-xs">
      <Search className="text-subtle pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
      <Input type="search" value={term} onChange={(event) => change(event.target.value)} placeholder={placeholder} aria-label={placeholder} className="pl-9" />
    </div>
  );
}

export function ListFooter({ page, perPage, onPerPage }: { page: Paginated<unknown>; perPage: number; onPerPage: (perPage: number) => void }) {
  const { t } = useTranslation();

  return (
    <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
      <p className="text-muted text-sm tabular-nums">{t('common.list.summary', { from: page.from ?? 0, to: page.to ?? 0, total: page.total })}</p>
      <div className="flex flex-wrap items-center gap-3">
        {page.last_page > 1 && <Pagination links={page.links} />}
        <label className="text-muted flex items-center gap-2 text-sm">
          {t('common.list.per_page')}
          <Select value={perPage} onChange={(event) => onPerPage(Number(event.target.value))} className="w-auto py-1.5">
            {PAGE_SIZES.map((size) => (
              <option key={size} value={size}>
                {size}
              </option>
            ))}
          </Select>
        </label>
      </div>
    </div>
  );
}

export function NoResults({ term }: { term: string }) {
  const { t } = useTranslation();

  return <p className="border-line bg-surface text-muted rounded-[var(--radius)] border px-4 py-10 text-center text-sm">{t('common.list.no_results', { term })}</p>;
}
