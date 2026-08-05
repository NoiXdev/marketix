import ActivityHistory from '@/Components/ActivityHistory';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, Field, FormSection, Input, Select } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { ActivityEntry, PageProps, Pixel } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';

interface ProviderOption {
  value: string;
  label: string;
}

export default function PixelsEdit({
  pixel,
  providers,
  history,
}: {
  pixel: Pick<Pixel, 'id' | 'provider' | 'name' | 'tag'>;
  providers: ProviderOption[];
  history?: ActivityEntry[];
}) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, put, processing, errors } = useForm({
    provider: pixel.provider,
    name: pixel.name,
    tag: pixel.tag,
  });

  return (
    <AppLayout title={t('pixels.form.edit_title')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.pixels.index', { project: project!.id })}>{t('pixels.form.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">
            {t('pixels.form.edit_title')} <span className="text-accent-soft-foreground">{pixel.name}</span>
          </h1>
        </div>

        <div className="max-w-lg space-y-4">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              put(route('app.project.pixels.update', { project: project!.id, pixel: pixel.id }));
            }}
            className="space-y-5"
          >
            <FormSection title={t('pixels.form.section')}>
              <Field label={<>{t('pixels.form.provider')} <span className="text-danger-foreground">*</span></>} htmlFor="provider" error={errors.provider}>
                <Select id="provider" value={data.provider} onChange={(e) => setData('provider', e.target.value)}>
                  {providers.map((p) => (
                    <option key={p.value} value={p.value}>{p.label}</option>
                  ))}
                </Select>
              </Field>
              <Field label={<>{t('pixels.form.name')} <span className="text-danger-foreground">*</span></>} htmlFor="name" error={errors.name}>
                <Input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder={t('pixels.form.name_placeholder')} />
              </Field>
              <Field label={<>{t('pixels.form.tag')} <span className="text-danger-foreground">*</span></>} htmlFor="tag" error={errors.tag}>
                <Input id="tag" type="text" value={data.tag} onChange={(e) => setData('tag', e.target.value)} placeholder={t('pixels.form.tag_placeholder')} />
              </Field>
            </FormSection>

            <div className="flex items-center gap-3">
              <Button type="submit" loading={processing}>{t('pixels.form.save_submit')}</Button>
              <Link href={route('app.project.pixels.index', { project: project!.id })} className="text-sm text-muted transition-colors hover:text-foreground">
                {t('common.actions.cancel')}
              </Link>
            </div>
          </form>
          <ActivityHistory history={history} />
        </div>
      </div>
    </AppLayout>
  );
}
