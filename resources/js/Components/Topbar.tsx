import LocaleSwitcher from '@/Components/LocaleSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';
import UserMenu from '@/Components/UserMenu';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';

export default function Topbar({ title, onMenuClick }: { title?: string; onMenuClick: () => void }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  return (
    <header className="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-line bg-surface px-4 sm:gap-4 sm:px-6">
      <div className="flex min-w-0 items-center gap-3">
        <button
          onClick={onMenuClick}
          aria-haspopup="dialog"
          aria-label={t('common.nav.open_menu')}
          className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-line bg-surface text-muted transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] lg:hidden"
        >
          <Menu className="h-[18px] w-[18px]" />
        </button>
        <div className="min-w-0 truncate text-sm text-muted">
          {/* On phones the project lives in the drawer's switcher, so only the page title is shown */}
          {project?.name && <span className={`font-semibold text-foreground ${title ? 'hidden sm:inline' : ''}`}>{project.name}</span>}
          {project?.name && title && <span className="hidden px-1.5 text-subtle sm:inline">·</span>}
          {title && <span>{title}</span>}
        </div>
      </div>
      <div className="flex shrink-0 items-center gap-2">
        <LocaleSwitcher />
        <ThemeToggle />
        <UserMenu direction="down" hideNameOnMobile />
      </div>
    </header>
  );
}
