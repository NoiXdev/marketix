import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, Shield, User } from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from '@/lib/i18n';

function initials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export default function UserMenu({ direction = 'up' }: { direction?: 'up' | 'down' } = {}) {
    const { auth } = usePage<PageProps>().props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);
    const { t } = useTranslation();

    return (
        <div className="relative" ref={ref}>
            <button
                onClick={() => setOpen((o) => !o)}
                className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-muted transition-colors hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
            >
                {/* Avatar */}
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-semibold text-accent-soft-foreground">
                    {initials(auth.user.name)}
                </span>
                <span className="flex-1 truncate text-left text-sm font-medium">
                    {auth.user.name}
                </span>
            </button>

            {open && (
                <>
                    <div
                        className="fixed inset-0 z-10"
                        onClick={() => setOpen(false)}
                    />
                    <div className={`absolute left-0 z-20 w-56 rounded-md border border-line bg-surface py-1 shadow-lg ${direction === 'down' ? 'top-full mt-1' : 'bottom-full mb-1'}`}>
                        <div className="border-b border-line px-3 py-2">
                            <p className="text-xs font-medium text-foreground">
                                {auth.user.name}
                            </p>
                            <p className="text-xs text-muted">
                                {auth.user.email}
                            </p>
                        </div>
                        {auth.user.super_admin && (
                            <Link
                                href={route('app.admin.users.index')}
                                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-muted hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                                onClick={() => setOpen(false)}
                            >
                                <Shield className="h-4 w-4 text-subtle" />
                                {t('common.user_menu.admin')}
                            </Link>
                        )}
                        <Link
                            href={route('app.profile.edit')}
                            className="flex w-full items-center gap-2 px-3 py-2 text-sm text-muted hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                            onClick={() => setOpen(false)}
                        >
                            <User className="h-4 w-4 text-subtle" />
                            {t('common.user_menu.profile')}
                        </Link>
                        <Link
                            href={route('app.auth.logout')}
                            method="post"
                            as="button"
                            className="flex w-full items-center gap-2 px-3 py-2 text-sm text-muted hover:bg-surface hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                            onClick={() => setOpen(false)}
                        >
                            <LogOut className="h-4 w-4 text-subtle" />
                            {t('common.user_menu.logout')}
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}
