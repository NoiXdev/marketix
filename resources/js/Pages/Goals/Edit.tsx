import { BackLink, Button, ErrorSummary } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';
import GoalFields, { GoalFormData } from '@/Pages/Goals/partials/GoalFields';
import { Goal, PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function GoalsEdit({ site, goal, goalTypes }: { site: { id: string }; goal: Goal; goalTypes: Option[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, put, processing, errors } = useForm<GoalFormData>({
    name: goal.name,
    type: goal.type,
    match_value: goal.match_value,
    conditions: goal.conditions,
    value: goal.value === null ? '' : String(goal.value),
    currency: goal.currency ?? '',
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    put(route('app.project.analytics.goals.update', { project: project!.id, site: site.id, goal: goal.id }));
  }

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <AppLayout title={t('analytics.goals.edit')}>
      <div className="px-4 py-6 sm:px-8 sm:py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.analytics.goals.index', { project: project!.id, site: site.id })}>{t('analytics.goals.back')}</BackLink>
          <h1 className="text-foreground mt-3 text-2xl font-bold tracking-tight">{t('analytics.goals.edit')}</h1>
        </div>

        <div className="max-w-2xl">
          <form onSubmit={submit} className="space-y-5">
            <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

            <GoalFields data={data} setData={setData} errors={errors} goalTypes={goalTypes} />

            <Button type="submit" loading={processing}>
              {t('common.actions.save')}
            </Button>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
