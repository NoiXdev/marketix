import AppLayout from '@/Layouts/AppLayout';
import { Badge, BackLink, Card, Flash, LinkButton, PageHeader, Pagination, Select, StatusPill, TableCard } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatBytes } from '@/lib/formatBytes';
import { durationBetween, formatDuration } from '@/lib/formatDuration';
import { CrawlContentCategory, CrawlPageRow, CrawlSummary, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect } from 'react';

type CrawlStatus = 'queued' | 'running' | 'completed' | 'failed';

interface CrawlDetail {
  id: string;
  start_url: string;
  mode: string;
  status: CrawlStatus;
  pages_crawled: number;
  summary: CrawlSummary;
  error: string | null;
  started_at: string | null;
  finished_at: string | null;
}

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

const statusVariant: Record<CrawlStatus, 'neutral' | 'success' | 'warning' | 'danger'> = {
  queued: 'neutral',
  running: 'warning',
  completed: 'success',
  failed: 'danger',
};

const categoryVariant: Record<CrawlContentCategory, 'neutral' | 'accent'> = {
  html: 'accent',
  image: 'neutral',
  pdf: 'neutral',
  media: 'neutral',
  other: 'neutral',
};

export default function CrawlsShow({
  crawl,
  pages,
  categories,
  filters,
}: {
  crawl: CrawlDetail;
  pages: Paginated<CrawlPageRow>;
  categories: CrawlContentCategory[];
  filters: { category: string | null; issue: string | null };
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  // Poll while the crawl is still queued/running so progress shows up without a manual refresh.
  useEffect(() => {
    if (crawl.status !== 'running' && crawl.status !== 'queued') return;
    const id = setInterval(() => router.reload({ only: ['crawl', 'pages'] }), 3000);
    return () => clearInterval(id);
  }, [crawl.status]);

  // Apply category/issue filters as query params (server-side, composable). Passing a
  // key merges it over the current filter; passing null clears just that one.
  function applyFilters(next: { category?: string | null; issue?: string | null }) {
    const category = next.category !== undefined ? next.category : filters.category;
    const issue = next.issue !== undefined ? next.issue : filters.issue;

    const params: Record<string, string> = {};
    if (category) params.category = category;
    if (issue) params.issue = issue;

    router.get(route('app.project.crawls.show', { project: project!.id, crawl: crawl.id }), params, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });
  }

  const summaryEntries = Object.entries(crawl.summary);

  return (
    <AppLayout title={crawl.start_url}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.crawls.index', { project: project!.id })}>{t('crawler.title')}</BackLink>
        </div>

        <PageHeader
          title={crawl.start_url}
          subtitle={
            <span className="inline-flex flex-wrap items-center gap-2">
              <StatusPill status={statusVariant[crawl.status]}>{t(`crawler.status_${crawl.status}`)}</StatusPill>
              <span>
                {crawl.pages_crawled} {t('crawler.pages')}
              </span>
              {crawl.finished_at && (
                <span className="text-muted">
                  · {t('crawler.duration')}: {formatDuration(durationBetween(crawl.started_at, crawl.finished_at))}
                </span>
              )}
            </span>
          }
          action={
            <LinkButton variant="secondary" href={route('app.project.crawls.export', { project: project!.id, crawl: crawl.id })}>
              {t('crawler.export_csv')}
            </LinkButton>
          }
        />
        <Flash />

        {crawl.error && (
          <Card className="mb-6 border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] bg-danger-soft p-4 text-sm text-danger-foreground">
            {crawl.error}
          </Card>
        )}

        {summaryEntries.length > 0 && (
          <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {summaryEntries.map(([code, count]) => {
              const active = filters.issue === code;
              return (
                <button
                  key={code}
                  type="button"
                  onClick={() => applyFilters({ issue: active ? null : code })}
                  aria-pressed={active}
                  className={`rounded-lg border p-4 text-left transition ${
                    active ? 'border-accent bg-accent-soft' : 'border-line bg-surface hover:bg-elevated'
                  }`}
                >
                  <div className="text-2xl font-semibold text-foreground">{count}</div>
                  <div className="text-xs text-muted">{t(`crawler.issue.${code}`)}</div>
                </button>
              );
            })}
          </div>
        )}

        {(categories.length > 1 || filters.issue) && (
          <div className="mb-3 flex flex-wrap items-center gap-3">
            {categories.length > 1 && (
              <div className="flex items-center gap-2">
                <span className="text-sm text-muted">{t('crawler.filter_type')}</span>
                <Select
                  value={filters.category ?? ''}
                  onChange={(e) => applyFilters({ category: e.target.value || null })}
                  className="w-48"
                >
                  <option value="">{t('crawler.filter_all')}</option>
                  {categories.map((c) => (
                    <option key={c} value={c}>
                      {t(`crawler.category.${c}`)}
                    </option>
                  ))}
                </Select>
              </div>
            )}

            {filters.issue && (
              <button
                type="button"
                onClick={() => applyFilters({ issue: null })}
                className="inline-flex items-center gap-1.5 rounded-full bg-accent-soft px-3 py-1 text-xs font-semibold text-accent-soft-foreground"
              >
                {t('crawler.filter_issue')}: {t(`crawler.issue.${filters.issue}`)}
                <X className="h-3.5 w-3.5" />
              </button>
            )}
          </div>
        )}

        <TableCard
          columns={[
            { label: t('crawler.col_url') },
            { label: t('crawler.col_status') },
            { label: t('crawler.col_type') },
            { label: t('crawler.col_indexable') },
            { label: t('crawler.col_inlinks') },
            { label: t('crawler.col_issues') },
          ]}
        >
          <tbody className="divide-y divide-line">
            {pages.data.map((p) => (
              <tr key={p.id} className="hover:bg-elevated">
                <td className="px-4 py-3">
                  <Link
                    href={route('app.project.crawls.pages.show', { project: project!.id, crawl: crawl.id, page: p.id })}
                    className="font-medium text-foreground hover:text-accent-soft-foreground"
                  >
                    {p.url}
                  </Link>
                </td>
                <td className="px-4 py-3 text-muted">{p.status_code ?? '—'}</td>
                <td className="px-4 py-3">
                  {p.content_category ? (
                    <Badge variant={categoryVariant[p.content_category]}>{t(`crawler.category.${p.content_category}`)}</Badge>
                  ) : (
                    <span className="text-muted">—</span>
                  )}
                </td>
                <td className="px-4 py-3 text-muted">
                  {p.content_category === 'html' ? (p.is_indexable ? '✓' : '✗') : '—'}
                </td>
                <td className="px-4 py-3 text-muted">{p.inlinks_count}</td>
                <td className="px-4 py-3 text-muted">{p.issues.length}</td>
              </tr>
            ))}
          </tbody>
        </TableCard>
        <div className="mt-2">
          <Pagination links={pages.links} />
        </div>
      </div>
    </AppLayout>
  );
}
