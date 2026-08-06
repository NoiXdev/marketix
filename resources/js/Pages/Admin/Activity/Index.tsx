import ActivityFeed from '@/Components/ActivityFeed';
import { Input, PageHeader, Pagination, Select } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/lib/i18n';
import { ActivityEntry } from '@/types';
import { router } from '@inertiajs/react';

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

interface Filters {
  log_name: string | null;
  project_id: string | null;
  causer: string | null;
  from: string | null;
  to: string | null;
}

export default function AdminActivityIndex({
  activities,
  filters,
  logNames,
  projects,
}: {
  activities: Paginated<ActivityEntry>;
  filters: Filters;
  logNames: string[];
  projects: { id: string; name: string }[];
}) {
  const { t } = useTranslation();

  function apply(patch: Partial<Filters>) {
    const next = { ...filters, ...patch };
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v));
    router.get(route('app.admin.activity.index'), params, { preserveState: true, replace: true });
  }

  return (
    <AdminLayout title={t('admin.activity.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.activity.title')} />

        <div className="mb-4 flex flex-wrap gap-2">
          <div className="w-40">
            <Select value={filters.log_name ?? ''} onChange={(e) => apply({ log_name: e.target.value || null })}>
              <option value="">{t('admin.activity.filters.all_types')}</option>
              {logNames.map((n) => (
                <option key={n} value={n}>
                  {n}
                </option>
              ))}
            </Select>
          </div>
          <div className="w-48">
            <Select value={filters.project_id ?? ''} onChange={(e) => apply({ project_id: e.target.value || null })}>
              <option value="">{t('admin.activity.filters.all_projects')}</option>
              {projects.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </Select>
          </div>
          <div className="w-48">
            <Input
              defaultValue={filters.causer ?? ''}
              onBlur={(e) => apply({ causer: e.target.value || null })}
              placeholder={t('admin.activity.filters.causer_placeholder')}
            />
          </div>
          <div className="w-40">
            <Input type="date" defaultValue={filters.from ?? ''} onChange={(e) => apply({ from: e.target.value || null })} />
          </div>
          <div className="w-40">
            <Input type="date" defaultValue={filters.to ?? ''} onChange={(e) => apply({ to: e.target.value || null })} />
          </div>
        </div>

        <div className="rounded-[var(--radius)] border border-line bg-surface px-5">
          <ActivityFeed activities={activities.data} showProject />
        </div>

        <Pagination links={activities.links} />
      </div>
    </AdminLayout>
  );
}
