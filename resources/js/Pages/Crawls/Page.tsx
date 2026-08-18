import AppLayout from '@/Layouts/AppLayout';
import { Badge, BackLink, Card, PageHeader, SlideOver, TableCard } from '@/Components/ui';
import { CrawlSidebar, CrawlNavItem } from '@/Components/CrawlSidebar';
import { useTranslation } from '@/lib/i18n';
import { formatBytes } from '@/lib/formatBytes';
import { severityBadgeVariant, severityDotClass, severityRank, Severity } from '@/lib/severity';
import { CATEGORY_ORDER } from '@/lib/crawlerCategories';
import { CrawlContentCategory, PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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
  structured_data_items: { format: string; type: string | null; valid: boolean; missing: string[]; error?: string }[];
  hreflang: { lang: string; href: string }[];
  images_missing_alt: string[];
  issues: string[];
  issue_severities: Record<string, 'error' | 'warning' | 'notice' | 'info'>;
  issue_categories: Record<string, string>;
  out_links: OutLink[];
  in_links: InLink[];
  screenshots_enabled: boolean;
  screenshots: { desktop: string | null; mobile: string | null };
}

function truncateText(value: string, max: number): string {
  return value.length > max ? `${value.slice(0, max - 1).trimEnd()}…` : value;
}

/** Format a URL the way Google renders its breadcrumb line, e.g. "example.com › blog › post". */
function serpDisplayUrl(url: string): string {
  try {
    const u = new URL(url);
    const segments = u.pathname.split('/').filter(Boolean);
    return [`${u.protocol}//${u.host}`, ...segments].join(' › ');
  } catch {
    return url;
  }
}

/** Failing codes that have a dedicated evidence card → keep their own sidebar tab. */
const EVIDENCE_CODES = new Set<string>([
  'broken_link', 'missing_alt_text', 'redirect_chain',
  'missing_h1', 'multiple_h1', 'heading_order_skip',
  'missing_title', 'title_too_long', 'missing_meta_description', 'duplicate_title', 'duplicate_meta_description',
  'noindex', 'canonical_mismatch', 'robots_blocked',
  'orphan_page', 'not_in_sitemap',
]);

const SECURITY_HEADER_CHECKS: { code: string; labelKey: string; httpsOnly?: boolean }[] = [
  { code: 'missing_hsts_header', labelKey: 'hsts', httpsOnly: true },
  { code: 'missing_csp_header', labelKey: 'csp' },
  { code: 'missing_x_content_type_options', labelKey: 'x_content_type_options' },
  { code: 'missing_x_frame_options', labelKey: 'x_frame_options' },
  { code: 'missing_referrer_policy', labelKey: 'referrer_policy' },
];

type CheckItem = { key: string; label: string; passed: boolean | null; severity?: Severity; help?: string };
type CheckGroup = { category: string; items: CheckItem[] };

export default function CrawlsPage({ crawlId, page }: { crawlId: string; page: CrawlPageDetail }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const isHtml = page.content_category === 'html';
  const brokenLinks = page.out_links.filter((l) => l.status_code != null && l.status_code >= 400);

  const isHttps = (() => {
    try {
      return new URL(page.final_url ?? page.url).protocol === 'https:';
    } catch {
      return true;
    }
  })();

  const checkGroups: CheckGroup[] = useMemo(() => {
    const issues = page.issues;
    const cat = page.issue_categories ?? {};
    const shown = new Set<string>();
    const groups: CheckGroup[] = [];

    const failRow = (code: string): CheckItem => {
      shown.add(code);
      return {
        key: code,
        label: t(`crawler.issue.${code}`),
        passed: false,
        severity: page.issue_severities[code] ?? 'notice',
        help: t(`crawler.issue_help.${code}`),
      };
    };

    // Security group — HTML pages only: real ✓/✗ audit.
    if (isHtml) {
      const items: CheckItem[] = [];
      items.push({ key: 'https', label: t('crawler.check.https'), passed: isHttps });
      for (const c of SECURITY_HEADER_CHECKS) {
        const passed = c.httpsOnly && !isHttps ? null : !issues.includes(c.code);
        items.push({ key: c.code, label: t(`crawler.check.${c.labelKey}`), passed });
        shown.add(c.code);
      }
      for (const code of issues) {
        if ((page.issue_severities[code] ?? 'notice') === 'info') continue;
        if (shown.has(code) || EVIDENCE_CODES.has(code)) continue;
        if (cat[code] !== 'security') continue;
        items.push(failRow(code));
      }
      groups.push({ category: 'security', items });
    }

    // Every other category: failing detail-less checks as ✗ rows.
    for (const category of CATEGORY_ORDER) {
      if (category === 'security' && isHtml) continue;
      const items: CheckItem[] = [];
      for (const code of issues) {
        if ((page.issue_severities[code] ?? 'notice') === 'info') continue;
        if (shown.has(code) || EVIDENCE_CODES.has(code)) continue;
        if ((cat[code] ?? 'other') !== category) continue;
        items.push(failRow(code));
      }
      if (items.length) groups.push({ category, items });
    }

    return groups;
  }, [page, isHtml, isHttps, t]);

  const [tab, setTab] = useState<string>('overview');
  const [shotDevice, setShotDevice] = useState<'desktop' | 'mobile'>('desktop');
  const [imagePreview, setImagePreview] = useState<string | null>(null);

  const currentShot = page.screenshots[shotDevice] ?? page.screenshots.desktop ?? page.screenshots.mobile ?? null;

  // Image srcs are stored raw (may be relative/protocol-relative); resolve against the page URL for display.
  const resolveImg = (src: string): string => {
    try {
      return new URL(src, page.url).href;
    } catch {
      return src;
    }
  };

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

  const screenshotsCard = (
    <Card className="p-4 md:col-span-2">
      <div className="mb-3 flex items-center justify-between">
        <h3 className="font-medium text-foreground">{t('crawler.screenshots')}</h3>
        {(page.screenshots.desktop || page.screenshots.mobile) && (
          <div className="inline-flex overflow-hidden rounded-lg border border-line">
            {(['desktop', 'mobile'] as const).map((d) => (
              <button
                key={d}
                type="button"
                onClick={() => setShotDevice(d)}
                className={`px-3 py-1 text-xs font-semibold ${
                  shotDevice === d ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'
                }`}
              >
                {t(`crawler.device_${d}`)}
              </button>
            ))}
          </div>
        )}
      </div>
      {currentShot ? (
        <img src={currentShot} alt="" loading="lazy" className="max-h-[600px] w-auto rounded border border-line" />
      ) : (
        <p className="text-sm text-muted">
          {page.screenshots_enabled ? t('crawler.screenshots_pending') : t('crawler.screenshots_disabled')}
        </p>
      )}
    </Card>
  );

  const serpCard = (
    <Card className="p-4 md:col-span-2">
      <h3 className="mb-3 font-medium text-foreground">{t('crawler.serp_preview')}</h3>
      <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-black/10">
        <div className="truncate text-xs text-[#4d5156]">{serpDisplayUrl(page.url)}</div>
        <div className="truncate text-lg leading-tight text-[#1a0dab]">
          {page.title?.trim() ? truncateText(page.title.trim(), 60) : t('crawler.serp_no_title')}
        </div>
        <p className="mt-1 line-clamp-2 text-sm text-[#4d5156]">
          {page.meta_description?.trim() ? truncateText(page.meta_description.trim(), 160) : t('crawler.serp_no_description')}
        </p>
      </div>
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

  const hreflangCard = (
    <Card className="p-4">
      <h3 className="mb-2 font-medium text-foreground">{t('crawler.hreflang')}</h3>
      <ul className="space-y-1.5 text-sm">
        {page.hreflang.map((h, i) => (
          <li key={i} className="flex items-baseline gap-2">
            <span className="shrink-0 font-mono text-xs text-muted">{h.lang}</span>
            <a
              href={h.href}
              target="_blank"
              rel="noopener noreferrer"
              className="truncate text-accent hover:underline"
            >
              {h.href}
            </a>
          </li>
        ))}
      </ul>
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
      {page.structured_data_items.length === 0 ? (
        <p className="text-sm text-muted">{t('crawler.structured_data_none')}</p>
      ) : (
        <ul className="divide-y divide-line text-sm">
          {page.structured_data_items.map((item, i) => {
            const label =
              item.error === 'no_type' || item.type === null
                ? t('crawler.sd_no_type')
                : item.error === 'parse'
                  ? t('crawler.sd_parse_error')
                  : item.type;

            return (
              <li key={i} className="flex flex-wrap items-start gap-2 py-1.5">
                <Badge variant="neutral" className="shrink-0">
                  {item.format}
                </Badge>
                <span className="min-w-0 flex-1">
                  <span className="flex flex-wrap items-center gap-2">
                    <span className="text-foreground">{label}</span>
                    {item.valid ? (
                      <span className="text-success-foreground">✓</span>
                    ) : (
                      <span className="text-danger-foreground">✗</span>
                    )}
                  </span>
                  {!item.valid && item.missing.length > 0 && (
                    <span className="mt-0.5 block text-muted">
                      {t('crawler.sd_missing_fields')}: {item.missing.join(', ')}
                    </span>
                  )}
                </span>
              </li>
            );
          })}
        </ul>
      )}
    </Card>
  );

  const imagesCard = (
    <div className="md:col-span-2">
      <h3 className="mb-2 font-medium text-foreground">
        {t('crawler.images_missing_alt')} ({page.images_missing_alt.length})
      </h3>
      {page.images_missing_alt.filter(Boolean).length === 0 ? (
        <Card className="p-4">
          <p className="text-sm text-muted">—</p>
        </Card>
      ) : (
        <TableCard columns={[{ label: '#' }, { label: t('crawler.col_url') }]}>
          <tbody className="divide-y divide-line">
            {page.images_missing_alt.filter(Boolean).map((src, i) => (
              <tr key={`${src}-${i}`} className="hover:bg-elevated">
                <td className="px-4 py-3 text-muted">{i + 1}</td>
                <td className="px-4 py-3">
                  <button
                    type="button"
                    onClick={() => setImagePreview(src)}
                    className="break-all text-left text-accent hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                  >
                    {src}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </TableCard>
      )}
    </div>
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

  const checksCard = checkGroups.length > 0 && (
    <Card className="p-4 md:col-span-2">
      <h3 className="mb-3 font-medium text-foreground">{t('crawler.checks')}</h3>
      <div className="space-y-4">
        {checkGroups.map((g) => (
          <div key={g.category}>
            <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted">{t(`crawler.category_group.${g.category}`)}</h4>
            <ul className="divide-y divide-line">
              {g.items.map((it) => (
                <li key={it.key} className="flex items-start gap-2 py-1.5 text-sm">
                  <span
                    className="mt-0.5 shrink-0 font-semibold"
                    aria-label={t(it.passed === null ? 'crawler.check_na' : it.passed ? 'crawler.check_passed' : 'crawler.check_failed')}
                  >
                    {it.passed === null ? (
                      <span className="text-muted">—</span>
                    ) : it.passed ? (
                      <span className="text-success-foreground">✓</span>
                    ) : (
                      <span className="text-danger-foreground">✗</span>
                    )}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex flex-wrap items-center gap-2">
                      <span className="text-foreground">{it.label}</span>
                      {it.passed === false && it.severity && (
                        <Badge variant={severityBadgeVariant(it.severity)}>{t(`crawler.severity_${it.severity}`)}</Badge>
                      )}
                    </span>
                    {it.passed === false && it.help && <span className="mt-0.5 block text-muted">{it.help}</span>}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
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

        <div className="flex flex-col gap-6 md:flex-row">
          <CrawlSidebar
            primary={[{ key: 'overview', label: t('crawler.tab_overview'), active: tab === 'overview', onSelect: () => setTab('overview') }]}
            sectionLabel={t('crawler.issues')}
            items={[...page.issues]
              .filter((code) => EVIDENCE_CODES.has(code))
              .sort((a, b) => severityRank(page.issue_severities[b] ?? 'notice') - severityRank(page.issue_severities[a] ?? 'notice'))
              .map(
                (code): CrawlNavItem => ({
                  key: code,
                  label: t(`crawler.issue.${code}`),
                  active: tab === code,
                  onSelect: () => setTab(code),
                  dotClass: severityDotClass(page.issue_severities[code] ?? 'notice'),
                }),
              )}
          />

          <div className="min-w-0 flex-1">
            {tab === 'overview' ? (
              <div className="grid gap-4 md:grid-cols-2">
                {isHtml && serpCard}
                {isHtml && page.screenshots_enabled && screenshotsCard}
                {checksCard}
                {fileCard}
                {foundOnCard}
                {isHtml && metaCard}
                {isHtml && page.hreflang.length > 0 && hreflangCard}
                {isHtml && headingsCard}
                {isHtml && outLinksCard}
                {isHtml && structuredDataCard}
                {isHtml && imagesCard}
                {redirectChainCard}
              </div>
            ) : (
              <div className="space-y-4">
                <div className="flex items-center gap-2">
                  <Badge variant={severityBadgeVariant(page.issue_severities[tab] ?? 'notice')}>
                    {t(`crawler.severity_${page.issue_severities[tab] ?? 'notice'}`)}
                  </Badge>
                  <h2 className="font-medium text-foreground">{t(`crawler.issue.${tab}`)}</h2>
                </div>
                <p className="text-sm text-muted">{t(`crawler.issue_help.${tab}`)}</p>
                <div className="grid gap-4 md:grid-cols-2">{issueEvidence(tab)}</div>
              </div>
            )}
          </div>
        </div>
      </div>

      <SlideOver
        open={imagePreview !== null}
        onClose={() => setImagePreview(null)}
        title={t('crawler.image_preview')}
        closeLabel={t('crawler.close')}
      >
        {imagePreview && (
          <div className="space-y-4">
            <img
              src={resolveImg(imagePreview)}
              alt=""
              className="max-h-[70vh] w-auto max-w-full rounded border border-line"
            />
            <div className="break-all text-sm text-muted">{imagePreview}</div>
            <a
              href={resolveImg(imagePreview)}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-block text-sm text-accent hover:underline"
            >
              {t('crawler.open_in_new_tab')}
            </a>
          </div>
        )}
      </SlideOver>
    </AppLayout>
  );
}
