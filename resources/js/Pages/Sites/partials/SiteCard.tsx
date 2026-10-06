import { Favicon } from '@/Components/icons/Favicon';
import { Badge, IconButton } from '@/Components/ui';
import { percentChange } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import Sparkline from '@/Pages/Sites/partials/Sparkline';
import { Site } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, Code2, Pencil, Trash2 } from 'lucide-react';
import { ReactNode, useState } from 'react';

export type SiteStats = {
  visitors: number;
  previous_visitors: number;
  page_views: number;
  engagement_rate: number;
  trend: number[];
  live: number;
  last_seen_at: string;
};

type Props = {
  site: Site & { tracking_mode_label: string };
  stats: SiteStats | null | undefined;
  analyticsUrl: string;
  editUrl: string;
  onDelete: () => void;
};

function Metric({ label, value, children }: { label: string; value: string; children?: ReactNode }) {
  return (
    <div className="min-w-0">
      <p className="text-muted truncate text-xs font-semibold">{label}</p>
      <p className="text-foreground mt-0.5 text-xl font-bold tracking-tight tabular-nums">{value}</p>
      {children}
    </div>
  );
}

function Skeleton() {
  return (
    <div className="animate-pulse px-5 pb-5" aria-hidden>
      <div className="grid grid-cols-3 gap-3">
        {[0, 1, 2].map((i) => (
          <div key={i} className="space-y-2">
            <div className="bg-elevated h-3 w-16 rounded" />
            <div className="bg-elevated h-6 w-14 rounded" />
          </div>
        ))}
      </div>
      <div className="bg-elevated mt-4 h-12 rounded-md" />
    </div>
  );
}

export default function SiteCard({ site, stats, analyticsUrl, editUrl, onDelete }: Props) {
  const { t, locale } = useTranslation();
  const number = (n: number) => n.toLocaleString(locale);
  const relative = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  const [now] = useState(() => Date.now());

  function since(iso: string) {
    const seconds = Math.max(0, Math.round((now - new Date(iso).getTime()) / 1000));
    if (seconds < 60) return relative.format(0, 'second');
    if (seconds < 3600) return relative.format(-Math.floor(seconds / 60), 'minute');
    if (seconds < 86400) return relative.format(-Math.floor(seconds / 3600), 'hour');
    return relative.format(-Math.floor(seconds / 86400), 'day');
  }

  const change = stats ? percentChange(stats.visitors, stats.previous_visitors) : null;

  return (
    <article className="group border-line bg-surface hover:border-line-strong relative flex flex-col rounded-[var(--radius)] border shadow-[var(--shadow-sm)] transition-[border-color,box-shadow] hover:shadow-[var(--shadow)]">
      <div className="flex items-start gap-3 p-5 pb-4">
        <span className="border-line bg-elevated grid h-10 w-10 shrink-0 place-items-center rounded-lg border">
          <Favicon domain={site.domain} className="h-5 w-5 rounded-[3px]" />
        </span>
        <div className="min-w-0 flex-1">
          <Link
            href={analyticsUrl}
            className="text-foreground block truncate font-semibold after:absolute after:inset-0 after:rounded-[var(--radius)] focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-[color:var(--accent-ring)]"
          >
            {site.name}
          </Link>
          <p className="text-muted truncate text-sm">{site.domain}</p>
        </div>
        <div className="relative z-10 -mt-1 -mr-1.5 flex items-center gap-0.5">
          <IconButton icon={Pencil} label={t('common.actions.edit')} href={editUrl} />
          <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={onDelete} />
        </div>
      </div>

      {stats === undefined ? (
        <Skeleton />
      ) : stats === null ? (
        <div className="border-line-strong mx-5 mb-5 flex flex-1 items-center gap-3 rounded-lg border border-dashed px-4 py-3">
          <span className="bg-warning-soft text-warning-foreground grid h-8 w-8 shrink-0 place-items-center rounded-full">
            <Code2 className="h-4 w-4" />
          </span>
          <div className="min-w-0 flex-1">
            <p className="text-foreground text-sm font-semibold">{t('analytics.sites.overview.no_data_title')}</p>
            <p className="text-muted text-xs">{t('analytics.sites.overview.no_data_text')}</p>
          </div>
          <Link
            href={editUrl}
            className="text-accent-soft-foreground relative z-10 shrink-0 rounded text-xs font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
          >
            {t('analytics.sites.overview.setup')}
          </Link>
        </div>
      ) : (
        <div className="px-5 pb-4">
          <div className="grid grid-cols-3 gap-3">
            <Metric label={t('analytics.sites.overview.visitors')} value={number(stats.visitors)}>
              {change !== null && (
                <p
                  title={t('common.dashboard.vs_previous')}
                  className={`text-xs font-bold ${change === 0 ? 'text-muted' : change > 0 ? 'text-success-foreground' : 'text-danger-foreground'}`}
                >
                  {change === 0 ? '± 0 %' : `${change > 0 ? '▲' : '▼'} ${Math.abs(change).toLocaleString(locale)} %`}
                </p>
              )}
            </Metric>
            <Metric label={t('analytics.sites.overview.page_views')} value={number(stats.page_views)} />
            <Metric label={t('analytics.sites.overview.engagement')} value={`${stats.engagement_rate.toLocaleString(locale)} %`} />
          </div>
          <div className="mt-3" title={t('analytics.sites.overview.trend')}>
            <Sparkline values={stats.trend} />
          </div>
        </div>
      )}

      <div className="border-line mt-auto flex items-center justify-between gap-3 border-t px-5 py-3 text-xs">
        <div className="flex min-w-0 items-center gap-2">
          <Badge variant="neutral">{site.tracking_mode_label}</Badge>
          {stats && stats.live > 0 ? (
            <span className="text-foreground inline-flex items-center gap-1.5 font-semibold">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[color:var(--success-dot)] opacity-60" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-[color:var(--success-dot)]" />
              </span>
              {t('analytics.dashboard.live.count', { count: number(stats.live) })}
            </span>
          ) : (
            stats && <span className="text-muted truncate">{t('analytics.sites.overview.last_seen', { time: since(stats.last_seen_at) })}</span>
          )}
        </div>
        <span className="text-accent-soft-foreground inline-flex shrink-0 items-center gap-1 font-semibold">
          {t('analytics.sites.overview.open')}
          <ArrowRight className="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
        </span>
      </div>
    </article>
  );
}
