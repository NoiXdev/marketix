import { BackLink, Button } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import FunnelForm, { FunnelFormData, FunnelStep } from '@/Pages/Funnels/partials/FunnelForm';
import { PageProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';

type Option = { value: string; label: string };

export default function FunnelsEdit({
  site,
  funnel,
  stepTypes,
  limits,
}: {
  site: { id: string; name: string };
  funnel: { id: string; name: string; steps: FunnelStep[] };
  stepTypes: Option[];
  limits: { min: number; max: number };
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();
  const { data, setData, put, processing, errors } = useForm<FunnelFormData>({ name: funnel.name, steps: funnel.steps });
  const params = { project: project!.id, site: site.id, funnel: funnel.id };

  async function destroy() {
    if (!(await confirmDelete({ title: funnel.name, text: t('analytics.funnels.delete_confirm'), confirmText: t('common.actions.delete') }))) return;
    router.delete(route('app.project.analytics.funnels.destroy', params));
  }

  return (
    <AppLayout title={t('analytics.funnels.edit')}>
      <div className="px-4 py-6 sm:px-8 sm:py-8">
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
          <div>
            <BackLink href={route('app.project.analytics.show', { project: project!.id, site: site.id, tab: 'conversions' })}>{t('analytics.funnels.back')}</BackLink>
            <h1 className="text-foreground mt-3 text-2xl font-bold tracking-tight">{t('analytics.funnels.edit')}</h1>
          </div>
          <Button variant="danger" onClick={destroy}>
            <Trash2 className="h-4 w-4" />
            {t('analytics.funnels.delete')}
          </Button>
        </div>

        <div className="max-w-2xl">
          <FunnelForm
            data={data}
            errors={errors as Record<string, string | undefined>}
            stepTypes={stepTypes}
            limits={limits}
            processing={processing}
            submitLabel={t('common.actions.save')}
            onChange={(next) => setData(next)}
            onSubmit={() => put(route('app.project.analytics.funnels.update', params))}
          />
        </div>
      </div>
    </AppLayout>
  );
}
