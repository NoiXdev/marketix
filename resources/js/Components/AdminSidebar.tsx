import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { Activity, ArrowLeft, BookOpen, FolderKanban, HardDrive, Mail, Palette, ScrollText, Shield, LucideLogOut, Users } from 'lucide-react';
import Brand from './Brand';

function initials(name: string): string {
  return name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

const navItems = [
  { label: 'Users', icon: Users, routeName: 'app.admin.users.index' },
  { label: 'Projects', icon: FolderKanban, routeName: 'app.admin.projects.index' },
  { label: 'Mailer', icon: Mail, routeName: 'app.admin.mailer.edit' },
  { label: 'Branding', icon: Palette, routeName: 'app.admin.branding.edit' },
  { label: 'Storage', icon: HardDrive, routeName: 'app.admin.storage.edit' },
  { label: 'Activity', icon: ScrollText, routeName: 'app.admin.activity.index' },
];

export default function AdminSidebar() {
  const { url } = usePage();
  const { auth, version } = usePage<import('@/types').PageProps>().props;

  return (
    <aside className="flex h-screen w-60 flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
      <div className="flex h-14 items-center border-b border-slate-200 px-4 dark:border-slate-800">
        <Brand suffix="Admin" />
      </div>

      <div className="mt-1 border-b border-slate-200 px-2 dark:border-slate-800">
        <Link
          href={route('app.projects.choose')}
          className="mb-1 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
        >
          <ArrowLeft className="h-4 w-4 shrink-0" />
          Back to app
        </Link>
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
        <div className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-900 dark:text-white">
          <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
            {initials(auth.user.name)}
          </span>
          <div>
            <span className="flex-1 truncate text-left text-sm font-medium">{auth.user.name}</span>
            <br />
            <span className="flex-1 truncate text-left text-xs font-medium">{auth.user.email}</span>
          </div>
        </div>

        <div className="flex justify-between px-3">
          <ThemeToggle />
          <a
            className="cursor-pointer rounded-lg border border-slate-200 p-1 hover:bg-slate-100 dark:border-slate-800 dark:hover:bg-slate-800"
            href="https://docs.noix.dev/marketix/admin/introduction/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="Documentation"
            title="Documentation"
          >
            <BookOpen className="h-5 w-5 text-slate-600 dark:text-slate-400" />
          </a>
          {auth.user.super_admin && (
            <Link
              className="cursor-pointer rounded-lg border border-slate-200 p-1 hover:bg-slate-100 dark:border-slate-800 dark:hover:bg-slate-800"
              href={route('app.admin.users.index')}
            >
              <Shield className="h-5 w-5 text-slate-600 dark:text-slate-400" />
            </Link>
          )}
          <Link
            className="cursor-pointer rounded-lg border border-slate-200 p-1 hover:bg-slate-100 dark:border-slate-800 dark:hover:bg-slate-800"
            href={route('app.auth.logout')}
            method="post"
            as="button"
          >
            <LucideLogOut className="h-5 w-5 text-slate-600 dark:text-slate-400" />
          </Link>
        </div>

        <p className="mt-2 px-3 text-center text-xs text-slate-500 dark:text-slate-400">v{version}</p>
      </div>
    </aside>
  );
}
