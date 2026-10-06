import { CountryFlag } from '@/Components/icons/CountryFlag';
import { PlatformIcon } from '@/Components/icons/PlatformIcon';
import { scrubHandlers } from '@/lib/chartScrub';
import { countryName } from '@/lib/displayNames';
import { useTranslation } from '@/lib/i18n';
import { useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import AnalyticsEmptyState from '@/Pages/Analytics/partials/AnalyticsEmptyState';
import BreakdownCard from '@/Pages/Analytics/partials/BreakdownCard';
import { RankRow } from '@/Pages/Dashboard/RankedList';
import { usePoll } from '@inertiajs/react';
import { FileText, Radio, Zap } from 'lucide-react';
import { useState } from 'react';

type CountRow = { value: string; count: number };

type FeedItem = {
  type: 'pageview' | 'event';
  label: string;
  path: string;
  country_code: string | null;
  device: string | null;
  seconds_ago: number;
};

export type RealtimeData = {
  realtime: {
    active_now: number;
    visitors: number;
    page_views: number;
    minutes: { minutes_ago: number; views: number; visitors: number }[];
    pages: CountRow[];
    countries: CountRow[];
    devices: CountRow[];
    channels: CountRow[];
    feed: FeedItem[];
  };
};

const REFRESH_MS = 10_000;

export default function RealtimeTab({ realtime }: RealtimeData) {
  const { t, locale } = useTranslation();
  const { channelLabel } = useRowBuilders();
  const [hovered, setHovered] = useState<number | null>(null);
  usePoll(REFRESH_MS, { only: ['realtime', 'liveVisitors'] });

  const number = (n: number) => n.toLocaleString(locale);
  const relative = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  const ago = (seconds: number) => (seconds < 60 ? relative.format(-seconds, 'second') : relative.format(-Math.floor(seconds / 60), 'minute'));
  const max = Math.max(1, ...realtime.minutes.map((m) => m.views));
  const focus = hovered !== null ? realtime.minutes[hovered] : null;
  const noData = t('analytics.dashboard.realtime.nobody');

  function countRows(items: CountRow[], extra: (row: CountRow) => Partial<RankRow> = () => ({})): RankRow[] {
    return items.map((row, i) => ({ key: `${row.value}-${i}`, label: row.value, value: row.count, ...extra(row) }));
  }

  const minuteLabel = (minutesAgo: number) => (minutesAgo === 0 ? t('analytics.dashboard.realtime.now') : t('analytics.dashboard.realtime.minutes_ago', { count: minutesAgo }));

  const readout = focus
    ? t('analytics.dashboard.realtime.minute_detail', { when: minuteLabel(focus.minutes_ago), views: number(focus.views), visitors: number(focus.visitors) })
    : t('analytics.dashboard.realtime.hover_hint');

  return (
    <>
      <p className="text-muted mb-4 inline-flex items-center gap-2 text-xs">
        <span className="relative flex h-2 w-2">
          <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[color:var(--success-dot)] opacity-60" />
          <span className="relative inline-flex h-2 w-2 rounded-full bg-[color:var(--success-dot)]" />
        </span>
        {t('analytics.dashboard.realtime.auto_refresh', { seconds: REFRESH_MS / 1000 })}
      </p>

      <div className="mb-6 grid grid-cols-2 gap-3.5 md:grid-cols-3">
        <section className="border-line bg-surface col-span-2 rounded-[var(--radius)] border p-5 shadow-[var(--shadow-sm)] md:col-span-1">
          <p className="text-muted text-[12.5px] font-semibold">{t('analytics.dashboard.realtime.active_now')}</p>
          <p className="text-foreground mt-1 text-5xl font-bold tracking-tight">{number(realtime.active_now)}</p>
          <p className="text-subtle mt-1 text-xs">{t('analytics.dashboard.realtime.active_hint')}</p>
        </section>
        <section className="border-line bg-surface rounded-[var(--radius)] border p-5 shadow-[var(--shadow-sm)]">
          <p className="text-muted text-[12.5px] font-semibold">{t('analytics.dashboard.realtime.visitors')}</p>
          <p className="text-foreground mt-1 text-[27px] font-bold tracking-tight">{number(realtime.visitors)}</p>
          <p className="text-subtle mt-1 text-xs">{t('analytics.dashboard.realtime.last_30')}</p>
        </section>
        <section className="border-line bg-surface rounded-[var(--radius)] border p-5 shadow-[var(--shadow-sm)]">
          <p className="text-muted text-[12.5px] font-semibold">{t('analytics.dashboard.kpi.page_views')}</p>
          <p className="text-foreground mt-1 text-[27px] font-bold tracking-tight">{number(realtime.page_views)}</p>
          <p className="text-subtle mt-1 text-xs">{t('analytics.dashboard.realtime.last_30')}</p>
        </section>
      </div>

      <section className="border-line bg-surface mb-6 rounded-[var(--radius)] border p-4 shadow-[var(--shadow-sm)] sm:p-6">
        <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="text-foreground text-sm font-semibold">{t('analytics.dashboard.realtime.per_minute')}</h2>
          <p className="text-muted text-xs">{readout}</p>
        </div>
        <div
          className="border-line flex h-32 touch-pan-y items-end gap-[2px] border-b sm:gap-[3px]"
          onMouseLeave={() => setHovered(null)}
          {...scrubHandlers((slot) => setHovered(Number(slot)))}
        >
          {realtime.minutes.map((minute, i) => (
            <div
              key={minute.minutes_ago}
              data-slot={i}
              className={`flex h-full min-w-0 flex-1 items-end justify-center rounded-t-[4px] pt-2 ${hovered === i ? 'bg-elevated' : ''}`}
            >
              <div
                className="bg-accent w-full max-w-6 rounded-t-[3px] transition-[height] duration-500"
                style={{ height: minute.views > 0 ? `max(2px, ${(minute.views / max) * 100}%)` : '0px' }}
              />
            </div>
          ))}
        </div>
        <div className="text-subtle mt-2 flex justify-between text-[10px]">
          {[29, 20, 10, 0].map((minutesAgo) => (
            <span key={minutesAgo}>{minuteLabel(minutesAgo)}</span>
          ))}
        </div>
      </section>

      <div className="mb-6 grid grid-cols-1 gap-3.5 md:grid-cols-2">
        <BreakdownCard title={t('analytics.dashboard.realtime.active_pages')} emptyLabel={noData} tabs={[{ key: 'pages', label: '', rows: countRows(realtime.pages) }]} />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.channels')}
          emptyLabel={noData}
          tabs={[{ key: 'channels', label: '', rows: countRows(realtime.channels, (row) => ({ label: channelLabel(row.value) })) }]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.countries')}
          emptyLabel={noData}
          tabs={[
            {
              key: 'countries',
              label: '',
              rows: countRows(realtime.countries, (row) => ({ label: countryName(row.value, locale, row.value), prefix: <CountryFlag code={row.value} /> })),
            },
          ]}
        />
        <BreakdownCard
          title={t('analytics.dashboard.breakdown.devices')}
          emptyLabel={noData}
          tabs={[{ key: 'devices', label: '', rows: countRows(realtime.devices, (row) => ({ prefix: <PlatformIcon kind="device" name={row.value} /> })) }]}
        />
      </div>

      <section className="border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
        <div className="border-line border-b px-4 py-3">
          <h2 className="text-foreground text-sm font-semibold">{t('analytics.dashboard.realtime.feed')}</h2>
        </div>
        {realtime.feed.length === 0 ? (
          <AnalyticsEmptyState icon={Radio} title={t('analytics.dashboard.realtime.empty_title')} text={t('analytics.dashboard.realtime.empty_text')} />
        ) : (
          <ul className="divide-line divide-y">
            {realtime.feed.map((item, i) => {
              const Icon = item.type === 'event' ? Zap : FileText;
              return (
                <li key={i} className="flex items-center gap-3 px-4 py-2.5">
                  <span
                    className={`grid h-7 w-7 shrink-0 place-items-center rounded-full ${item.type === 'event' ? 'bg-warning-soft text-warning-foreground' : 'bg-accent-soft text-accent-soft-foreground'}`}
                  >
                    <Icon className="h-3.5 w-3.5" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="text-foreground truncate text-[13.5px] font-semibold">
                      {item.type === 'event' ? t('analytics.dashboard.realtime.event', { name: item.label }) : item.label}
                    </p>
                    {item.type === 'event' && <p className="text-muted truncate font-mono text-[11.5px]">{item.path}</p>}
                  </div>
                  {item.country_code && <CountryFlag code={item.country_code} />}
                  {item.device && <PlatformIcon kind="device" name={item.device} />}
                  <span className="text-subtle w-24 shrink-0 text-right text-xs whitespace-nowrap">{ago(item.seconds_ago)}</span>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    </>
  );
}
