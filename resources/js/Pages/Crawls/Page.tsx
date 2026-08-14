import AppLayout from '@/Layouts/AppLayout';
import { Badge, BackLink, Card, PageHeader } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { formatBytes } from '@/lib/formatBytes';
import { CrawlContentCategory, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Heading = { level: number; text: string };
type OutLink = { to_url: string; type: string; anchor: string; status_code: number | null };
type InLink = { from_page_id: string; from_url: string; anchor: string | null };

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
  issue_severities: Record<string, 'error' | 'warning' | 'notice'>;
  out_links: OutLink[];
  in_links: InLink[];
}

const severityVariant: Record<'error' | 'warning' | 'notice', 'danger' | 'warning' | 'neutral'> = {
  error: 'danger',
  warning: 'warning',
  notice: 'neutral',
};

const severityDot: Record<'error' | 'warning' | 'notice', string> = {
  error: 'bg-danger-foreground',
  warning: 'bg-warning-foreground',
  notice: 'bg-neutral-foreground',
};

export default function CrawlsPage({ crawlId, page }: { crawlId: string; page: CrawlPageDetail }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const isHtml = page.content_category === 'html';
  const brokenLinks = page.out_links.filter((l) => l.status_code != null && l.status_code >= 400);

  const [tab, setTab] = useState<string>('overview');

  const brokenLinksCard = brokenLinks.length > 0 && (
    <Card className="border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] bg-danger-soft p-4 md:col-span-2">
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
  );

  const fileCard = (
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
        {isHtml && (
          <div>
            <dt className="text-muted">{t('crawler.col_indexable')}</dt>
            <dd className="text-foreground">{page.is_indexable ? '✓' : `✗ (${page.indexability_reason ?? '—'})`}</dd>
          </div>
        )}
      </dl>
    </Card>
  );

  const metaCard = (
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
      </dl>
    </Card>
  );

  const headingsCard = (
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
  );

  const structuredDataCard = (
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
  );

  const imagesCard = (
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
  );

  const foundOnCard = (
    <Card className="p-4 md:col-span-2">
      <h3 className="mb-2 font-medium text-foreground">
        {t('crawler.found_on')} ({page.in_links.length})
      </h3>
      {page.in_links.length === 0 ? (
        <p className="text-sm text-muted">
          {t('crawler.no_inlinks')}
          {page.in_sitemap && <> · {t('crawler.from_sitemap')}</>}
        </p>
      ) : (
        <ul className="space-y-1.5 text-sm">
          {page.in_links.map((link, i) => (
            <li key={i} className="flex flex-wrap items-baseline gap-x-2">
              <Link
                href={route('app.project.crawls.pages.show', { project: project!.id, crawl: crawlId, page: link.from_page_id })}
                className="break-all text-accent hover:underline"
              >
                {link.from_url}
              </Link>
              {link.anchor && <span className="text-muted">„{link.anchor}"</span>}
            </li>
          ))}
        </ul>
      )}
    </Card>
  );

  const outLinksCard = (
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
  );

  const redirectChainCard = (
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
  );

  // The evidence card most relevant to a given issue, shown inside that issue's tab.
  function issueEvidence(code: string) {
    switch (code) {
      case 'broken_link':
        return brokenLinksCard || null;
      case 'missing_alt_text':
        return imagesCard;
      case 'redirect_chain':
        return redirectChainCard;
      case 'missing_structured_data':
        return structuredDataCard;
      case 'missing_h1':
      case 'multiple_h1':
      case 'heading_order_skip':
        return headingsCard;
      case 'missing_title':
      case 'title_too_long':
      case 'missing_meta_description':
      case 'duplicate_title':
      case 'duplicate_meta_description':
      case 'noindex':
      case 'canonical_mismatch':
      case 'robots_blocked':
        return metaCard;
      case 'orphan_page':
      case 'not_in_sitemap':
        return foundOnCard;
      default:
        return fileCard;
    }
  }

  const tabs = [
    { key: 'overview', label: t('crawler.tab_overview'), severity: null as string | null },
    ...page.issues.map((code) => ({
      key: code,
      label: t(`crawler.issue.${code}`),
      severity: page.issue_severities[code] ?? 'notice',
    })),
  ];

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

        <div className="mb-4 flex flex-wrap gap-1 border-b border-line" role="tablist">
          {tabs.map((tb) => (
            <button
              key={tb.key}
              type="button"
              role="tab"
              aria-selected={tab === tb.key}
              onClick={() => setTab(tb.key)}
              className={`-mb-px inline-flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                tab === tb.key ? 'border-accent text-foreground' : 'border-transparent text-muted hover:text-foreground'
              }`}
            >
              {tb.severity && (
                <span className={`h-2 w-2 shrink-0 rounded-full ${severityDot[tb.severity as 'error' | 'warning' | 'notice']}`} />
              )}
              {tb.label}
            </button>
          ))}
        </div>

        {tab === 'overview' ? (
          <div className="grid gap-4 md:grid-cols-2">
            {fileCard}
            {foundOnCard}
            {isHtml && metaCard}
            {isHtml && headingsCard}
            {isHtml && outLinksCard}
            {isHtml && structuredDataCard}
            {isHtml && imagesCard}
            {redirectChainCard}
          </div>
        ) : (
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <Badge variant={severityVariant[page.issue_severities[tab] ?? 'notice']}>
                {t(`crawler.severity_${page.issue_severities[tab] ?? 'notice'}`)}
              </Badge>
              <h2 className="font-medium text-foreground">{t(`crawler.issue.${tab}`)}</h2>
            </div>
            <p className="text-sm text-muted">{t(`crawler.issue_help.${tab}`)}</p>
            <div className="grid gap-4 md:grid-cols-2">{issueEvidence(tab)}</div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
