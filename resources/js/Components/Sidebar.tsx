import SidebarBottom from '@/Components/SidebarBottom';
import { useTranslation } from '@/lib/i18n';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, Globe, History, LayoutDashboard, LineChart, LinkIcon, QrCode, Users, Zap } from 'lucide-react';
import Brand from './Brand';
import LocaleSwitcher from './LocaleSwitcher';
import ProjectSwitcher from './ProjectSwitcher';

const navItems = [
  { key: 'dashboard', icon: LayoutDashboard, routeName: 'app.project.dashboard' },
  { key: 'links', icon: LinkIcon, routeName: 'app.project.links.index' },
  { key: 'domains', icon: Globe, routeName: 'app.project.domains.index' },
  { key: 'qrcodes', icon: QrCode, routeName: 'app.project.qrcodes.index' },
  { key: 'pixels', icon: Zap, routeName: 'app.project.pixels.index' },
  { key: 'statistics', icon: BarChart3, routeName: 'app.project.statistics' },
  { key: 'sites', icon: LineChart, routeName: 'app.project.sites.index' },
  { key: 'activity', icon: History, routeName: 'app.project.activity.index' },
];

export default function Sidebar() {
  const { url } = usePage();
  const currentProject = usePage().props.project;
  const { currentProjectRole, auth } = usePage<import('@/types').PageProps>().props;
  const isProjectAdmin = auth.user.super_admin || currentProjectRole === 'admin';
  const { t } = useTranslation();

  return (
    <aside className="flex h-screen w-60 flex-col border-r border-line bg-elevated">
      {/* Logo */}
      <div className="flex h-14 items-center border-b border-line px-4">
        <Brand />
      </div>

      <div className="mt-1 border-b border-line px-2">
        <ProjectSwitcher />
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-0.5">
          {[...navItems, ...(isProjectAdmin ? [{ key: 'team', icon: Users, routeName: 'app.project.team.index' }] : [])].map(({ key, icon: Icon, routeName }) => {
            const href = routeName ? route(routeName, { project: currentProject?.id }) : '#';
            const isActive = routeName ? url.startsWith('/' + href.replace(/^https?:\/\/[^/]+\//, '')) : false;

            return (
              <li key={key}>
                <Link
                  href={href}
                  className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-accent-soft text-accent-soft-foreground'
                      : 'text-muted hover:bg-surface hover:text-foreground'
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
      <div className="border-t border-line p-3">
        <div className="mt-1">
          <LocaleSwitcher />
        </div>
        <SidebarBottom docsUrl="https://docs.noix.dev/marketix/user/getting-started/" />
      </div>
    </aside>
  );
}
