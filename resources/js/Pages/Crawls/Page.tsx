import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Card, PageHeader } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

type Heading = { level: number; text: string };
type OutLink = { to_url: string; type: string; anchor: string };

interface CrawlPageDetail {
  url: string;
  final_url: string | null;
  status_code: number | null;
  redirect_chain: string[] | null;
  title: string | null;
  meta_description: string | null;
  canonical: string | null;
  meta_robots: string | null;
  word_count: number | null;
  headings: Heading[];
  is_indexable: boolean;
  indexability_reason: string | null;
  in_sitemap: boolean;
  inlinks_count: number;
  structured_data: string[];
  images_missing_alt: string[];
  issues: string[];
  out_links: OutLink[];
}

export default function CrawlsPage({ crawlId, page }: { crawlId: string; page: CrawlPageDetail }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  return (
    <AppLayout title={page.url}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.crawls.show', { project: project!.id, crawl: crawlId })}>{t('crawler.title')}</BackLink>
        </div>

        <PageHeader
          title={page.url}
          subtitle={`${page.status_code ?? '—'} · ${page.word_count ?? 0} ${t('crawler.words')}`}
        />

        <div className="grid gap-4 md:grid-cols-2">
          <Card className="p-4">
            <h3 className="mb-2 font-medium text-foreground">{t('crawler.meta')}</h3>
            <dl className="space-y-1 text-sm">
              <div>
                <dt className="text-muted">Title</dt>
                <dd className="text-foreground">{page.title ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-muted">Description</dt>
                <dd className="text-foreground">{page.meta_description ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-muted">Canonical</dt>
                <dd className="text-foreground">{page.canonical ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-muted">Robots</dt>
                <dd className="text-foreground">{page.meta_robots ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-muted">{t('crawler.col_indexable')}</dt>
                <dd className="text-foreground">{page.is_indexable ? '✓' : `✗ (${page.indexability_reason ?? '—'})`}</dd>
              </div>
              <div>
                <dt className="text-muted">{t('crawler.col_inlinks')}</dt>
                <dd className="text-foreground">{page.inlinks_count}</dd>
              </div>
            </dl>
          </Card>

          <Card className="p-4">
            <h3 className="mb-2 font-medium text-foreground">{t('crawler.headings')}</h3>
            {page.headings.length === 0 ? (
              <p className="text-sm text-muted">—</p>
            ) : (
              <ul className="space-y-1 text-sm text-foreground">
                {page.headings.map((h, i) => (
                  <li key={i} style={{ paddingLeft: (h.level - 1) * 12 }}>
                    H{h.level}: {h.text}
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card className="p-4">
            <h3 className="mb-2 font-medium text-foreground">{t('crawler.issues')}</h3>
            {page.issues.length === 0 ? (
              <p className="text-sm text-muted">—</p>
            ) : (
              <ul className="space-y-1 text-sm text-foreground">
                {page.issues.map((c) => (
                  <li key={c}>{t(`crawler.issue.${c}`)}</li>
                ))}
              </ul>
            )}
          </Card>

          <Card className="p-4">
            <h3 className="mb-2 font-medium text-foreground">
              {t('crawler.images_missing_alt')} ({page.images_missing_alt.length})
            </h3>
            {page.images_missing_alt.length === 0 ? (
              <p className="text-sm text-muted">—</p>
            ) : (
              <ul className="space-y-1 text-sm text-foreground">
                {page.images_missing_alt.map((src) => (
                  <li key={src} className="truncate">
                    {src}
                  </li>
                ))}
              </ul>
            )}
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
