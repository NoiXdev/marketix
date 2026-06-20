import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Globe, History, LayoutDashboard, LinkIcon, QrCode, Users, Zap } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';
import Brand from './Brand';
import LocaleSwitcher from './LocaleSwitcher';
import ProjectSwitcher from './ProjectSwitcher';
import ThemeToggle from './ThemeToggle';
import UserMenu from './UserMenu';

const navItems = [
  { key: 'dashboard',  icon: LayoutDashboard, routeName: 'app.project.dashboard' },
  { key: 'links',      icon: LinkIcon,        routeName: 'app.project.links.index' },
  { key: 'domains',    icon: Globe,           routeName: 'app.project.domains.index' },
  { key: 'qrcodes',    icon: QrCode,          routeName: 'app.project.qrcodes.index' },
  { key: 'pixels',     icon: Zap,             routeName: 'app.project.pixels.index' },
  { key: 'statistics', icon: BarChart3,       routeName: 'app.project.statistics' },
  { key: 'activity',   icon: History,         routeName: 'app.project.activity.index' },
];

export default function Sidebar() {
  const { url } = usePage();
  const currentProject = usePage().props.project;
  const { currentProjectRole, auth, version } = usePage<import('@/types').PageProps>().props;
  const isProjectAdmin = auth.user.super_admin || currentProjectRole === 'admin';
  const { t } = useTranslation();

  return (
    <aside className="flex h-screen w-60 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      {/* Logo */}
      <div className="flex h-14 items-center border-b border-slate-200 px-4 dark:border-slate-800">
        <Brand />
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-0.5">
          {[
            ...navItems,
            ...(isProjectAdmin ? [{ key: 'team', icon: Users, routeName: 'app.project.team.index' }] : []),
          ].map(({ key, icon: Icon, routeName }) => {
            const href = routeName ? route(routeName, { project: currentProject?.id }) : '#';
            const isActive = routeName ? url.startsWith('/' + href.replace(/^https?:\/\/[^/]+\//, '')) : false;

            return (
              <li key={key}>
                <Link
                  href={href}
                  className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
                  }`}
                >
                  <Icon className="h-4 w-4 shrink-0" />
                  {t(`common.nav.${key}`)}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      {/* Bottom section */}
      <div className="border-t border-slate-200 p-3 dark:border-slate-800">
        <ThemeToggle />
        <div className="mt-1">
          <ProjectSwitcher />
        </div>
        <div className="mt-1">
          <LocaleSwitcher />
        </div>
        <div className="mt-1">
          <UserMenu />
        </div>
        <p className="mt-2 px-3 text-center text-xs text-slate-400 dark:text-slate-600">v{version}</p>
      </div>
    </aside>
  );
}
