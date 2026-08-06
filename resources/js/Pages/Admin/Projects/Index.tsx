import { Flash, IconButton, Input, PageHeader, Pagination, RowActions, TableCard } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { Link, router } from '@inertiajs/react';
import { ExternalLink, Lock, Pencil, Plus, Trash2 } from 'lucide-react';

interface AdminProjectRow {
  id: string;
  name: string;
  locked: boolean;
  users_count: number;
}

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function AdminProjectsIndex({ projects, search }: { projects: Paginated<AdminProjectRow>; search: string }) {
  const { t } = useTranslation();

  async function destroy(project: AdminProjectRow) {
    if (!(await confirmDelete({ title: t('admin.projects.delete_confirm.title'), text: t('admin.projects.delete_confirm.text', { name: project.name }) })))
      return;
    router.delete(route('app.admin.projects.destroy', { project: project.id }));
  }

  function onSearch(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const value = new FormData(e.currentTarget).get('search') as string;
    router.get(route('app.admin.projects.index'), { search: value }, { preserveState: true, replace: true });
  }

  const addBtn = (
    <Link
      href={route('app.admin.projects.create')}
      className="inline-flex items-center gap-2 rounded-[var(--radius-sm)] bg-accent px-4 py-2 text-sm font-semibold text-accent-foreground transition-colors hover:bg-accent-hover focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
    >
      <Plus className="h-4 w-4" />
      {t('admin.projects.add')}
    </Link>
  );

  return (
    <AdminLayout title={t('admin.projects.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.projects.title')} action={addBtn} />

        <Flash />

        <form onSubmit={onSearch} className="mb-4">
          <div className="w-full max-w-xs">
            <Input name="search" defaultValue={search} placeholder={t('admin.projects.search_placeholder')} />
          </div>
        </form>

        <TableCard columns={[{ label: t('admin.projects.columns.name') }, { label: t('admin.projects.columns.members') }, { label: '' }]}>
          <tbody className="divide-y divide-line">
            {projects.data.map((project) => (
              <tr key={project.id} onClick={rowLink(route('app.admin.projects.edit', { project: project.id }))} className={`group ${ROW_LINK_CLASS}`}>
                <td className="px-4 py-3 font-medium text-foreground">
                  <Link href={route('app.admin.projects.edit', { project: project.id })} className="flex items-center gap-2 hover:text-accent-soft-foreground">
                    {project.name}
                    {project.locked && <Lock className="h-3.5 w-3.5 text-warning-foreground" />}
                  </Link>
                </td>
                <td className="px-4 py-3 text-muted">{project.users_count}</td>
                <RowActions>
                  <IconButton icon={ExternalLink} label={t('admin.projects.actions.open')} href={route('app.project.dashboard', { project: project.id })} />
                  <IconButton icon={Pencil} label={t('common.actions.edit')} href={route('app.admin.projects.edit', { project: project.id })} />
                  <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(project)} />
                </RowActions>
              </tr>
            ))}
          </tbody>
        </TableCard>

        <Pagination links={projects.links} />
      </div>
    </AdminLayout>
  );
}
