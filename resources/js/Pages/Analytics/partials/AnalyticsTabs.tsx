import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import { TabKey, TABS } from '@/Pages/Analytics/types';
import { Link } from '@inertiajs/react';
import { Globe2, LayoutDashboard, LucideIcon, Megaphone, MousePointerClick, Target } from 'lucide-react';

const ICONS: Record<TabKey, LucideIcon> = {
  overview: LayoutDashboard,
  acquisition: Megaphone,
  behavior: MousePointerClick,
  audience: Globe2,
  conversions: Target,
};

export default function AnalyticsTabs() {
  const { t } = useTranslation();
  const { tab: active, href } = useAnalytics();

  return (
    <div className="mb-6">
      <nav aria-label={t('analytics.dashboard.tabs.label')} className="overflow-x-auto overflow-y-hidden">
        <ul className="flex min-w-full gap-1 shadow-[inset_0_-1px_0_var(--line)]">
          {TABS.map((tab) => {
            const Icon = ICONS[tab];
            const current = tab === active;
            return (
              <li key={tab}>
                <Link
                  href={href({ tab })}
                  preserveScroll
                  aria-current={current ? 'page' : undefined}
                  className={`inline-flex items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none focus-visible:ring-inset ${
                    current ? 'border-accent text-foreground' : 'text-muted hover:border-line-strong hover:text-foreground border-transparent'
                  }`}
                >
                  <Icon className={`h-4 w-4 ${current ? 'text-accent-soft-foreground' : 'text-subtle'}`} />
                  {t(`analytics.dashboard.tabs.${tab}.label`)}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>
      <p className="text-muted mt-3 text-sm">{t(`analytics.dashboard.tabs.${active}.description`)}</p>
    </div>
  );
}
