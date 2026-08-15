import AppLayout from '@/Layouts/AppLayout';
import { Badge, BackLink, Card, Flash, LinkButton, PageHeader, Pagination, Select, StatusPill, TableCard } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { durationBetween, formatDuration } from '@/lib/formatDuration';
import { formatBytes } from '@/lib/formatBytes';
import { CrawlContentCategory, CrawlPageRow, CrawlResourceRow, CrawlSummary, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
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

interface CatalogCheck {
  code: string;
  severity: 'error' | 'warning' | 'notice' | 'info';
  status: 'active' | 'planned';
  count: number;
}
type Catalog = Record<string, { count: number; checks: CatalogCheck[] }>;
type Filters = { category: string | null; group: string | null; issue: string | null; view: string | null; resource_type: string | null };

const statusVariant: Record<CrawlStatus, 'neutral' | 'success' | 'warning' | 'danger'> = {
  queued: 'neutral',
  running: 'warning',
  completed: 'success',
  failed: 'danger',
};

// Fixed tab order matching the catalogue's category order.
const CATEGORY_ORDER = [
  'security',
  'response_codes',
  'url',
  'page_title',
  'meta_description',
  'meta_keywords',
  'h1',
  'h2',
  'content',
  'images',
  'canonicals',
  'pagination',
  'links',
  'other',
];

export default function CrawlsShow({
  crawl,
  pages,
  categories,
  catalog,
  resources,
  resourceSummary,
  filters,
}: {
  crawl: CrawlDetail;
  pages: Paginated<CrawlPageRow>;
  categories: CrawlContentCategory[];
  catalog: Catalog;
  resources: Paginated<CrawlResourceRow> | null;
  resourceSummary: Record<string, number>;
  filters: Filters;
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  useEffect(() => {
    if (crawl.status !== 'running' && crawl.status !== 'queued') return;
    const id = setInterval(() => router.reload({ only: ['crawl', 'pages', 'catalog', 'resources'] }), 3000);
    return () => clearInterval(id);
  }, [crawl.status]);

  const activeTab = filters.view === 'resources' ? 'resources' : (filters.group ?? (filters.category ? 'all' : 'overview'));

  // A category is "planned-only" when none of its checks are implemented yet.
  const hasActiveChecks = (key: string) => (catalog[key]?.checks ?? []).some((c) => c.status === 'active');

  function go(params: Record<string, string | null>) {
    const clean: Record<string, string> = {};
    for (const [k, v] of Object.entries(params)) if (v) clean[k] = v;
    router.get(route('app.project.crawls.show', { project: project!.id, crawl: crawl.id }), clean, {
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });
  }

  function pagesTable() {
    return (
      <>
        <TableCard
          columns={[
            { label: t('crawler.col_url') },
            { label: t('crawler.col_status') },
            { label: t('crawler.col_type') },
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
                  {p.content_category ? <Badge>{t(`crawler.category.${p.content_category}`)}</Badge> : <span className="text-muted">—</span>}
                </td>
                <td className="px-4 py-3 text-muted">{p.issues.length}</td>
              </tr>
            ))}
          </tbody>
        </TableCard>
        <div className="mt-2">
          <Pagination links={pages.links} />
        </div>
      </>
    );
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

        {/* Tab bar: Overview · All URLs · categories */}
        <div className="mb-4 flex flex-wrap gap-1 border-b border-line" role="tablist">
          <TabButton active={activeTab === 'overview'} onClick={() => go({})} label={t('crawler.tab_overview')} />
          <TabButton active={activeTab === 'all'} onClick={() => go({ category: filters.category })} label={t('crawler.all_urls')} />
          <TabButton active={activeTab === 'resources'} onClick={() => go({ view: 'resources' })} label={t('crawler.tab_resources')} />
          {CATEGORY_ORDER.map((key) => (
            <TabButton
              key={key}
              active={activeTab === key}
              onClick={() => go({ group: key })}
              label={t(`crawler.category_group.${key}`)}
              count={catalog[key]?.count ?? 0}
              planned={!hasActiveChecks(key)}
            />
          ))}
        </div>

        {activeTab === 'overview' && (
          <>
            {summaryEntries.length > 0 && (
              <div className="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                {summaryEntries.map(([code, count]) => (
                  <Card key={code} className="p-4">
                    <div className="text-2xl font-semibold text-foreground">{count}</div>
                    <div className="text-xs text-muted">{t(`crawler.issue.${code}`)}</div>
                  </Card>
                ))}
              </div>
            )}
            {pagesTable()}
          </>
        )}

        {activeTab === 'all' && (
          <>
            {categories.length > 1 && (
              <div className="mb-3 flex items-center gap-2">
                <span className="text-sm text-muted">{t('crawler.filter_type')}</span>
                <Select value={filters.category ?? ''} onChange={(e) => go({ category: e.target.value || null })} className="w-48">
                  <option value="">{t('crawler.filter_all')}</option>
                  {categories.map((c) => (
                    <option key={c} value={c}>
                      {t(`crawler.category.${c}`)}
                    </option>
                  ))}
                </Select>
              </div>
            )}
            {pagesTable()}
          </>
        )}

        {activeTab === 'resources' && resources && (
          <>
            <div className="mb-3 flex flex-wrap items-center gap-2">
              {Object.entries(resourceSummary).map(([type, count]) => (
                <Badge key={type}>
                  {t(`crawler.category.${type}`)}: {count}
                </Badge>
              ))}
            </div>
            <div className="mb-3 flex items-center gap-2">
              <span className="text-sm text-muted">{t('crawler.filter_type')}</span>
              <Select value={filters.resource_type ?? ''} onChange={(e) => go({ view: 'resources', resource_type: e.target.value || null })} className="w-48">
                <option value="">{t('crawler.filter_all')}</option>
                {['javascript', 'css', 'font', 'image', 'other'].map((ty) => (
                  <option key={ty} value={ty}>
                    {t(`crawler.category.${ty}`)}
                  </option>
                ))}
              </Select>
            </div>
            <TableCard
              columns={[
                { label: t('crawler.col_url') },
                { label: t('crawler.col_type') },
                { label: t('crawler.col_size') },
                { label: t('crawler.col_status') },
                { label: t('crawler.col_references') },
              ]}
            >
              <tbody className="divide-y divide-line">
                {resources.data.map((res) => (
                  <tr key={res.url} className="hover:bg-elevated">
                    <td className="px-4 py-3">
                      <span className="break-all text-foreground">{res.url}</span>
                      <span className="ml-2 text-xs text-muted">{res.is_internal ? t('crawler.resource_internal') : t('crawler.resource_external')}</span>
                    </td>
                    <td className="px-4 py-3">
                      <Badge>{t(`crawler.category.${res.type}`)}</Badge>
                    </td>
                    <td className="px-4 py-3 text-muted">{formatBytes(res.size_bytes)}</td>
                    <td className="px-4 py-3 text-muted">{res.status_code ?? '—'}</td>
                    <td className="px-4 py-3 text-muted">{res.ref_count}</td>
                  </tr>
                ))}
              </tbody>
            </TableCard>
            <div className="mt-2">
              <Pagination links={resources.links} />
            </div>
          </>
        )}

        {CATEGORY_ORDER.includes(activeTab) && (
          <>
            <div className="mb-3 flex items-center gap-2">
              <span className="text-sm text-muted">{t('crawler.filter_issue')}</span>
              <Select value={filters.issue ?? ''} onChange={(e) => go({ group: activeTab, issue: e.target.value || null })} className="w-72">
                <option value="">{t('crawler.filter_all')}</option>
                {(catalog[activeTab]?.checks ?? []).map((check) => (
                  <option key={check.code} value={check.code} disabled={check.status === 'planned'}>
                    {t(`crawler.issue.${check.code}`)}
                    {check.status === 'planned' ? ` (${t('crawler.planned')})` : ` (${check.count})`}
                  </option>
                ))}
              </Select>
            </div>
            {!hasActiveChecks(activeTab) ? (
              <Card className="p-6 text-sm text-muted">{t('crawler.category_planned_note')}</Card>
            ) : (catalog[activeTab]?.count ?? 0) === 0 && !filters.issue ? (
              <Card className="p-6 text-sm text-muted">{t('crawler.category_no_issues')}</Card>
            ) : (
              pagesTable()
            )}
          </>
        )}
      </div>
    </AppLayout>
  );
}

function TabButton({
  active,
  onClick,
  label,
  count,
  planned,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
  count?: number;
  planned?: boolean;
}) {
  return (
    <button
      type="button"
      role="tab"
      aria-selected={active}
      onClick={onClick}
      className={`-mb-px inline-flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
        active ? 'border-accent text-foreground' : 'border-transparent text-muted hover:text-foreground'
      } ${planned ? 'opacity-60' : ''}`}
    >
      {label}
      {count !== undefined && count > 0 && <span className="rounded-full bg-neutral-soft px-1.5 text-xs text-neutral-foreground">{count}</span>}
    </button>
  );
}
