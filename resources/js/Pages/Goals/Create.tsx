import { BackLink, Button, ErrorSummary } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';
import GoalFields, { GoalFormData } from '@/Pages/Goals/partials/GoalFields';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function GoalsCreate({ site, goalTypes, defaultCurrency }: { site: { id: string }; goalTypes: Option[]; defaultCurrency: string | null }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm<GoalFormData>({
    name: '',
    type: 'event',
    match_value: '',
    conditions: [],
    value: '',
    currency: defaultCurrency ?? '',
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    post(route('app.project.analytics.goals.store', { project: project!.id, site: site.id }));
  }

  const errorMessages = Object.values(errors).filter(Boolean) as string[];

  return (
    <AppLayout title={t('analytics.goals.create')}>
      <div className="px-4 py-6 sm:px-8 sm:py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.analytics.goals.index', { project: project!.id, site: site.id })}>{t('analytics.goals.back')}</BackLink>
          <h1 className="text-foreground mt-3 text-2xl font-bold tracking-tight">{t('analytics.goals.create')}</h1>
        </div>

        <div className="max-w-2xl">
          <form onSubmit={submit} className="space-y-5">
            <ErrorSummary title={t('links.form.save_error_title')} errors={errorMessages} />

            <GoalFields data={data} setData={setData} errors={errors} goalTypes={goalTypes} />

            <Button type="submit" loading={processing}>
              {t('analytics.goals.create')}
            </Button>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
