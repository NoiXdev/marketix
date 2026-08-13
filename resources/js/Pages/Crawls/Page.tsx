import AppLayout from '@/Layouts/AppLayout';
import { Badge, BackLink, Card, PageHeader } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatBytes } from '@/lib/formatBytes';
import { CrawlContentCategory, PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

type Heading = { level: number; text: string };
type OutLink = { to_url: string; type: string; anchor: string; status_code: number | null };

interface CrawlPageDetail {
  url: string;
  final_url: string | null;
  status_code: number | null;
  content_category: CrawlContentCategory | null;
  content_type: string | null;
  size_bytes: number | null;
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

  const isHtml = page.content_category === 'html';
  const brokenLinks = page.out_links.filter((l) => l.status_code != null && l.status_code >= 400);

  return (
    <AppLayout title={page.url}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.crawls.show', { project: project!.id, crawl: crawlId })}>{t('crawler.title')}</BackLink>
        </div>

        <PageHeader
          title={page.url}
          subtitle={
            <span className="inline-flex items-center gap-2">
              {page.content_category && <Badge>{t(`crawler.category.${page.content_category}`)}</Badge>}
              <span>
                {page.status_code ?? '—'} · {isHtml ? `${page.word_count ?? 0} ${t('crawler.words')}` : formatBytes(page.size_bytes)}
              </span>
            </span>
          }
        />

        {brokenLinks.length > 0 && (
          <Card className="mb-4 border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] bg-danger-soft p-4">
            <h3 className="mb-2 font-medium text-danger-foreground">
              {t('crawler.broken_links')} ({brokenLinks.length})
            </h3>
            <ul className="space-y-1.5 text-sm">
              {brokenLinks.map((link, i) => (
                <li key={i} className="flex items-start gap-2">
                  <Badge variant="danger" className="shrink-0">
                    {link.status_code}
                  </Badge>
                  <span className="break-all text-foreground">
                    {link.to_url}
                    {link.anchor && <span className="text-muted"> — {link.anchor}</span>}
                  </span>
                </li>
              ))}
            </ul>
          </Card>
        )}

        <div className="grid gap-4 md:grid-cols-2">
          {/* File / resource metadata — shown for every crawled URL. */}
          <Card className="p-4">
            <h3 className="mb-2 font-medium text-foreground">{t('crawler.file')}</h3>
            <dl className="space-y-1 text-sm">
              <div>
                <dt className="text-muted">{t('crawler.content_type')}</dt>
                <dd className="text-foreground">{page.content_type ?? '—'}</dd>
              </div>
              <div>
                <dt className="text-muted">{t('crawler.size')}</dt>
                <dd className="text-foreground">{formatBytes(page.size_bytes)}</dd>
              </div>
              <div>
                <dt className="text-muted">{t('crawler.col_inlinks')}</dt>
                <dd className="text-foreground">{page.inlinks_count}</dd>
              </div>
            </dl>
          </Card>

          {isHtml && (
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
              </dl>
            </Card>
          )}

          {isHtml && (
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
          )}

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
            <h3 className="mb-2 font-medium text-foreground">{t('crawler.redirect_chain')}</h3>
            {!page.redirect_chain || page.redirect_chain.length === 0 ? (
              <p className="text-sm text-muted">—</p>
            ) : (
              <ol className="list-decimal space-y-1 pl-5 text-sm text-foreground">
                {page.redirect_chain.map((url, i) => (
                  <li key={i} className="truncate">
                    {url}
                  </li>
                ))}
              </ol>
            )}
          </Card>

          {isHtml && (
            <Card className="p-4">
              <h3 className="mb-2 font-medium text-foreground">{t('crawler.out_links')}</h3>
              {page.out_links.length === 0 ? (
                <p className="text-sm text-muted">—</p>
              ) : (
                <ul className="space-y-1.5 text-sm text-foreground">
                  {page.out_links.map((link, i) => (
                    <li key={i} className="flex items-center gap-2">
                      <Badge variant={link.type === 'internal' ? 'neutral' : 'accent'} className="shrink-0">
                        {link.type}
                      </Badge>
                      {link.status_code != null && (
                        <Badge
                          variant={link.status_code >= 400 ? 'danger' : link.status_code >= 300 ? 'warning' : 'success'}
                          className="shrink-0"
                        >
                          {link.status_code}
                        </Badge>
                      )}
                      <span className="truncate">{link.anchor || link.to_url}</span>
                    </li>
                  ))}
                </ul>
              )}
            </Card>
          )}

          {isHtml && (
            <Card className="p-4">
              <h3 className="mb-2 font-medium text-foreground">{t('crawler.structured_data')}</h3>
              {page.structured_data.length === 0 ? (
                <p className="text-sm text-muted">—</p>
              ) : (
                <ul className="space-y-1 text-sm text-foreground">
                  {page.structured_data.map((type, i) => (
                    <li key={i}>{type}</li>
                  ))}
                </ul>
              )}
            </Card>
          )}

          {isHtml && (
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
          )}
        </div>
      </div>
    </AppLayout>
  );
}
