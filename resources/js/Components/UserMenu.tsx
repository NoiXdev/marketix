import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, Shield, User } from 'lucide-react';
import { useRef, useState } from 'react';

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

    return (
        <div className="relative" ref={ref}>
            <button
                onClick={() => setOpen((o) => !o)}
                className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
            >
                {/* Avatar */}
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
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
                    <div className={`absolute left-0 z-20 w-56 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800 ${direction === 'down' ? 'top-full mt-1' : 'bottom-full mb-1'}`}>
                        <div className="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
                            <p className="text-xs font-medium text-slate-900 dark:text-white">
                                {auth.user.name}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                {auth.user.email}
                            </p>
                        </div>
                        {auth.user.super_admin && (
                            <Link
                                href={route('app.admin.users.index')}
                                className="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700"
                                onClick={() => setOpen(false)}
                            >
                                <Shield className="h-4 w-4 text-slate-400" />
                                Admin
                            </Link>
                        )}
                        <Link
                            href={route('app.profile.edit')}
                            className="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700"
                            onClick={() => setOpen(false)}
                        >
                            <User className="h-4 w-4 text-slate-400" />
                            Profile
                        </Link>
                        <Link
                            href={route('app.auth.logout')}
                            method="post"
                            as="button"
                            className="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700"
                            onClick={() => setOpen(false)}
                        >
                            <LogOut className="h-4 w-4 text-slate-400" />
                            Log out
                        </Link>
                    </div>
                </>
            )}
        </div>
    );
}
