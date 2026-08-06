import ReportDownloadButton from '@/Components/ReportDownloadButton';
import WorldMap, { CountryDatum } from '@/Components/WorldMap';
import AppLayout from '@/Layouts/AppLayout';
import ClicksChart from '@/Pages/Dashboard/ClicksChart';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import RankedList from '@/Pages/Dashboard/RankedList';
import { Button, StatusPill } from '@/Components/ui';
import { confirmTyped } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Calendar, Check, Copy, ExternalLink, MousePointerClick, Pencil, QrCode as QrCodeIcon, RotateCcw } from 'lucide-react';
import { useState } from 'react';

interface DayClicks { date: string; clicks: number; unique: number }
interface BreakdownRow { count: number; [key: string]: string | number }
interface RecentClick {
  id: string;
  country: string | null;
  city: string | null;
  browser: string | null;
  os: string | null;
  domain: string | null;
  created_at: string;
}
interface LinkDetail {
  id: string;
  slug: string;
  url: string;
  type: number;
  type_label: string;
  status: number;
  clicks: number;
  unique_clicks: number;
  expired_at: string | null;
  created_at: string;
  has_qr_code: boolean;
  domain: { id: string; name: string } | null;
}

interface Props {
  link: LinkDetail;
  days: number;
  rangeClicks: number;
  rangeUnique: number;
  clicksByDay: DayClicks[];
  topCountries: (BreakdownRow & { country: string })[];
  clicksByCountry: CountryDatum[];
  topCities: (BreakdownRow & { city: string })[];
  topBrowsers: (BreakdownRow & { browser: string })[];
  topOs: (BreakdownRow & { os: string })[];
  topReferrers: (BreakdownRow & { domain: string })[];
  recentClicks: RecentClick[];
}

const RANGES = [7, 30, 90];

function CopyButton({ text }: { text: string }) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);
  function copy() {
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }
  return (
    <button
      onClick={copy}
      title={copied ? t('links.copy.copied') : t('links.copy.idle')}
      className={`rounded-md p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
        copied ? 'text-success-foreground' : 'text-subtle hover:bg-elevated hover:text-foreground'
      }`}
    >
      {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
    </button>
  );
}

function Breakdown({ title, rows, labelKey, emptyLabel }: { title: string; rows: BreakdownRow[]; labelKey: string; emptyLabel: string }) {
  return (
    <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
      <div className="border-b border-line px-4 py-3">
        <h2 className="text-sm font-semibold text-foreground">{title}</h2>
      </div>
      <RankedList
        emptyLabel={emptyLabel}
        rows={rows.map((r, i) => ({ key: `${String(r[labelKey] ?? '')}-${i}`, label: String(r[labelKey] || '—'), value: r.count }))}
      />
    </section>
  );
}

const linkBtn =
  'inline-flex items-center gap-2 rounded-[var(--radius-sm)] border border-line-strong bg-surface px-3 py-1.5 text-sm font-semibold text-foreground transition-colors hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]';

export default function LinksShow({
  link, days, rangeClicks, rangeUnique, clicksByDay,
  topCountries, topCities, topBrowsers, topOs, topReferrers, recentClicks,
  clicksByCountry,
}: Props) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const shortUrl = link.domain ? `https://${link.domain.name}/${link.slug}` : link.slug;

  function setDays(d: number) {
    router.get(
      route('app.project.links.show', { project: project!.id, url: link.id }),
      { days: d },
      { preserveState: true, preserveScroll: true },
    );
  }

  async function resetStats() {
    const ok = await confirmTyped({
      title: t('links.reset_stats.title'),
      text: t('links.reset_stats.confirm', { slug: link.slug }),
      match: link.slug,
      confirmText: t('links.reset_stats.button'),
      mismatchText: t('links.reset_stats.mismatch', { slug: link.slug }),
    });
    if (!ok) return;
    router.delete(route('app.project.links.stats.reset', { project: project!.id, url: link.id }));
  }

  return (
    <AppLayout title={t('links.show.page_title', { slug: link.slug })}>
      <div className="px-8 py-8">
        {/* Header / detail card */}
        <div className="mb-6 rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <div className="flex items-center gap-1 text-xl font-bold text-foreground">
                {link.domain && <span className="text-subtle">{link.domain.name}/</span>}
                <span className="truncate">{link.slug}</span>
                <CopyButton text={shortUrl} />
                {link.status === 1 ? (
                  <span className="ml-2"><StatusPill status="success">{t('links.status.active')}</StatusPill></span>
                ) : (
                  <span className="ml-2"><StatusPill status="neutral">{t('links.status.inactive')}</StatusPill></span>
                )}
              </div>
              <a
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-2 inline-flex max-w-full items-center gap-1 truncate text-sm text-muted hover:text-accent-soft-foreground"
              >
                <span className="truncate">{link.url}</span>
                <ExternalLink className="h-3 w-3 shrink-0" />
              </a>
              <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-subtle">
                <span>{link.type_label}</span>
                <span>{t('links.show.created', { date: new Date(link.created_at).toLocaleDateString() })}</span>
                {link.expired_at && <span className="text-warning-foreground">{t('links.expires', { date: new Date(link.expired_at).toLocaleDateString() })}</span>}
                {link.has_qr_code && <span className="inline-flex items-center gap-1"><QrCodeIcon className="h-3.5 w-3.5" /> {t('links.show.qr_code')}</span>}
              </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              {!link.has_qr_code && (
                <Link href={route('app.project.qrcodes.create', { project: project!.id, link: link.id })} className={linkBtn}>
                  <QrCodeIcon className="h-4 w-4" />
                  {t('links.actions.create_qr')}
                </Link>
              )}
              <Link href={route('app.project.links.edit', { project: project!.id, url: link.id })} className={linkBtn}>
                <Pencil className="h-4 w-4" />
                {t('common.actions.edit')}
              </Link>
              <ReportDownloadButton projectId={project!.id} urlId={link.id} />
              <Button variant="danger" onClick={resetStats}>
                <RotateCcw className="h-4 w-4" />
                {t('links.reset_stats.button')}
              </Button>
            </div>
          </div>
        </div>

        {/* Range selector */}
        <div className="mb-6 flex justify-end">
          <div className="inline-flex overflow-hidden rounded-lg border border-line">
            {RANGES.map((d) => (
              <button
                key={d}
                onClick={() => setDays(d)}
                className={`border-r border-line px-3 py-1.5 text-sm font-semibold last:border-r-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                  days === d ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'
                }`}
              >
                {d}
                {t('common.dashboard.range_days')}
              </button>
            ))}
          </div>
        </div>

        {/* Summary cards */}
        <div className="mb-6 grid grid-cols-2 gap-3.5 lg:grid-cols-4">
          <KpiTile compact={false} label={t('links.show.cards.clicks_alltime')} value={link.clicks} icon={BarChart3} />
          <KpiTile compact={false} label={t('links.show.cards.unique_alltime')} value={link.unique_clicks} icon={MousePointerClick} />
          <KpiTile compact={false} label={t('links.show.cards.clicks_range', { days: String(days) })} value={rangeClicks} icon={Calendar} />
          <KpiTile compact={false} label={t('links.show.cards.unique_range', { days: String(days) })} value={rangeUnique} icon={Calendar} />
        </div>

        {/* Clicks over time */}
        <section className="mb-6 rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
          <div className="border-b border-line px-4 py-3.5">
            <h2 className="text-sm font-semibold text-foreground">
              {t('links.show.clicks_over_time')} <span className="font-normal text-muted">{t('links.show.last_days', { days: String(days) })}</span>
            </h2>
          </div>
          <div className="p-4"><ClicksChart data={clicksByDay} /></div>
        </section>

        {/* Clicks by country map */}
        <div className="mb-6">
          <WorldMap data={clicksByCountry} />
        </div>

        {/* Breakdowns */}
        <div className="mb-6 grid grid-cols-1 gap-3.5 md:grid-cols-2">
          <Breakdown title={t('links.show.breakdown.countries')} rows={topCountries} labelKey="country" emptyLabel={t('links.show.no_data')} />
          <Breakdown title={t('links.show.breakdown.cities')} rows={topCities} labelKey="city" emptyLabel={t('links.show.no_data')} />
          <Breakdown title={t('links.show.breakdown.browsers')} rows={topBrowsers} labelKey="browser" emptyLabel={t('links.show.no_data')} />
          <Breakdown title={t('links.show.breakdown.os')} rows={topOs} labelKey="os" emptyLabel={t('links.show.no_data')} />
          <Breakdown title={t('links.show.breakdown.referrers')} rows={topReferrers} labelKey="domain" emptyLabel={t('links.show.no_data')} />
        </div>

        {/* Recent clicks */}
        <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
          <div className="border-b border-line px-4 py-3">
            <h2 className="text-sm font-semibold text-foreground">
              {t('links.show.recent_clicks')} <span className="font-normal text-muted">{t('links.show.last_days', { days: String(days) })}</span>
            </h2>
          </div>
          {recentClicks.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-subtle">{t('links.show.no_clicks')}</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-line">
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-muted">{t('links.show.columns.when')}</th>
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-muted">{t('links.show.columns.location')}</th>
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-muted">{t('links.show.columns.device')}</th>
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-muted">{t('links.show.columns.referrer')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {recentClicks.map((c) => (
                  <tr key={c.id}>
                    <td className="px-4 py-2.5 text-muted">{new Date(c.created_at).toLocaleString()}</td>
                    <td className="px-4 py-2.5 text-muted">{[c.city, c.country].filter(Boolean).join(', ') || '—'}</td>
                    <td className="px-4 py-2.5 text-muted">{[c.browser, c.os].filter(Boolean).join(' · ') || '—'}</td>
                    <td className="px-4 py-2.5 text-muted">{c.domain || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </div>
    </AppLayout>
  );
}
