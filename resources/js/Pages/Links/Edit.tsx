import ActivityHistory from '@/Components/ActivityHistory';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { ActivityEntry, Domain, PageProps, PixelOption } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import LinkForm, { LinkFormData } from './partials/LinkForm';
import { AbVariant, GeoRule, DeviceRule, LanguageRule } from './partials/TargetingSection';

interface UrlData {
  id: string;
  domain_id: string;
  slug: string;
  url: string;
  type: number;
  status: number;
  password: string;
  has_password: boolean;
  expired_at: string | null;
  archived: boolean;
  targeting_geo: GeoRule[];
  targeting_device: DeviceRule[];
  targeting_language: LanguageRule[];
  targeting_ab: AbVariant[];
  pixel_ids: string[];
}

export default function LinksEdit({
  url,
  domains,
  pixels,
  history,
}: {
  url: UrlData;
  domains: Pick<Domain, 'id' | 'name'>[];
  pixels: PixelOption[];
  history?: ActivityEntry[];
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, put, processing, errors } = useForm<LinkFormData>({
    domain_id:          url.domain_id.toString(),
    slug:               url.slug,
    url:                url.url,
    type:               url.type.toString(),
    status:             url.status.toString(),
    password:           url.password ?? '',
    expired_at:         url.expired_at ?? '',
    targeting_geo:      url.targeting_geo ?? [],
    targeting_device:   url.targeting_device ?? [],
    targeting_language: url.targeting_language ?? [],
    targeting_ab:       url.targeting_ab ?? [],
    pixel_ids:          url.pixel_ids ?? [],
  });

  return (
    <AppLayout title={t('common.actions.edit')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.links.index', { project: project!.id })}>{t('links.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">
            {t('common.actions.edit')} <span className="text-accent-soft-foreground">{url.slug}</span>
          </h1>
        </div>

        <div className="max-w-2xl space-y-4">
          <LinkForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            submitLabel={t('common.actions.save')}
            cancelHref={route('app.project.links.index', { project: project!.id })}
            domains={domains}
            pixels={pixels}
            hasPassword={url.has_password}
            onSubmit={(e) => {
              e.preventDefault();
              put(route('app.project.links.update', { project: project!.id, url: url.id }));
            }}
          />
          <ActivityHistory history={history} />
        </div>
      </div>
    </AppLayout>
  );
}
