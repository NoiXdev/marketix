import ActivityHistory from '@/Components/ActivityHistory';
import AppLayout from '@/Layouts/AppLayout';
import { ActivityEntry, Domain, PageProps, PixelOption } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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
    <AppLayout title="Edit link">
      <div className="px-8 py-8">
        <div className="mb-6">
          <Link
            href={route('app.project.links.index', { project: project!.id })}
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to links
          </Link>
          <h1 className="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
            Edit <span className="text-indigo-600 dark:text-indigo-400">{url.slug}</span>
          </h1>
        </div>

        <div className="max-w-2xl space-y-4">
          <LinkForm
            data={data}
            setData={setData}
            errors={errors}
            processing={processing}
            submitLabel="Save changes"
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
