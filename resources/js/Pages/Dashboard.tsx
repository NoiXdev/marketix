import ActivityFeed, { FeedItem } from '@/Pages/Dashboard/ActivityFeed';
import ClicksChart from '@/Pages/Dashboard/ClicksChart';
import KpiTile from '@/Pages/Dashboard/KpiTile';
import QuickActions from '@/Pages/Dashboard/QuickActions';
import RankedList from '@/Pages/Dashboard/RankedList';
import ReportDownloadButton from '@/Components/ReportDownloadButton';
import AppLayout from '@/Layouts/AppLayout';
import { flagFromCode } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Gauge, LinkIcon, Plus, Users } from 'lucide-react';

type Kpi = { value: number; deltaPct: number | null };
type ActiveLinks = Kpi & { newInPeriod: number; domains: number; qrCodes: number };
type DayClicks = { date: string; clicks: number; unique: number };
type TopLink = { id: string; slug: string; domain_name: string; clicks: number };
type Country = { country_code: string; country: string; count: number };

interface Props {
  days: number;
  kpis: { clicks: Kpi; uniqueVisitors: Kpi; activeLinks: ActiveLinks; avgPerLink: Kpi };
  clicksByDay: DayClicks[];
  topLinks: TopLink[];
  topCountries: Country[];
  recentActivity: FeedItem[];
}

const RANGES = [7, 30, 90, 365];

export default function Dashboard({ days, kpis, clicksByDay, topLinks, topCountries, recentActivity }: Props) {
  const project = usePage<PageProps>().props.project!;
  const { t } = useTranslation();

  function setDays(d: number) {
    router.get(route('app.project.dashboard', { project: project.id }), { days: d }, { preserveState: true });
  }

  return (
    <AppLayout title={t('common.nav.dashboard')}>
      <div className="px-8 py-8">
        {/* Header */}
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground">{project.name}</h1>
            <p className="mt-1 text-sm text-muted">{t('common.dashboard.overview')} · {days}d</p>
          </div>
          <div className="flex items-center gap-2">
            <div className="inline-flex overflow-hidden rounded-lg border border-line">
              {RANGES.map((d) => (
                <button key={d} onClick={() => setDays(d)} className={`border-r border-line px-3 py-1.5 text-sm font-semibold last:border-r-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${days === d ? 'bg-accent-soft text-accent-soft-foreground' : 'bg-surface text-muted hover:bg-elevated'}`}>
                  {d === 365 ? '1J' : `${d}T`}
                </button>
              ))}
            </div>
            <ReportDownloadButton projectId={project.id} />
            <Link href={route('app.project.links.create', { project: project.id })} className="inline-flex items-center gap-2 rounded-lg bg-accent px-3 py-2 text-sm font-semibold text-accent-foreground hover:bg-accent-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]">
              <Plus className="h-4 w-4" /> {t('common.dashboard.new_link')}
            </Link>
          </div>
        </div>

        {/* KPIs */}
        <div className="grid grid-cols-1 gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
          <KpiTile label={t('common.dashboard.clicks')} value={kpis.clicks.value} deltaPct={kpis.clicks.deltaPct} icon={BarChart3} />
          <KpiTile label={t('common.dashboard.unique_visitors')} value={kpis.uniqueVisitors.value} deltaPct={kpis.uniqueVisitors.deltaPct} icon={Users} />
          <KpiTile label={t('common.dashboard.active_links')} value={kpis.activeLinks.value} deltaPct={kpis.activeLinks.deltaPct} compact={false}
            icon={LinkIcon} subtitle={`${kpis.activeLinks.domains} ${t('common.nav.domains')} · ${kpis.activeLinks.qrCodes} ${t('common.nav.qrcodes')}`} />
          <KpiTile label={t('common.dashboard.avg_per_link')} value={kpis.avgPerLink.value} deltaPct={kpis.avgPerLink.deltaPct} compact={false}
            icon={Gauge} subtitle={t('common.dashboard.bots_excluded')} />
        </div>

        {/* Chart + Top links */}
        <div className="mt-3.5 grid grid-cols-1 gap-3.5 lg:grid-cols-[1.6fr_1fr]">
          <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
            <div className="flex items-center justify-between border-b border-line px-4 py-3.5">
              <h2 className="text-sm font-semibold text-foreground">{t('common.dashboard.clicks_over_time')}</h2>
              <div className="flex gap-3 text-xs text-muted">
                <span className="flex items-center gap-1.5"><i className="h-2.5 w-2.5 rounded-sm bg-accent" /> {t('common.dashboard.clicks')}</span>
                <span className="flex items-center gap-1.5"><i className="h-2.5 w-2.5 rounded-sm bg-[color:color-mix(in_srgb,var(--accent)_40%,var(--surface))]" /> Unique</span>
              </div>
            </div>
            <div className="p-4"><ClicksChart data={clicksByDay} /></div>
          </section>
          <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
            <div className="flex items-center justify-between border-b border-line px-4 py-3.5">
              <h2 className="text-sm font-semibold text-foreground">{t('common.dashboard.top_links')}</h2>
              <Link href={route('app.project.links.index', { project: project.id })} className="text-[12.5px] font-semibold text-accent-soft-foreground hover:underline">{t('common.dashboard.all_links')} →</Link>
            </div>
            <RankedList emptyLabel={t('common.dashboard.no_data')}
              rows={topLinks.map((l, i) => ({ key: l.id, prefix: <span className="text-xs font-bold text-subtle">{i + 1}</span>, label: `${l.domain_name}/${l.slug}`, value: Number(l.clicks) }))} />
          </section>
        </div>

        {/* Geo + Activity */}
        <div className="mt-3.5 grid grid-cols-1 gap-3.5 lg:grid-cols-[1.6fr_1fr]">
          <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
            <div className="flex items-center justify-between border-b border-line px-4 py-3.5">
              <h2 className="text-sm font-semibold text-foreground">{t('common.dashboard.clicks_origin')}</h2>
              <Link href={route('app.project.statistics', { project: project.id })} className="text-[12.5px] font-semibold text-accent-soft-foreground hover:underline">{t('common.dashboard.statistics')} →</Link>
            </div>
            <RankedList emptyLabel={t('common.dashboard.no_data')}
              rows={topCountries.map((c) => ({ key: c.country_code || c.country, prefix: <span className="text-[15px]">{flagFromCode(c.country_code)}</span>, label: c.country ?? c.country_code, value: Number(c.count) }))} />
          </section>
          <section className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
            <div className="flex items-center justify-between border-b border-line px-4 py-3.5">
              <h2 className="text-sm font-semibold text-foreground">{t('common.dashboard.recent_activity')}</h2>
              <Link href={route('app.project.activity.index', { project: project.id })} className="text-[12.5px] font-semibold text-accent-soft-foreground hover:underline">{t('common.dashboard.activity')} →</Link>
            </div>
            <ActivityFeed items={recentActivity} emptyLabel={t('common.dashboard.no_activity')} />
          </section>
        </div>

        {/* Quick actions */}
        <h2 className="mb-2.5 mt-6 text-[11px] font-bold uppercase tracking-wider text-subtle">{t('common.dashboard.quick_actions')}</h2>
        <QuickActions projectId={project.id} />
      </div>
    </AppLayout>
  );
}
