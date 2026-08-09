import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Activity, ArrowLeft, ChevronLeft, FolderKanban, HardDrive, Mail, Palette, ScrollText, Users } from 'lucide-react';
import { useState } from 'react';
import Brand from './Brand';

type NavItem = { key: string; icon: typeof Users; routeName?: string; href?: string };
type NavGroup = { labelKey: string; items: NavItem[] };

const groups: NavGroup[] = [
  {
    labelKey: 'management',
    items: [
      { key: 'users', icon: Users, routeName: 'app.admin.users.index' },
      { key: 'projects', icon: FolderKanban, routeName: 'app.admin.projects.index' },
    ],
  },
  {
    labelKey: 'settings',
    items: [
      { key: 'mailer', icon: Mail, routeName: 'app.admin.mailer.edit' },
      { key: 'branding', icon: Palette, routeName: 'app.admin.branding.edit' },
      { key: 'storage', icon: HardDrive, routeName: 'app.admin.storage.edit' },
    ],
  },
  {
    labelKey: 'system',
    items: [
      { key: 'activity', icon: ScrollText, routeName: 'app.admin.activity.index' },
      { key: 'horizon', icon: Activity, href: '/horizon' }, // Blade-rendered → plain anchor
    ],
  },
];

const stripHost = (href: string) => '/' + href.replace(/^https?:\/\/[^/]+\//, '');

export default function AdminSidebar() {
  const { url } = usePage();
  const { version } = usePage<PageProps>().props;
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

  const tooltip = (label: string) => (
    <span className="pointer-events-none absolute left-[calc(100%+10px)] top-1/2 z-40 hidden -translate-y-1/2 whitespace-nowrap rounded-md bg-foreground px-2 py-1 text-xs font-semibold text-canvas shadow-[var(--shadow)] group-hover:block group-focus-within:block">
      {label}
    </span>
  );

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
            <Brand suffix="Admin" />
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

      {/* Back to app (replaces the tenant project switcher) */}
      <div className="border-b border-line p-2">
        <div className="group relative">
          <Link
            href={route('app.projects.choose')}
            aria-label={t('admin.nav.back_to_app')}
            className={`flex items-center gap-3 rounded-md px-2.5 py-2 text-sm font-medium text-muted transition-colors hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
              collapsed ? 'justify-center' : ''
            }`}
          >
            <ArrowLeft className="h-[18px] w-[18px] shrink-0" />
            {!collapsed && <span className="flex-1 truncate">{t('admin.nav.back_to_app')}</span>}
          </Link>
          {collapsed && tooltip(t('admin.nav.back_to_app'))}
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 overflow-y-auto px-2 py-3">
        {groups.map((group) => (
          <div
            key={group.labelKey}
            className={collapsed ? 'mt-2 border-t border-line pt-2 first:mt-0 first:border-t-0 first:pt-0' : 'mt-4 first:mt-0'}
          >
            {!collapsed && (
              <p className="px-2.5 pb-1 pt-1.5 text-[10.5px] font-bold uppercase tracking-wider text-subtle">
                {t(`admin.nav.groups.${group.labelKey}`)}
              </p>
            )}
            <ul className="space-y-0.5">
              {group.items.map(({ key, icon: Icon, routeName, href: extHref }) => {
                const href = extHref ?? route(routeName!);
                const path = extHref ?? stripHost(href);
                const isActive = url.startsWith(path);
                const label = t(`admin.nav.${key}`);
                const cls = `flex items-center gap-3 rounded-md px-2.5 py-2 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
                  collapsed ? 'justify-center' : ''
                } ${isActive ? 'bg-accent-soft text-accent-soft-foreground' : 'text-muted hover:bg-surface hover:text-foreground'}`;
                const inner = (
                  <>
                    <Icon className="h-[18px] w-[18px] shrink-0" />
                    {!collapsed && <span className="flex-1 truncate">{label}</span>}
                  </>
                );
                return (
                  <li key={key} className="group relative">
                    {extHref ? (
                      <a href={extHref} aria-label={label} className={cls}>
                        {inner}
                      </a>
                    ) : (
                      <Link href={href} aria-label={label} className={cls}>
                        {inner}
                      </Link>
                    )}
                    {collapsed && tooltip(label)}
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </nav>

      {/* Footer: version */}
      <div className="border-t border-line px-3 py-2">
        <p className={`text-[11px] text-subtle ${collapsed ? 'text-center' : ''}`}>v{version}</p>
      </div>
    </aside>
  );
}
