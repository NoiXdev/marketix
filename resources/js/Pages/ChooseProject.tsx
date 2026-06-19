import Brand from '@/Components/Brand';
import UserMenu from '@/Components/UserMenu';
import { PageProps, ProjectRole } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';

interface ChooserProject {
    id: string;
    name: string;
    role: ProjectRole;
}

interface ChooseProjectProps {
    projects: ChooserProject[];
}

const roleBadgeClass: Record<ProjectRole, string> = {
    admin: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300',
    member: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

export default function ChooseProject({ projects }: ChooseProjectProps) {
    const { version } = usePage<PageProps>().props;
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return projects;
        return projects.filter((p) => p.name.toLowerCase().includes(q));
    }, [projects, query]);

    const open = (id: string) =>
        router.visit(route('app.project.dashboard', { project: id }));

    return (
        <div className="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-950">
            <Head title="Choose a project" />

            {/* Top strip */}
            <header className="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-800">
                <Brand
                  className="flex items-center gap-2 text-lg font-semibold text-slate-900 dark:text-white"
                  iconClassName="h-5 w-5 text-indigo-500"
                  textClassName="text-lg font-semibold"
                />
                <div className="w-56">
                    <UserMenu direction="down" />
                </div>
            </header>

            {/* Centered content */}
            <main className="flex flex-1 items-start justify-center px-4 py-12 sm:py-20">
                <div className="w-full max-w-md">
                    <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                        Choose a project
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Select a project to continue.
                    </p>

                    {projects.length === 0 ? (
                        <div className="mt-8 rounded-md border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
                            <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                You're not assigned to any project yet.
                            </p>
                            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Contact an administrator to get access.
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Search */}
                            <div className="relative mt-6">
                                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input
                                    type="text"
                                    value={query}
                                    onChange={(e) => setQuery(e.target.value)}
                                    placeholder="Search projects…"
                                    autoFocus
                                    className="block w-full rounded-md border border-slate-300 bg-white py-2 pl-9 pr-3 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
                                />
                            </div>

                            {/* List */}
                            <ul className="mt-4 space-y-2">
                                {filtered.map((project) => (
                                    <li key={project.id}>
                                        <button
                                            onClick={() => open(project.id)}
                                            className="flex w-full items-center justify-between rounded-md border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-900 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:hover:border-indigo-700 dark:hover:bg-slate-700/50"
                                        >
                                            <span className="truncate">{project.name}</span>
                                            <span className={`ml-3 shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ${roleBadgeClass[project.role]}`}>
                                                {project.role === 'admin' ? 'Admin' : 'Member'}
                                            </span>
                                        </button>
                                    </li>
                                ))}
                                {filtered.length === 0 && (
                                    <li className="px-1 py-3 text-sm text-slate-500 dark:text-slate-400">
                                        No projects match "{query}".
                                    </li>
                                )}
                            </ul>
                        </>
                    )}

                    <p className="mt-10 text-center text-xs text-slate-400 dark:text-slate-600">
                        v{version}
                    </p>
                </div>
            </main>
        </div>
    );
}
