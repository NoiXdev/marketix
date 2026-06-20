import ReportDownloadButton from '@/Components/ReportDownloadButton';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
  BarChart3,
  Calendar,
  Check,
  Copy,
  ExternalLink,
  MousePointerClick,
  Pencil,
  QrCode as QrCodeIcon,
} from 'lucide-react';
import { useState } from 'react';
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  LabelList,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

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
  topCities: (BreakdownRow & { city: string })[];
  topBrowsers: (BreakdownRow & { browser: string })[];
  topOs: (BreakdownRow & { os: string })[];
  topReferrers: (BreakdownRow & { domain: string })[];
  recentClicks: RecentClick[];
}

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
      className={`rounded p-1.5 transition-colors ${
        copied
          ? 'text-green-500'
          : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200'
      }`}
    >
      {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
    </button>
  );
}

function BreakdownChart({
  title,
  rows,
  labelKey,
}: {
  title: string;
  rows: BreakdownRow[];
  labelKey: string;
}) {
  const { t } = useTranslation();
  const data = rows.map((r) => ({ label: String(r[labelKey] || '—'), count: r.count as number }));
  return (
    <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
        <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">{title}</h2>
      </div>
      {data.length === 0 ? (
        <p className="px-4 py-6 text-center text-sm text-slate-400">{t('links.show.no_data')}</p>
      ) : (
        <div className="p-3" style={{ height: Math.max(data.length * 34 + 16, 80) }}>
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} layout="vertical" margin={{ left: 8, right: 32, top: 4, bottom: 4 }}>
              <XAxis type="number" hide />
              <YAxis
                type="category"
                dataKey="label"
                width={110}
                tick={{ fontSize: 12, fill: 'currentColor' }}
                className="text-slate-500 dark:text-slate-400"
                axisLine={false}
                tickLine={false}
              />
              <Bar dataKey="count" radius={[0, 4, 4, 0]} fill="#6366f1">
                <LabelList dataKey="count" position="right" className="fill-slate-500 text-xs" />
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  );
}

export default function LinksShow({
  link, days, rangeClicks, rangeUnique, clicksByDay,
  topCountries, topCities, topBrowsers, topOs, topReferrers, recentClicks,
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

  const summaryCards = [
    { label: t('links.show.cards.clicks_alltime'), value: link.clicks.toLocaleString(), icon: BarChart3, color: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20 dark:text-indigo-400' },
    { label: t('links.show.cards.unique_alltime'), value: link.unique_clicks.toLocaleString(), icon: MousePointerClick, color: 'text-violet-600 bg-violet-50 dark:bg-violet-900/20 dark:text-violet-400' },
    { label: t('links.show.cards.clicks_range', { days: String(days) }), value: rangeClicks.toLocaleString(), icon: Calendar, color: 'text-sky-600 bg-sky-50 dark:bg-sky-900/20 dark:text-sky-400' },
    { label: t('links.show.cards.unique_range', { days: String(days) }), value: rangeUnique.toLocaleString(), icon: Calendar, color: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 dark:text-emerald-400' },
  ];

  return (
    <AppLayout title={t('links.show.page_title', { slug: link.slug })}>
      <div className="px-8 py-8">
        {/* Header / detail card */}
        <div className="mb-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <div className="flex items-start justify-between gap-4">
            <div className="min-w-0">
              <div className="flex items-center gap-1 text-xl font-bold text-slate-900 dark:text-white">
                {link.domain && <span className="text-slate-400 dark:text-slate-500">{link.domain.name}/</span>}
                <span className="truncate">{link.slug}</span>
                <CopyButton text={shortUrl} />
                {link.status === 1 ? (
                  <span className="ml-2 inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">{t('links.status.active')}</span>
                ) : (
                  <span className="ml-2 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">{t('links.status.inactive')}</span>
                )}
              </div>
              <a
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-2 inline-flex max-w-full items-center gap-1 truncate text-sm text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400"
              >
                <span className="truncate">{link.url}</span>
                <ExternalLink className="h-3 w-3 shrink-0" />
              </a>
              <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400">
                <span>{link.type_label}</span>
                <span>{t('links.show.created', { date: new Date(link.created_at).toLocaleDateString() })}</span>
                {link.expired_at && <span className="text-amber-500">{t('links.expires', { date: new Date(link.expired_at).toLocaleDateString() })}</span>}
                {link.has_qr_code && <span className="inline-flex items-center gap-1"><QrCodeIcon className="h-3.5 w-3.5" /> {t('links.show.qr_code')}</span>}
              </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              {!link.has_qr_code && (
                <Link
                  href={route('app.project.qrcodes.create', { project: project!.id, link: link.id })}
                  className="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                  <QrCodeIcon className="h-4 w-4" />
                  {t('links.actions.create_qr')}
                </Link>
              )}
              <Link
                href={route('app.project.links.edit', { project: project!.id, url: link.id })}
                className="inline-flex items-center gap-2 rounded-md border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
              >
                <Pencil className="h-4 w-4" />
                {t('common.actions.edit')}
              </Link>
              <ReportDownloadButton projectId={project!.id} urlId={link.id} />
            </div>
          </div>
        </div>

        {/* Range selector */}
        <div className="mb-6 flex justify-end">
          <div className="flex rounded-lg border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-900">
            {[7, 30, 90].map((d) => (
              <button
                key={d}
                onClick={() => setDays(d)}
                className={`px-3 py-1.5 transition-colors first:rounded-l-lg last:rounded-r-lg ${
                  days === d
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800'
                }`}
              >
                {d}d
              </button>
            ))}
          </div>
        </div>

        {/* Summary cards */}
        <div className="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
          {summaryCards.map(({ label, value, icon: Icon, color }) => (
            <div key={label} className="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
              <div className="flex items-center justify-between">
                <p className="text-sm font-medium text-slate-500 dark:text-slate-400">{label}</p>
                <span className={`rounded-lg p-2 ${color}`}><Icon className="h-4 w-4" /></span>
              </div>
              <p className="mt-3 text-3xl font-bold text-slate-900 dark:text-white">{value}</p>
            </div>
          ))}
        </div>

        {/* Clicks over time */}
        <div className="mb-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <h2 className="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            {t('links.show.clicks_over_time')} <span className="font-normal text-slate-400">{t('links.show.last_days', { days: String(days) })}</span>
          </h2>
          <div className="h-64">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={clicksByDay} margin={{ left: -20, right: 8, top: 4, bottom: 4 }}>
                <defs>
                  <linearGradient id="fillClicks" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#6366f1" stopOpacity={0.3} />
                    <stop offset="95%" stopColor="#6366f1" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" className="stroke-slate-100 dark:stroke-slate-800" vertical={false} />
                <XAxis dataKey="date" tick={{ fontSize: 11, fill: 'currentColor' }} className="text-slate-400" tickLine={false} axisLine={false} minTickGap={24} />
                <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: 'currentColor' }} className="text-slate-400" tickLine={false} axisLine={false} width={40} />
                <Tooltip contentStyle={{ fontSize: 12, borderRadius: 8 }} />
                <Area type="monotone" dataKey="clicks" name="Clicks" stroke="#6366f1" strokeWidth={2} fill="url(#fillClicks)" />
                <Area type="monotone" dataKey="unique" name="Unique" stroke="#a855f7" strokeWidth={2} fill="none" strokeDasharray="4 3" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        </div>

        {/* Breakdowns */}
        <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-2">
          <BreakdownChart title={t('links.show.breakdown.countries')} rows={topCountries} labelKey="country" />
          <BreakdownChart title={t('links.show.breakdown.cities')} rows={topCities} labelKey="city" />
          <BreakdownChart title={t('links.show.breakdown.browsers')} rows={topBrowsers} labelKey="browser" />
          <BreakdownChart title={t('links.show.breakdown.os')} rows={topOs} labelKey="os" />
          <BreakdownChart title={t('links.show.breakdown.referrers')} rows={topReferrers} labelKey="domain" />
        </div>

        {/* Recent clicks */}
        <div className="rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
          <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
            <h2 className="text-sm font-semibold text-slate-700 dark:text-slate-300">
              {t('links.show.recent_clicks')} <span className="font-normal text-slate-400">{t('links.show.last_days', { days: String(days) })}</span>
            </h2>
          </div>
          {recentClicks.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-slate-400">{t('links.show.no_clicks')}</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 dark:border-slate-800">
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">{t('links.show.columns.when')}</th>
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">{t('links.show.columns.location')}</th>
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">{t('links.show.columns.device')}</th>
                  <th className="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">{t('links.show.columns.referrer')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {recentClicks.map((c) => (
                  <tr key={c.id}>
                    <td className="px-4 py-2.5 text-slate-600 dark:text-slate-400">{new Date(c.created_at).toLocaleString()}</td>
                    <td className="px-4 py-2.5 text-slate-600 dark:text-slate-400">{[c.city, c.country].filter(Boolean).join(', ') || '—'}</td>
                    <td className="px-4 py-2.5 text-slate-600 dark:text-slate-400">{[c.browser, c.os].filter(Boolean).join(' · ') || '—'}</td>
                    <td className="px-4 py-2.5 text-slate-600 dark:text-slate-400">{c.domain || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
