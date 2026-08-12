import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Card, Flash, LinkButton, PageHeader, Pagination, StatusPill, TableCard } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { CrawlPageRow, CrawlSummary, PageProps } from '@/types';
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

export default function CrawlsShow({ crawl, pages }: { crawl: CrawlDetail; pages: Paginated<CrawlPageRow> }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  // Poll while the crawl is still queued/running so progress shows up without a manual refresh.
  useEffect(() => {
    if (crawl.status !== 'running' && crawl.status !== 'queued') return;
    const id = setInterval(() => router.reload({ only: ['crawl', 'pages'] }), 3000);
    return () => clearInterval(id);
  }, [crawl.status]);

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
            <span className="inline-flex items-center gap-2">
              <StatusPill status={statusVariant[crawl.status]}>{t(`crawler.status_${crawl.status}`)}</StatusPill>
              <span>
                {crawl.pages_crawled} {t('crawler.pages')}
              </span>
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
            {summaryEntries.map(([code, count]) => (
              <Card key={code} className="p-4">
                <div className="text-2xl font-semibold text-foreground">{count}</div>
                <div className="text-xs text-muted">{t(`crawler.issue.${code}`)}</div>
              </Card>
            ))}
          </div>
        )}

        <TableCard
          columns={[
            { label: t('crawler.col_url') },
            { label: t('crawler.col_status') },
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
                <td className="px-4 py-3 text-muted">{p.is_indexable ? '✓' : '✗'}</td>
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
