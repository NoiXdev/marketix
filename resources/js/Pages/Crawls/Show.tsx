import AppLayout from '@/Layouts/AppLayout';
import { Badge, BackLink, Card, Flash, LinkButton, PageHeader, Pagination, Select, StatusPill, TableCard } from '@/Components/ui';
import { CrawlSidebar, CrawlNavItem } from '@/Components/CrawlSidebar';
import { useTranslation } from '@/lib/i18n';
import { durationBetween, formatDuration } from '@/lib/formatDuration';
import { formatBytes } from '@/lib/formatBytes';
import { severityDotClass, severityRank, Severity } from '@/lib/severity';
import { CrawlContentCategory, CrawlPageRow, CrawlResourceRow, CrawlSummary, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';

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
type Filters = {
  category: string | null;
  group: string | null;
  issue: string | null;
  view: string | null;
  resource_type: string | null;
  resource: string | null;
};

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
  resourceRefs,
  filters,
}: {
  crawl: CrawlDetail;
  pages: Paginated<CrawlPageRow>;
  categories: CrawlContentCategory[];
  catalog: Catalog;
  resources: Paginated<CrawlResourceRow> | null;
  resourceSummary: Record<string, { count: number; total_bytes: number }>;
  resourceRefs: { url: string; pages: Paginated<{ id: string; url: string; status_code: number | null }> } | null;
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

  // Sum of active checks' counts by severity, across all categories (info severity excluded).
  const severityTotals = useMemo(() => {
    const totals: Record<'error' | 'warning' | 'notice', number> = { error: 0, warning: 0, notice: 0 };
    for (const entry of Object.values(catalog)) {
      for (const check of entry.checks) {
        if (check.status !== 'active' || check.severity === 'info') continue;
        totals[check.severity] += check.count;
      }
    }
    return totals;
  }, [catalog]);

  // Active, non-info checks with count > 0, flattened across categories, sorted by count desc, top 8.
  // (info is never a "problem" — same rule severityTotals applies.)
  const topIssues = useMemo(() => {
    const flat: { code: string; severity: Severity; count: number; category: string }[] = [];
    for (const [category, entry] of Object.entries(catalog)) {
      for (const check of entry.checks) {
        if (check.status === 'active' && check.severity !== 'info' && check.count > 0) {
          flat.push({ code: check.code, severity: check.severity, count: check.count, category });
        }
      }
    }
    return flat.sort((a, b) => b.count - a.count).slice(0, 8);
  }, [catalog]);

  // Dot class for the worst active severity with count > 0 in a category, or undefined when it has none.
  const categoryDot = useMemo(() => {
    return (cat: string): string | undefined => {
      let worst: Severity | null = null;
      for (const check of catalog[cat]?.checks ?? []) {
        if (check.status === 'active' && check.count > 0 && (!worst || severityRank(check.severity) > severityRank(worst))) {
          worst = check.severity;
        }
      }
      return worst ? severityDotClass(worst) : undefined;
    };
  }, [catalog]);

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

        <div className="flex flex-col gap-6 md:flex-row">
          <CrawlSidebar
            primary={[
              { key: 'overview', label: t('crawler.tab_overview'), active: activeTab === 'overview', onSelect: () => go({}) },
              { key: 'all', label: t('crawler.all_urls'), active: activeTab === 'all', onSelect: () => go({ category: filters.category }) },
              { key: 'resources', label: t('crawler.tab_resources'), active: activeTab === 'resources', onSelect: () => go({ view: 'resources' }) },
            ]}
            sectionLabel={t('crawler.category_group_section')}
            items={CATEGORY_ORDER.map(
              (key): CrawlNavItem => ({
                key,
                label: t(`crawler.category_group.${key}`),
                active: activeTab === key,
                onSelect: () => go({ group: key }),
                count: catalog[key]?.count ?? 0,
                dotClass: categoryDot(key),
                dimmed: (catalog[key]?.count ?? 0) === 0,
              }),
            )}
          />

          <div className="min-w-0 flex-1">
            {activeTab === 'overview' && (
              <>
                <div className="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                  <Card className="border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] bg-danger-soft p-4">
                    <div className="text-2xl font-semibold text-danger-foreground">{severityTotals.error}</div>
                    <div className="text-xs text-danger-foreground">{t('crawler.severity_error')}</div>
                  </Card>
                  <Card className="border-[color:color-mix(in_srgb,var(--warning-foreground)_35%,transparent)] bg-warning-soft p-4">
                    <div className="text-2xl font-semibold text-warning-foreground">{severityTotals.warning}</div>
                    <div className="text-xs text-warning-foreground">{t('crawler.severity_warning')}</div>
                  </Card>
                  <Card className="border-[color:color-mix(in_srgb,var(--neutral-foreground)_35%,transparent)] bg-neutral-soft p-4">
                    <div className="text-2xl font-semibold text-neutral-foreground">{severityTotals.notice}</div>
                    <div className="text-xs text-neutral-foreground">{t('crawler.severity_notice')}</div>
                  </Card>
                </div>

                <h3 className="mb-3 text-sm font-medium text-foreground">{t('crawler.dashboard_top_issues')}</h3>
                {topIssues.length === 0 ? (
                  <Card className="p-6 text-sm text-muted">{t('crawler.category_no_issues')}</Card>
                ) : (
                  <div className="overflow-hidden rounded-[var(--radius)] border border-line bg-surface">
                    <div className="divide-y divide-line">
                      {topIssues.map((issue) => (
                        <button
                          key={issue.code}
                          type="button"
                          onClick={() => go({ group: issue.category, issue: issue.code })}
                          className="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                        >
                          <span className="flex min-w-0 items-center gap-2">
                            <span className={`h-1.5 w-1.5 shrink-0 rounded-full ${severityDotClass(issue.severity)}`} />
                            <span className="truncate text-foreground">{t(`crawler.issue.${issue.code}`)}</span>
                          </span>
                          <span className="shrink-0 text-muted">{issue.count}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                )}
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

          {activeTab === 'resources' && filters.resource && resourceRefs && (
            <>
              <div className="mb-3">
                <button type="button" onClick={() => go({ view: 'resources' })} className="text-sm text-accent-soft-foreground hover:underline">
                  ← {t('crawler.tab_resources')}
                </button>
              </div>
              <div className="mb-3 break-all text-sm text-muted">
                {t('crawler.resource_referenced_on')}: <span className="text-foreground">{resourceRefs.url}</span>
              </div>
              <TableCard columns={[{ label: t('crawler.col_url') }, { label: t('crawler.col_status') }]}>
                <tbody className="divide-y divide-line">
                  {resourceRefs.pages.data.map((p) => (
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
                    </tr>
                  ))}
                </tbody>
              </TableCard>
              <div className="mt-2">
                <Pagination links={resourceRefs.pages.links} />
              </div>
            </>
          )}

          {activeTab === 'resources' && !filters.resource && resources && (
            <>
              <div className="mb-3 flex flex-wrap items-center gap-2">
                {Object.entries(resourceSummary).map(([type, s]) => (
                  <Badge key={type}>
                    {t(`crawler.category.${type}`)}: {s.count} · {formatBytes(s.total_bytes)}
                  </Badge>
                ))}
              </div>
              <div className="mb-3 flex items-center gap-2">
                <span className="text-sm text-muted">{t('crawler.filter_type')}</span>
                <Select value={filters.resource_type ?? ''} onChange={(e) => go({ view: 'resources', resource_type: e.target.value || null })} className="w-48">
                  <option value="">{t('crawler.filter_all')}</option>
                  {Object.keys(resourceSummary)
                    .sort()
                    .map((ty) => (
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
                        <button
                          type="button"
                          onClick={() => go({ view: 'resources', resource: res.url })}
                          className="break-all text-left font-medium text-foreground hover:text-accent-soft-foreground"
                        >
                          {res.url}
                        </button>
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
        </div>
      </div>
    </AppLayout>
  );
}
