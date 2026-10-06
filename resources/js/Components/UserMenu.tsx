import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, ChevronDown, LogOut, Shield, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import VersionLabel from './VersionLabel';

function initials(name: string): string {
  return name
    .split(' ')
    .map((n) => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

export default function UserMenu({ direction = 'up', hideNameOnMobile = false }: { direction?: 'up' | 'down'; hideNameOnMobile?: boolean } = {}) {
  const { auth } = usePage<PageProps>().props;
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const { t } = useTranslation();

  useEffect(() => {
    function onDoc(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') setOpen(false);
    }
    document.addEventListener('mousedown', onDoc);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDoc);
      document.removeEventListener('keydown', onKey);
    };
  }, []);

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setOpen((o) => !o)}
        className="text-muted hover:bg-elevated hover:text-foreground flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
      >
        {/* Avatar */}
        <span className="bg-accent-soft text-accent-soft-foreground flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold">
          {initials(auth.user.name)}
        </span>
        <span className={`flex-1 truncate text-left text-sm font-medium ${hideNameOnMobile ? 'hidden sm:block' : ''}`}>{auth.user.name}</span>
        <ChevronDown className="text-subtle h-4 w-4 shrink-0" />
      </button>

      {open && (
        <div className={`border-line bg-surface absolute right-0 z-20 w-56 rounded-md border py-1 shadow-lg ${direction === 'down' ? 'top-full mt-1' : 'bottom-full mb-1'}`}>
          <div className="border-line border-b px-3 py-2">
            <p className="text-foreground text-xs font-medium">{auth.user.name}</p>
            <p className="text-muted text-xs">{auth.user.email}</p>
          </div>
          {auth.user.super_admin && (
            <Link
              href={route('app.admin.users.index')}
              className="text-muted hover:bg-elevated hover:text-foreground flex w-full items-center gap-2 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
              onClick={() => setOpen(false)}
            >
              <Shield className="text-subtle h-4 w-4" />
              {t('common.user_menu.admin')}
            </Link>
          )}
          <Link
            href={route('app.profile.edit')}
            className="text-muted hover:bg-elevated hover:text-foreground flex w-full items-center gap-2 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            onClick={() => setOpen(false)}
          >
            <User className="text-subtle h-4 w-4" />
            {t('common.user_menu.profile')}
          </Link>
          <a
            href="https://docs.noix.dev/marketix/user/getting-started/"
            target="_blank"
            rel="noopener noreferrer"
            className="text-muted hover:bg-elevated hover:text-foreground flex w-full items-center gap-2 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            onClick={() => setOpen(false)}
          >
            <BookOpen className="text-subtle h-4 w-4" />
            {t('common.user_menu.docs')}
          </a>
          {auth.user.super_admin && (
            <a
              href="https://docs.noix.dev/marketix/admin/introduction/"
              target="_blank"
              rel="noopener noreferrer"
              className="text-muted hover:bg-elevated hover:text-foreground flex w-full items-center gap-2 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
              onClick={() => setOpen(false)}
            >
              <BookOpen className="text-subtle h-4 w-4" />
              {t('common.user_menu.admin_docs')}
            </a>
          )}
          <Link
            href={route('app.auth.logout')}
            method="post"
            as="button"
            className="text-danger-foreground hover:bg-danger-soft flex w-full items-center gap-2 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
            onClick={() => setOpen(false)}
          >
            <LogOut className="text-danger-foreground h-4 w-4" />
            {t('common.user_menu.logout')}
          </Link>
          <VersionLabel className="border-line text-subtle border-t px-3 pt-2 pb-1 text-center text-[11px]" />
        </div>
      )}
    </div>
  );
}
