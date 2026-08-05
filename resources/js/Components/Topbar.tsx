import LocaleSwitcher from '@/Components/LocaleSwitcher';
import ThemeToggle from '@/Components/ThemeToggle';
import UserMenu from '@/Components/UserMenu';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

export default function Topbar({ title }: { title?: string }) {
  const { project } = usePage<PageProps>().props;

  return (
    <header className="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-line bg-surface px-4 sm:px-6">
      <div className="min-w-0 truncate text-sm text-muted">
        {project?.name && <span className="font-semibold text-foreground">{project.name}</span>}
        {project?.name && title && <span className="px-1.5 text-subtle">·</span>}
        {title && <span>{title}</span>}
      </div>
      <div className="flex items-center gap-2">
        <LocaleSwitcher />
        <ThemeToggle />
        <UserMenu direction="down" />
      </div>
    </header>
  );
}
