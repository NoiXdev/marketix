import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, LucideLogOut, Shield } from 'lucide-react';

export default function SidebarBottom({ docsUrl }: { docsUrl: string }) {
  const { auth, version } = usePage().props;

  function initials(name: string): string {
    return name
      .split(' ')
      .map((n) => n[0])
      .slice(0, 2)
      .join('')
      .toUpperCase();
  }

  return (
    <div>
      {/* User Information */}
      <div>
        <div className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 dark:text-slate-300">
          <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
            {initials(auth.user.name)}
          </span>
          <div>
            <span className="flex-1 truncate text-left text-sm font-medium">{auth.user.name}</span>
            <br />
            <span className="flex-1 truncate text-left text-xs font-medium">{auth.user.email}</span>
          </div>
        </div>
      </div>

      {/* Actions */}

      <div className="flex justify-between px-3">
        <ThemeToggle />
        <a
          className="cursor-pointer rounded-lg border p-1 dark:bg-indigo-900 dark:hover:bg-indigo-700"
          href={docsUrl}
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Documentation"
          title="Documentation"
        >
          <BookOpen className="h-5 w-5 dark:text-slate-400" />
        </a>
        {auth.user.super_admin && (
          <Link className="cursor-pointer rounded-lg border p-1 dark:bg-indigo-900 dark:hover:bg-indigo-700" href={route('app.admin.users.index')}>
            <Shield className="h-5 w-5 dark:text-slate-400" />
          </Link>
        )}
        <Link className="cursor-pointer rounded-lg border p-1 dark:bg-indigo-900 dark:hover:bg-indigo-700" href={route('app.auth.logout')} method="post" as="button">
          <LucideLogOut className="h-5 w-5 dark:text-slate-400" />
        </Link>
      </div>

      {/* Version */}
      <p className="mt-2 px-3 text-center text-xs text-slate-400 dark:text-slate-600">v{version}</p>
    </div>
  );
}
