import AppLayout from '@/Layouts/AppLayout';
import RangeTabs from '@/Pages/Analytics/RangeTabs';
import { BackLink } from '@/Components/ui';
import RankedList from '@/Pages/Dashboard/RankedList';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';

type PropValue = { value: string; count: number };
type NumericSummary = { count: number; sum: number; avg: number };
type PropKey = { key: string; values: PropValue[]; numeric: NumericSummary | null };

export default function EventDetail({
  site,
  event,
  days,
  total,
  keys,
}: {
  site: { id: string; name: string; domain: string };
  event: string;
  days: number;
  total: number;
  keys: PropKey[];
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  function setDays(d: number) {
    router.get(
      route('app.project.analytics.events.show', { project: project!.id, site: site.id, name: event }),
      { days: d },
      { preserveState: true },
    );
  }

  return (
    <AppLayout title={t('analytics.events.page_title', { name: event })}>
      <div className="px-8 py-8">
        {/* Header */}
        <div className="mb-6 flex items-center justify-between gap-4">
          <div>
            <BackLink href={route('app.project.analytics.show', { project: project!.id, site: site.id })}>
              {t('analytics.events.back')}
            </BackLink>
            <h1 className="mt-1 font-mono text-2xl font-bold tracking-tight text-foreground">{event}</h1>
            <p className="mt-1 text-sm text-muted">{t('analytics.events.subtitle', { total, name: site.name })}</p>
          </div>
          <RangeTabs days={days} onChange={setDays} />
        </div>

        {keys.length === 0 ? (
          <p className="text-sm text-muted">{t('analytics.events.empty')}</p>
        ) : (
          <div className="grid grid-cols-1 gap-3.5 md:grid-cols-2">
            {keys.map((k) => (
              <section key={k.key} className="rounded-[var(--radius)] border border-line bg-surface shadow-[var(--shadow-sm)]">
                <div className="border-b border-line px-4 py-3">
                  <h2 className="font-mono text-sm font-semibold text-foreground">{k.key}</h2>
                  {k.numeric && (
                    <p className="mt-1.5 text-xs text-muted">
                      {t('analytics.events.numeric.sum')} <span className="font-semibold text-foreground">{k.numeric.sum}</span> ·{' '}
                      {t('analytics.events.numeric.avg')} <span className="font-semibold text-foreground">{k.numeric.avg}</span> ·{' '}
                      {k.numeric.count} {t('analytics.events.numeric.count_suffix')}
                    </p>
                  )}
                </div>
                <RankedList
                  emptyLabel={t('analytics.dashboard.no_data')}
                  rows={k.values.map((v, i) => ({ key: `${v.value}-${i}`, label: v.value, value: v.count }))}
                />
              </section>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
