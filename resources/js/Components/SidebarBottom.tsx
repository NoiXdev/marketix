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
        <div className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-foreground">
          <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent-soft-foreground">
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
          className="cursor-pointer rounded-lg border border-line p-1 hover:bg-surface"
          href={docsUrl}
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Documentation"
          title="Documentation"
        >
          <BookOpen className="h-5 w-5 text-muted" />
        </a>
        {auth.user.super_admin && (
          <Link className="cursor-pointer rounded-lg border border-line p-1 hover:bg-surface" href={route('app.admin.users.index')}>
            <Shield className="h-5 w-5 text-muted" />
          </Link>
        )}
        <Link className="cursor-pointer rounded-lg border border-line p-1 hover:bg-surface" href={route('app.auth.logout')} method="post" as="button">
          <LucideLogOut className="h-5 w-5 text-muted" />
        </Link>
      </div>

      {/* Version */}
      <p className="mt-2 px-3 text-center text-xs text-subtle">v{version}</p>
    </div>
  );
}
