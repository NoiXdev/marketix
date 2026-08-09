import ActivityFeed from '@/Components/ActivityFeed';
import AppLayout from '@/Layouts/AppLayout';
import { PageHeader, Pagination, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { ActivityEntry, PageProps } from '@/types';
import { router, usePage } from '@inertiajs/react';

interface Paginated<T> {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
}

export default function ActivityIndex({
  activities,
  logName,
  logNames,
}: {
  activities: Paginated<ActivityEntry>;
  logName: string | null;
  logNames: string[];
}) {
  const project = usePage<PageProps>().props.project;
  const { t } = useTranslation();

  function onFilter(value: string) {
    router.get(route('app.project.activity.index', { project: project!.id }), value ? { log_name: value } : {}, {
      preserveState: true,
      replace: true,
    });
  }

  const filter = (
    <div className="w-56">
      <Select value={logName ?? ''} onChange={(e) => onFilter(e.target.value)}>
        <option value="">{t('activity.all')}</option>
        {logNames.map((n) => (
          <option key={n} value={n}>{n}</option>
        ))}
      </Select>
    </div>
  );

  return (
    <AppLayout title={t('activity.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('activity.title')} action={filter} />
        <div className="rounded-[var(--radius)] border border-line bg-surface px-5 shadow-[var(--shadow-sm)]">
          <ActivityFeed activities={activities.data} />
        </div>
        <div className="mt-4">
          <Pagination links={activities.links} />
        </div>
      </div>
    </AppLayout>
  );
}
