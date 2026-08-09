import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import ReportForm, { ReportFormData } from './partials/ReportForm';

type LinkOption = { id: string; slug: string };
type SiteOption = { id: string; name: string };

export default function ReportsCreate({
  links,
  sites,
  types,
}: {
  links: LinkOption[];
  sites: SiteOption[];
  types: string[];
}) {
  const { project, auth, locale } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, post, processing, errors } = useForm<ReportFormData>({
    name: '',
    type: types[0] ?? 'project_summary',
    subject_id: null,
    frequency: 'weekly',
    weekday: 1,
    day_of_month: null,
    period: 'last_7_days',
    formats: { csv: true, pdf: false },
    recipients: auth.user.email ? [auth.user.email] : [],
    active: true,
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    post(route('app.project.reports.store', { project: project!.id }));
  }

  return (
    <AppLayout title={t('reports.form.create_title')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.reports.index', { project: project!.id })}>
            {t('common.actions.cancel')}
          </BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('reports.form.create_title')}</h1>
        </div>

        <div className="max-w-2xl">
          <ReportForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            submitLabel={t('reports.form.submit_create')}
            cancelHref={route('app.project.reports.index', { project: project!.id })}
            links={links}
            sites={sites}
            types={types}
            locale={locale}
            onSubmit={submit}
          />
        </div>
      </div>
    </AppLayout>
  );
}
