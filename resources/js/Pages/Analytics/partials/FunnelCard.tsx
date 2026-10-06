import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import { FunnelReport } from '@/Pages/Analytics/types';
import { Link } from '@inertiajs/react';
import { ArrowDown, FileText, Pencil, Zap } from 'lucide-react';

export default function FunnelCard({ funnel }: { funnel: FunnelReport }) {
  const { t, locale } = useTranslation();
  const { projectId, site } = useAnalytics();
  const number = (n: number) => n.toLocaleString(locale);
  const percent = (n: number) => `${n.toLocaleString(locale)} %`;

  return (
    <section className="border-line bg-surface rounded-[var(--radius)] border shadow-[var(--shadow-sm)]">
      <header className="border-line flex items-start justify-between gap-3 border-b px-4 py-4 sm:px-5">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <h3 className="text-foreground truncate text-sm font-semibold">{funnel.name}</h3>
            <Link
              href={route('app.project.analytics.funnels.edit', { project: projectId, site: site.id, funnel: funnel.id })}
              title={t('analytics.dashboard.funnels.edit')}
              aria-label={t('analytics.dashboard.funnels.edit')}
              className="text-subtle hover:bg-elevated hover:text-foreground rounded-md p-1"
            >
              <Pencil className="h-3.5 w-3.5" />
            </Link>
          </div>
          <p className="text-muted mt-0.5 text-xs">{t('analytics.dashboard.funnels.summary', { entered: number(funnel.entered), completed: number(funnel.completed) })}</p>
        </div>
        <div className="shrink-0 text-right">
          <p className="text-foreground text-2xl font-bold">{percent(funnel.conversion_rate)}</p>
          <p className="text-muted text-xs">{t('analytics.dashboard.funnels.conversion_rate')}</p>
        </div>
      </header>

      <ol className="px-4 py-4 sm:px-5">
        {funnel.steps.map((step, i) => {
          const Icon = step.type === 'event' ? Zap : FileText;
          return (
            <li key={i}>
              {i > 0 && (
                <p className="text-muted my-2 flex flex-wrap items-center gap-x-1.5 pl-9 text-xs">
                  <ArrowDown className="h-3.5 w-3.5" />
                  <span className="whitespace-nowrap">{t('analytics.dashboard.funnels.continued', { rate: percent(step.step_rate ?? 0) })}</span>
                  {step.drop_off > 0 && <span className="text-subtle whitespace-nowrap">· {t('analytics.dashboard.funnels.dropped', { count: number(step.drop_off) })}</span>}
                </p>
              )}
              <div className="flex items-start gap-3">
                <span className="bg-accent-soft text-accent-soft-foreground grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs font-bold">{i + 1}</span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-baseline justify-between gap-3">
                    <p className="text-foreground truncate text-[13.5px] font-semibold">{step.label || step.value}</p>
                    <p className="text-foreground text-sm font-bold whitespace-nowrap tabular-nums">
                      {number(step.sessions)}
                      <span className="text-muted ml-1.5 text-xs font-medium">{percent(step.rate)}</span>
                    </p>
                  </div>
                  <p className="text-muted mt-0.5 flex items-center gap-1 truncate text-xs">
                    <Icon className="h-3 w-3 shrink-0" />
                    {t(`app.goal_type_${step.type}`)}
                    <span className="truncate font-mono">· {step.value}</span>
                  </p>
                  <div className="bg-elevated mt-2 h-2.5 overflow-hidden rounded-full">
                    <div className="bg-accent h-full rounded-full transition-[width] duration-300" style={{ width: `${Math.max(step.rate, step.sessions > 0 ? 1 : 0)}%` }} />
                  </div>
                </div>
              </div>
            </li>
          );
        })}
      </ol>
    </section>
  );
}
