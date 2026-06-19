import { Link, usePage } from '@inertiajs/react';
import { Activity, ArrowLeft, FolderKanban, Link2, Mail, ScrollText, Users } from 'lucide-react';
import UserMenu from './UserMenu';
import { PageProps } from '@/types';

const navItems = [
  { label: 'Users', icon: Users, routeName: 'app.admin.users.index' },
  { label: 'Projects', icon: FolderKanban, routeName: 'app.admin.projects.index' },
  { label: 'Mailer', icon: Mail, routeName: 'app.admin.mailer.edit' },
  { label: 'Activity', icon: ScrollText, routeName: 'app.admin.activity.index' },
];

export default function AdminSidebar() {
  const { url } = usePage();
  const { projects, version } = usePage<PageProps>().props;
  const backProject = projects?.[0];

  return (
    <aside className="flex h-screen w-60 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div className="flex h-14 items-center gap-2 border-b border-slate-200 px-4 dark:border-slate-800">
        <Link2 className="h-5 w-5 text-indigo-600" />
        <span className="text-sm font-semibold text-slate-900 dark:text-white">Marketix Admin</span>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-0.5">
          {navItems.map(({ label, icon: Icon, routeName }) => {
            const href = route(routeName);
            const isActive = url.startsWith('/' + href.replace(/^https?:\/\/[^/]+\//, ''));
            return (
              <li key={label}>
                <Link
                  href={href}
                  className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                      : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
                  }`}
                >
                  <Icon className="h-4 w-4 shrink-0" />
                  {label}
                </Link>
              </li>
            );
          })}

          {/* Horizon is a separate Blade-rendered dashboard, so it needs a full-page anchor, not an Inertia Link. */}
          <li>
            <a
              href="/horizon"
              className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                url.startsWith('/horizon')
                  ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-300'
                  : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white'
              }`}
            >
              <Activity className="h-4 w-4 shrink-0" />
              Horizon
            </a>
          </li>
        </ul>
      </nav>

      <div className="border-t border-slate-200 p-3 dark:border-slate-800">
        {backProject && (
          <Link
            href={route('app.project.dashboard', { project: backProject.id })}
            className="mb-1 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4 shrink-0" />
            Back to app
          </Link>
        )}
        <UserMenu />
        <p className="mt-2 px-3 text-center text-xs text-slate-400 dark:text-slate-600">v{version}</p>
      </div>
    </aside>
  );
}
