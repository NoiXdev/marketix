import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Domain, PageProps, PixelOption } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import LinkForm, { LinkFormData } from './partials/LinkForm';

export default function LinksCreate({
  domains,
  pixels,
}: {
  domains: Pick<Domain, 'id' | 'name'>[];
  pixels: PixelOption[];
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, post, processing, errors } = useForm<LinkFormData>({
    domain_id:          domains[0]?.id.toString() ?? '',
    slug:               '',
    url:                '',
    type:               '0',
    status:             '1',
    password:           '',
    expired_at:         '',
    targeting_geo:      [],
    targeting_device:   [],
    targeting_language: [],
    targeting_ab:       [],
    pixel_ids:          [],
  });

  return (
    <AppLayout title={t('links.create')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.links.index', { project: project!.id })}>{t('links.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('links.create')}</h1>
        </div>

        <div className="max-w-2xl">
          <LinkForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            submitLabel={t('links.create')}
            cancelHref={route('app.project.links.index', { project: project!.id })}
            domains={domains}
            pixels={pixels}
            onSubmit={(e) => {
              e.preventDefault();
              post(route('app.project.links.store', { project: project!.id }));
            }}
          />
        </div>
      </div>
    </AppLayout>
  );
}
