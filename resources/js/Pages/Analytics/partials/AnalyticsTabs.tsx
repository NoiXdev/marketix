import { useTranslation } from '@/lib/i18n';
import { useAnalytics } from '@/Pages/Analytics/AnalyticsContext';
import { TabKey, TABS } from '@/Pages/Analytics/types';
import { Link } from '@inertiajs/react';
import { Banknote, Globe2, LayoutDashboard, LucideIcon, Megaphone, MousePointerClick, Radio, Target } from 'lucide-react';
import { useEffect, useRef } from 'react';

const ICONS: Record<TabKey, LucideIcon> = {
  overview: LayoutDashboard,
  realtime: Radio,
  acquisition: Megaphone,
  behavior: MousePointerClick,
  audience: Globe2,
  conversions: Target,
  revenue: Banknote,
};

export default function AnalyticsTabs() {
  const { t } = useTranslation();
  const { tab: active, href } = useAnalytics();
  const navRef = useRef<HTMLElement>(null);

  // On narrow screens the strip scrolls sideways: keep the active tab centred in view.
  useEffect(() => {
    const nav = navRef.current;
    const current = nav?.querySelector<HTMLElement>('[aria-current="page"]');
    if (!nav || !current) return;
    const offset = current.getBoundingClientRect().left - nav.getBoundingClientRect().left + nav.scrollLeft;
    nav.scrollLeft = offset - (nav.clientWidth - current.offsetWidth) / 2;
  }, [active]);

  return (
    <div className="mb-6">
      <nav
        ref={navRef}
        aria-label={t('analytics.dashboard.tabs.label')}
        className="-mx-4 overflow-x-auto overflow-y-hidden px-4 [scrollbar-width:none] sm:mx-0 sm:px-0 [&::-webkit-scrollbar]:hidden"
      >
        <ul className="flex w-max min-w-full gap-1 shadow-[inset_0_-1px_0_var(--line)]">
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
