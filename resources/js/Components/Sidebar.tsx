import ProjectSwitcher from '@/Components/ProjectSwitcher';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, FileBarChart, Globe, History, LayoutDashboard, LineChart, LinkIcon, QrCode, ShieldCheck, Users, Zap } from 'lucide-react';
import Brand from './Brand';
import SidebarShell, { MobileNavProps } from './SidebarShell';

type NavItem = { key: string; icon: typeof LinkIcon; routeName: string; countKey?: string; activeRoutes?: string[] };
type NavGroup = { labelKey: string; items: NavItem[] };

const groups: NavGroup[] = [
  { labelKey: 'overview', items: [{ key: 'dashboard', icon: LayoutDashboard, routeName: 'app.project.dashboard' }] },
  {
    labelKey: 'links_codes',
    items: [
      { key: 'links', icon: LinkIcon, routeName: 'app.project.links.index', countKey: 'links' },
      { key: 'domains', icon: Globe, routeName: 'app.project.domains.index' },
      { key: 'qrcodes', icon: QrCode, routeName: 'app.project.qrcodes.index', activeRoutes: ['app.project.qr-templates.*'] },
      { key: 'pixels', icon: Zap, routeName: 'app.project.pixels.index' },
    ],
  },
  {
    labelKey: 'analytics',
    items: [{ key: 'sites', icon: LineChart, routeName: 'app.project.sites.index', activeRoutes: ['app.project.analytics.*'] }],
  },
  {
    labelKey: 'insights',
    items: [
      { key: 'statistics', icon: BarChart3, routeName: 'app.project.statistics' },
      { key: 'reports', icon: FileBarChart, routeName: 'app.project.reports.index' },
    ],
  },
  { labelKey: 'management', items: [{ key: 'activity', icon: History, routeName: 'app.project.activity.index' }] },
  { labelKey: 'docs_help', items: [{ key: 'data_privacy', icon: ShieldCheck, routeName: 'app.project.docs.privacy' }] },
];

function isNavItemActive({ routeName, activeRoutes = [] }: NavItem): boolean {
  return [routeName.replace(/\.index$/, '.*'), ...activeRoutes].some((pattern) => route().current(pattern));
}

export default function Sidebar(mobileNav: MobileNavProps) {
  const { project, navCounts, currentProjectRole, auth } = usePage<PageProps>().props;
  const isProjectAdmin = auth.user.super_admin || currentProjectRole === 'admin';
  const { t } = useTranslation();

  const renderGroups: NavGroup[] = isProjectAdmin
    ? groups.map((g) => (g.labelKey === 'management' ? { ...g, items: [...g.items, { key: 'team', icon: Users, routeName: 'app.project.team.index' }] } : g))
    : groups;

  return (
    <SidebarShell brand={<Brand />} {...mobileNav}>
      {(collapsed) => (
        <>
          {/* Project switcher */}
          <div className="border-line border-b p-2">
            <ProjectSwitcher collapsed={collapsed} />
          </div>

          {/* Navigation */}
          <nav className="flex-1 overflow-y-auto px-2 py-3">
            {renderGroups.map((group) => (
              <div key={group.labelKey} className={collapsed ? 'border-line mt-2 border-t pt-2 first:mt-0 first:border-t-0 first:pt-0' : 'mt-4 first:mt-0'}>
                {!collapsed && <p className="text-subtle px-2.5 pt-1.5 pb-1 text-[10.5px] font-bold tracking-wider uppercase">{t(`common.nav.groups.${group.labelKey}`)}</p>}
                <ul className="space-y-0.5">
                  {group.items.map((item) => {
                    const { key, icon: Icon, routeName, countKey } = item;
                    const href = route(routeName, { project: project?.id });
                    const isActive = isNavItemActive(item);
                    const count = countKey && navCounts ? navCounts[countKey] : undefined;
                    return (
                      <li key={key} className="group relative">
                        <Link
                          href={href}
                          aria-label={t(`common.nav.${key}`)}
                          className={`flex items-center gap-3 rounded-md px-2.5 py-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none ${
                            collapsed ? 'justify-center' : ''
                          } ${isActive ? 'bg-accent-soft text-accent-soft-foreground' : 'text-muted hover:bg-surface hover:text-foreground'}`}
                        >
                          <Icon className="h-[18px] w-[18px] shrink-0" />
                          {!collapsed && <span className="flex-1 truncate">{t(`common.nav.${key}`)}</span>}
                          {!collapsed && count ? (
                            <span className={`rounded-full px-1.5 text-[11px] font-bold ${isActive ? 'text-accent-soft-foreground' : 'border-line bg-elevated text-muted border'}`}>
                              {count}
                            </span>
                          ) : null}
                        </Link>
                        {collapsed && (
                          <span className="bg-foreground text-canvas pointer-events-none absolute top-1/2 left-[calc(100%+10px)] z-40 hidden -translate-y-1/2 rounded-md px-2 py-1 text-xs font-semibold whitespace-nowrap shadow-[var(--shadow)] group-focus-within:block group-hover:block">
                            {t(`common.nav.${key}`)}
                          </span>
                        )}
                      </li>
                    );
                  })}
                </ul>
              </div>
            ))}
          </nav>
        </>
      )}
    </SidebarShell>
  );
}
