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
    <header className="border-line bg-surface flex h-14 shrink-0 items-center justify-between gap-3 border-b px-4 sm:gap-4 sm:px-6">
      <div className="flex min-w-0 items-center gap-3">
        <button
          onClick={onMenuClick}
          aria-haspopup="dialog"
          aria-label={t('common.nav.open_menu')}
          className="border-line bg-surface text-muted hover:bg-elevated hover:text-foreground inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none lg:hidden"
        >
          <Menu className="h-[18px] w-[18px]" />
        </button>
        <div className="text-muted min-w-0 truncate text-sm">
          {/* On phones the project lives in the drawer's switcher, so only the page title is shown */}
          {project?.name && <span className={`text-foreground font-semibold ${title ? 'hidden sm:inline' : ''}`}>{project.name}</span>}
          {project?.name && title && <span className="text-subtle hidden px-1.5 sm:inline">·</span>}
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
