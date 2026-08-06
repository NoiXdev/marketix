import Brand from '@/Components/Brand';
import UserMenu from '@/Components/UserMenu';
import { Badge, EmptyState, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps, ProjectRole } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { FolderKanban, Search } from 'lucide-react';
import { useMemo, useState } from 'react';

interface ChooserProject {
  id: string;
  name: string;
  role: ProjectRole;
}

export default function ChooseProject({ projects }: { projects: ChooserProject[] }) {
  const { version } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return projects;
    return projects.filter((p) => p.name.toLowerCase().includes(q));
  }, [projects, query]);

  const open = (id: string) => router.visit(route('app.project.dashboard', { project: id }));

  return (
    <div className="flex min-h-screen flex-col bg-canvas">
      <Head title={t('choose_project.title')} />

      <header className="flex items-center justify-between border-b border-line px-6 py-4">
        <Brand
          className="flex items-center gap-2 text-lg font-semibold text-foreground"
          iconClassName="h-5 w-5 text-accent"
          textClassName="text-lg font-semibold"
        />
        <div className="w-56">
          <UserMenu direction="down" />
        </div>
      </header>

      <main className="flex flex-1 items-start justify-center px-4 py-12 sm:py-20">
        <div className="w-full max-w-md">
          <h1 className="text-2xl font-bold tracking-tight text-foreground">{t('choose_project.title')}</h1>
          <p className="mt-1 text-sm text-muted">{t('choose_project.subtitle')}</p>

          {projects.length === 0 ? (
            <div className="mt-8">
              <EmptyState icon={FolderKanban} title={t('choose_project.empty_title')} hint={t('choose_project.empty_hint')} />
            </div>
          ) : (
            <>
              <div className="relative mt-6">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-subtle" />
                <Input
                  type="text"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder={t('choose_project.search_placeholder')}
                  autoFocus
                  className="pl-9"
                />
              </div>

              <ul className="mt-4 space-y-2">
                {filtered.map((project) => (
                  <li key={project.id}>
                    <button
                      onClick={() => open(project.id)}
                      className="flex w-full items-center justify-between rounded-[var(--radius)] border border-line bg-surface px-4 py-3 text-left text-sm font-medium text-foreground shadow-[var(--shadow-sm)] transition-colors hover:border-line-strong hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
                    >
                      <span className="truncate">{project.name}</span>
                      <Badge variant={project.role === 'admin' ? 'accent' : 'neutral'}>{t(`common.roles.${project.role}`)}</Badge>
                    </button>
                  </li>
                ))}
                {filtered.length === 0 && (
                  <li className="px-1 py-3 text-sm text-muted">{t('choose_project.no_match', { query })}</li>
                )}
              </ul>
            </>
          )}

          <p className="mt-10 text-center text-xs text-subtle">v{version}</p>
        </div>
      </main>
    </div>
  );
}
