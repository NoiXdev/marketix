import { LinkButton } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import AnalyticsEmptyState from '@/Pages/Analytics/partials/AnalyticsEmptyState';
import { SiteInfo } from '@/Pages/Analytics/types';
import { router, usePoll } from '@inertiajs/react';
import { Code2 } from 'lucide-react';

const CHECK_INTERVAL_MS = 5000;

export default function TrackingSetup() {
  const { t } = useTranslation();
  const { projectId, site } = useAnalytics();

  usePoll(CHECK_INTERVAL_MS, {
    only: ['site'],
    onSuccess: (page) => {
      if ((page.props.site as SiteInfo).has_data) router.reload();
    },
  });

  return (
    <div className="mb-6">
      <AnalyticsEmptyState
        framed
        icon={Code2}
        title={t('analytics.dashboard.setup.title')}
        text={t('analytics.dashboard.setup.text', { domain: site.domain })}
        code={site.snippet}
        codeLanguage="html"
        action={
          <div className="flex flex-col items-center gap-4">
            <p className="text-muted inline-flex items-center gap-2 text-xs">
              <span className="relative flex h-2 w-2">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[color:var(--warning-dot)] opacity-60" />
                <span className="relative inline-flex h-2 w-2 rounded-full bg-[color:var(--warning-dot)]" />
              </span>
              {t('analytics.dashboard.setup.waiting')}
            </p>
            <LinkButton variant="secondary" size="sm" href={route('app.project.sites.edit', { project: projectId, site: site.id })}>
              {t('analytics.dashboard.interactions.open_settings')}
            </LinkButton>
          </div>
        }
      />
    </div>
  );
}
