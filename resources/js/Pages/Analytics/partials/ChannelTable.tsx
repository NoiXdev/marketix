import { formatDuration } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics, useRowBuilders } from '@/Pages/Analytics/AnalyticsContext';
import { ChannelRow } from '@/Pages/Analytics/types';

export default function ChannelTable({ title, channels, emptyLabel }: { title: string; channels: ChannelRow[]; emptyLabel: string }) {
  const { t, locale } = useTranslation();
  const { addFilter } = useAnalytics();
  const { channelLabel } = useRowBuilders();

  const total = channels.reduce((sum, row) => sum + Number(row.count), 0);
  const max = Math.max(1, ...channels.map((row) => Number(row.count)));
  const percent = (value: number) => `${value.toLocaleString(locale, { maximumFractionDigits: 1 })} %`;

  return (
    <section className="border-line bg-surface flex flex-col rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
      <div className="border-line border-b px-4 py-3">
        <h2 className="text-foreground text-sm font-semibold">{title}</h2>
      </div>

      {channels.length === 0 ? (
        <p className="text-muted flex-1 py-8 text-center text-sm">{emptyLabel}</p>
      ) : (
        <table className="w-full text-sm">
          <thead>
            <tr className="text-muted text-xs">
              <th className="px-4 py-2 text-left font-semibold">{t('analytics.dashboard.channel_table.channel')}</th>
              <th className="px-3 py-2 text-right font-semibold">{t('analytics.dashboard.channel_table.sessions')}</th>
              <th className="px-3 py-2 text-right font-semibold">{t('analytics.dashboard.channel_table.engagement')}</th>
              <th className="hidden px-4 py-2 text-right font-semibold sm:table-cell">{t('analytics.dashboard.channel_table.avg_duration')}</th>
            </tr>
          </thead>
          <tbody>
            {channels.map((row) => {
              const count = Number(row.count);
              return (
                <tr key={row.channel} className="border-line hover:bg-elevated border-t">
                  <td className="px-4 py-2">
                    <button
                      type="button"
                      onClick={() => addFilter('channel', row.channel)}
                      title={t('analytics.dashboard.filters.apply')}
                      className="text-foreground rounded text-left font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
                    >
                      {channelLabel(row.channel)}
                    </button>
                    <div className="bg-elevated mt-1.5 h-1 rounded-full">
                      <div className="bg-accent h-1 rounded-full" style={{ width: `${(count / max) * 100}%` }} />
                    </div>
                  </td>
                  <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums">
                    <span className="text-foreground font-semibold">{count.toLocaleString(locale)}</span>
                    <span className="text-muted ml-1.5 text-xs">{percent(total > 0 ? (count / total) * 100 : 0)}</span>
                  </td>
                  <td className="px-3 py-2 text-right">
                    {row.engagement_rate === null ? (
                      <span className="text-muted">—</span>
                    ) : (
                      <span className="inline-flex items-center gap-2">
                        <span aria-hidden className="bg-elevated hidden h-1.5 w-10 overflow-hidden rounded-full sm:block">
                          <span className="block h-full rounded-full bg-[color:var(--chart-engagement)]" style={{ width: `${row.engagement_rate}%` }} />
                        </span>
                        <span className="text-foreground font-semibold tabular-nums">{percent(row.engagement_rate)}</span>
                      </span>
                    )}
                  </td>
                  <td className="text-muted hidden px-4 py-2 text-right tabular-nums sm:table-cell">{formatDuration(row.avg_duration)}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      )}

      <p className="border-line text-subtle mt-auto border-t px-4 py-3 text-xs">{t('analytics.dashboard.channel_table.hint')}</p>
    </section>
  );
}
