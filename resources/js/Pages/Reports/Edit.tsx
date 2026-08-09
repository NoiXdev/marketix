import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import ReportForm, { ReportFormData } from './partials/ReportForm';

type LinkOption = { id: string; slug: string };
type SiteOption = { id: string; name: string };

interface ReportRecord {
  id: string;
  name: string;
  type: string;
  subject_id: string | null;
  frequency: string;
  weekday: number | null;
  day_of_month: number | null;
  period: string;
  formats: { csv: boolean; pdf: boolean };
  recipients: string[];
  active: boolean;
}

export default function ReportsEdit({
  report,
  links,
  sites,
  types,
}: {
  report: ReportRecord;
  links: LinkOption[];
  sites: SiteOption[];
  types: string[];
}) {
  const { project, locale } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, put, processing, errors } = useForm<ReportFormData>({
    name: report.name,
    type: report.type,
    subject_id: report.subject_id,
    frequency: report.frequency,
    weekday: report.weekday,
    day_of_month: report.day_of_month,
    period: report.period,
    formats: report.formats,
    recipients: report.recipients,
    active: report.active,
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    put(route('app.project.reports.update', { project: project!.id, report: report.id }));
  }

  return (
    <AppLayout title={t('reports.form.edit_title')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.reports.index', { project: project!.id })}>
            {t('common.actions.cancel')}
          </BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('reports.form.edit_title')}</h1>
        </div>

        <div className="max-w-2xl">
          <ReportForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            submitLabel={t('reports.form.submit_save')}
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
