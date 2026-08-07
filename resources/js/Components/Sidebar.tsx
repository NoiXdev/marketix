import ProjectSwitcher from '@/Components/ProjectSwitcher';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
  BarChart3, ChevronLeft, FileBarChart, Globe, History, LayoutDashboard, LineChart, LinkIcon, QrCode, Users, Zap,
} from 'lucide-react';
import { useState } from 'react';
import Brand from './Brand';

type NavItem = { key: string; icon: typeof LinkIcon; routeName: string; countKey?: string };
type NavGroup = { labelKey: string; items: NavItem[] };

const groups: NavGroup[] = [
  { labelKey: 'overview', items: [{ key: 'dashboard', icon: LayoutDashboard, routeName: 'app.project.dashboard' }] },
  {
    labelKey: 'links_codes',
    items: [
      { key: 'links', icon: LinkIcon, routeName: 'app.project.links.index', countKey: 'links' },
      { key: 'domains', icon: Globe, routeName: 'app.project.domains.index' },
      { key: 'qrcodes', icon: QrCode, routeName: 'app.project.qrcodes.index' },
      { key: 'pixels', icon: Zap, routeName: 'app.project.pixels.index' },
    ],
  },
  {
    labelKey: 'insights',
    items: [
      { key: 'statistics', icon: BarChart3, routeName: 'app.project.statistics' },
      { key: 'sites', icon: LineChart, routeName: 'app.project.sites.index' },
      { key: 'reports', icon: FileBarChart, routeName: 'app.project.reports.index' },
    ],
  },
  { labelKey: 'management', items: [{ key: 'activity', icon: History, routeName: 'app.project.activity.index' }] },
];

export default function Sidebar() {
  const { project, navCounts, currentProjectRole, auth, version } = usePage<PageProps>().props;
  const isProjectAdmin = auth.user.super_admin || currentProjectRole === 'admin';
  const { t } = useTranslation();

  const [collapsed, setCollapsed] = useState<boolean>(() => {
    try {
      return localStorage.getItem('sidebar-collapsed') === 'true';
    } catch {
      return false;
    }
  });
  function toggle() {
    setCollapsed((c) => {
      const next = !c;
      try {
        localStorage.setItem('sidebar-collapsed', String(next));
      } catch {
        /* ignore */
      }
      return next;
    });
  }

  const renderGroups: NavGroup[] = isProjectAdmin
    ? groups.map((g) =>
        g.labelKey === 'management'
          ? { ...g, items: [...g.items, { key: 'team', icon: Users, routeName: 'app.project.team.index' }] }
          : g,
      )
    : groups;

  return (
    <aside
      className={`flex h-screen shrink-0 flex-col border-r border-line bg-elevated transition-[width] duration-200 motion-reduce:transition-none ${
        collapsed ? 'w-[72px]' : 'w-64'
      }`}
    >
      {/* Header */}
      <div className="flex h-14 items-center border-b border-line px-3">
        {!collapsed && (
          <div className="flex-1 overflow-hidden">
            <Brand />
          </div>
        )}
        <button
          onClick={toggle}
          aria-label="Sidebar ein-/ausklappen"
          className={`inline-flex h-7 w-7 items-center justify-center rounded-md border border-line bg-surface text-muted hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
            collapsed ? 'mx-auto' : 'ml-auto'
          }`}
        >
          <ChevronLeft className={`h-[15px] w-[15px] transition-transform duration-200 motion-reduce:transition-none ${collapsed ? 'rotate-180' : ''}`} />
        </button>
      </div>

      {/* Project switcher */}
      <div className="border-b border-line p-2">
        <ProjectSwitcher collapsed={collapsed} />
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-2 py-3">
        {renderGroups.map((group) => (
          <div key={group.labelKey} className={collapsed ? 'mt-2 border-t border-line pt-2 first:mt-0 first:border-t-0 first:pt-0' : 'mt-4 first:mt-0'}>
            {!collapsed && (
              <p className="px-2.5 pb-1 pt-1.5 text-[10.5px] font-bold uppercase tracking-wider text-subtle">
                {t(`common.nav.groups.${group.labelKey}`)}
              </p>
            )}
            <ul className="space-y-0.5">
              {group.items.map(({ key, icon: Icon, routeName, countKey }) => {
                const href = route(routeName, { project: project?.id });
                const isActive = route().current(routeName);
                const count = countKey && navCounts ? navCounts[countKey] : undefined;
                return (
                  <li key={key} className="group relative">
                    <Link
                      href={href}
                      aria-label={t(`common.nav.${key}`)}
                      className={`flex items-center gap-3 rounded-md px-2.5 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                        collapsed ? 'justify-center' : ''
                      } ${isActive ? 'bg-accent-soft text-accent-soft-foreground' : 'text-muted hover:bg-surface hover:text-foreground'}`}
                    >
                      <Icon className="h-[18px] w-[18px] shrink-0" />
                      {!collapsed && <span className="flex-1 truncate">{t(`common.nav.${key}`)}</span>}
                      {!collapsed && count ? (
                        <span
                          className={`rounded-full px-1.5 text-[11px] font-bold ${
                            isActive ? 'text-accent-soft-foreground' : 'border border-line bg-elevated text-muted'
                          }`}
                        >
                          {count}
                        </span>
                      ) : null}
                    </Link>
                    {collapsed && (
                      <span className="pointer-events-none absolute left-[calc(100%+10px)] top-1/2 z-40 hidden -translate-y-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs font-semibold text-canvas shadow-[var(--shadow)] group-hover:block group-focus-within:block">
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

      {/* Footer: version only */}
      <div className="border-t border-line px-3 py-2">
        <p className={`text-[11px] text-subtle ${collapsed ? 'text-center' : ''}`}>v{version}</p>
      </div>
    </aside>
  );
}
