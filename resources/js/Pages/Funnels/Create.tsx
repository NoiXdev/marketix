import { BackLink } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslation } from '@/lib/i18n';
import FunnelForm, { FunnelFormData } from '@/Pages/Funnels/partials/FunnelForm';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

type Option = { value: string; label: string };

export default function FunnelsCreate({ site, stepTypes, limits }: { site: { id: string; name: string }; stepTypes: Option[]; limits: { min: number; max: number } }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, post, processing, errors } = useForm<FunnelFormData>({
    name: '',
    steps: [
      { type: 'pageview', value: '', label: null },
      { type: 'pageview', value: '', label: null },
    ],
  });

  const back = route('app.project.analytics.show', { project: project!.id, site: site.id, tab: 'conversions' });

  return (
    <AppLayout title={t('analytics.funnels.create')}>
      <div className="px-4 py-6 sm:px-8 sm:py-8">
        <div className="mb-6">
          <BackLink href={back}>{t('analytics.funnels.back')}</BackLink>
          <h1 className="text-foreground mt-3 text-2xl font-bold tracking-tight">{t('analytics.funnels.create')}</h1>
          <p className="text-muted mt-1 max-w-2xl text-sm">{t('analytics.funnels.intro', { site: site.name })}</p>
        </div>

        <div className="max-w-2xl">
          <FunnelForm
            data={data}
            errors={errors as Record<string, string | undefined>}
            stepTypes={stepTypes}
            limits={limits}
            processing={processing}
            submitLabel={t('analytics.funnels.create')}
            onChange={(next) => setData(next)}
            onSubmit={() => post(route('app.project.analytics.funnels.store', { project: project!.id, site: site.id }))}
          />
        </div>
      </div>
    </AppLayout>
  );
}
