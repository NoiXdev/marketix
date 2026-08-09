import { Flash, IconButton, Input, LinkButton, PageHeader, Pagination, RowActions, TableCard } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Shield, Trash2 } from 'lucide-react';

interface AdminUserRow {
  id: string;
  name: string;
  email: string;
  super_admin: boolean;
  projects_count: number;
}

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function AdminUsersIndex({ users, search }: { users: Paginated<AdminUserRow>; search: string }) {
  const { t } = useTranslation();

  async function destroy(user: AdminUserRow) {
    if (!(await confirmDelete({ title: t('admin.users.delete_confirm.title'), text: t('admin.users.delete_confirm.text', { name: user.name }) }))) return;
    router.delete(route('app.admin.users.destroy', { user: user.id }));
  }

  function onSearch(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    const value = new FormData(e.currentTarget).get('search') as string;
    router.get(route('app.admin.users.index'), { search: value }, { preserveState: true, replace: true });
  }

  const addBtn = (
    <LinkButton href={route('app.admin.users.create')}>
      <Plus className="h-4 w-4" />
      {t('admin.users.add')}
    </LinkButton>
  );

  return (
    <AdminLayout title={t('admin.users.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.users.title')} action={addBtn} />

        <Flash />

        <form onSubmit={onSearch} className="mb-4">
          <div className="w-full max-w-xs">
            <Input name="search" defaultValue={search} placeholder={t('admin.users.search_placeholder')} />
          </div>
        </form>

        <TableCard
          columns={[
            { label: t('admin.users.columns.name') },
            { label: t('admin.users.columns.email') },
            { label: t('admin.users.columns.projects') },
            { label: '' },
          ]}
        >
          <tbody className="divide-y divide-line">
            {users.data.map((user) => (
              <tr key={user.id} onClick={rowLink(route('app.admin.users.edit', { user: user.id }))} className={`group ${ROW_LINK_CLASS}`}>
                <td className="px-4 py-3 font-medium text-foreground">
                  <Link href={route('app.admin.users.edit', { user: user.id })} className="flex items-center gap-2 hover:text-accent-soft-foreground">
                    {user.name}
                    {user.super_admin && <Shield className="h-3.5 w-3.5 text-accent" />}
                  </Link>
                </td>
                <td className="px-4 py-3 text-muted">{user.email}</td>
                <td className="px-4 py-3 text-muted">{user.projects_count}</td>
                <RowActions>
                  <IconButton icon={Pencil} label={t('common.actions.edit')} href={route('app.admin.users.edit', { user: user.id })} />
                  <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(user)} />
                </RowActions>
              </tr>
            ))}
          </tbody>
        </TableCard>

        <Pagination links={users.links} />
      </div>
    </AdminLayout>
  );
}
