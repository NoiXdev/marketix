import ThemeToggle from '@/Components/ThemeToggle';
import { useTranslation } from '@/lib/i18n';
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
  { labelKey: 'users', icon: Users, routeName: 'app.admin.users.index' },
  { labelKey: 'projects', icon: FolderKanban, routeName: 'app.admin.projects.index' },
  { labelKey: 'mailer', icon: Mail, routeName: 'app.admin.mailer.edit' },
  { labelKey: 'branding', icon: Palette, routeName: 'app.admin.branding.edit' },
  { labelKey: 'storage', icon: HardDrive, routeName: 'app.admin.storage.edit' },
  { labelKey: 'activity', icon: ScrollText, routeName: 'app.admin.activity.index' },
];

export default function AdminSidebar() {
  const { url } = usePage();
  const { auth, version } = usePage<import('@/types').PageProps>().props;
  const { t } = useTranslation();

  return (
    <aside className="flex h-screen w-60 flex-col border-r border-line bg-surface">
      <div className="flex h-14 items-center border-b border-line px-4">
        <Brand suffix="Admin" />
      </div>

      <div className="mt-1 border-b border-line px-2">
        <Link
          href={route('app.projects.choose')}
          className="mb-1 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-muted hover:bg-elevated hover:text-foreground"
        >
          <ArrowLeft className="h-4 w-4 shrink-0" />
          {t('admin.nav.back_to_app')}
        </Link>
      </div>

      <nav className="flex-1 overflow-y-auto px-3 py-4">
        <ul className="space-y-0.5">
          {navItems.map(({ labelKey, icon: Icon, routeName }) => {
            const href = route(routeName);
            const isActive = url.startsWith('/' + href.replace(/^https?:\/\/[^/]+\//, ''));
            return (
              <li key={labelKey}>
                <Link
                  href={href}
                  className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    isActive ? 'bg-accent-soft text-accent-soft-foreground' : 'text-muted hover:bg-elevated hover:text-foreground'
                  }`}
                >
                  <Icon className="h-4 w-4 shrink-0" />
                  {t(`admin.nav.${labelKey}`)}
                </Link>
              </li>
            );
          })}

          {/* Horizon is a separate Blade-rendered dashboard, so it needs a full-page anchor, not an Inertia Link. */}
          <li>
            <a
              href="/horizon"
              className={`flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                url.startsWith('/horizon') ? 'bg-accent-soft text-accent-soft-foreground' : 'text-muted hover:bg-elevated hover:text-foreground'
              }`}
            >
              <Activity className="h-4 w-4 shrink-0" />
              {t('admin.nav.horizon')}
            </a>
          </li>
        </ul>
      </nav>

      <div className="border-t border-line p-3">
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

        <div className="flex justify-between px-3">
          <ThemeToggle />
          <a
            className="cursor-pointer rounded-lg border border-line p-1 hover:bg-elevated"
            href="https://docs.noix.dev/marketix/admin/introduction/"
            target="_blank"
            rel="noopener noreferrer"
            aria-label={t('admin.nav.documentation')}
            title={t('admin.nav.documentation')}
          >
            <BookOpen className="h-5 w-5 text-muted" />
          </a>
          {auth.user.super_admin && (
            <Link className="cursor-pointer rounded-lg border border-line p-1 hover:bg-elevated" href={route('app.admin.users.index')}>
              <Shield className="h-5 w-5 text-muted" />
            </Link>
          )}
          <Link className="cursor-pointer rounded-lg border border-line p-1 hover:bg-elevated" href={route('app.auth.logout')} method="post" as="button">
            <LucideLogOut className="h-5 w-5 text-muted" />
          </Link>
        </div>

        <p className="mt-2 px-3 text-center text-xs text-subtle">v{version}</p>
      </div>
    </aside>
  );
}
