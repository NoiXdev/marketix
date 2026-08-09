import { Button, Checkbox, Field, Flash, Input, PageHeader, Select } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/lib/i18n';
import { router, useForm } from '@inertiajs/react';

interface StorageSettings {
  driver: string;
  s3_key: string;
  s3_region: string;
  s3_bucket: string;
  s3_endpoint: string;
  s3_use_path_style: boolean;
}

interface Props {
  settings: StorageSettings;
  has_s3_secret: boolean;
}

export default function AdminStorageEdit({ settings, has_s3_secret }: Props) {
  const { t } = useTranslation();
  const { data, setData, put, processing, errors } = useForm({
    driver: settings.driver,
    s3_key: settings.s3_key,
    s3_secret: '',
    s3_region: settings.s3_region,
    s3_bucket: settings.s3_bucket,
    s3_endpoint: settings.s3_endpoint,
    s3_use_path_style: settings.s3_use_path_style,
  });

  const driverChanged = data.driver !== settings.driver;

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.storage.update'));
  }

  function testConnection() {
    // Reuse the current form values; post them to the test endpoint.
    router.post(route('app.admin.storage.test'), { ...data }, { preserveScroll: true });
  }

  return (
    <AdminLayout title={t('admin.storage.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.storage.title')} />

        <Flash />

        <form onSubmit={submit} className="max-w-md space-y-4">
          <Field label={t('admin.storage.fields.driver')} htmlFor="driver" error={errors.driver}>
            <Select id="driver" value={data.driver} onChange={(e) => setData('driver', e.target.value)}>
              <option value="local">{t('admin.storage.options.local')}</option>
              <option value="s3">{t('admin.storage.options.s3')}</option>
            </Select>
          </Field>

          {driverChanged && (
            <div className="max-w-md rounded-md bg-warning-soft px-4 py-3 text-sm text-warning-foreground">
              {t('admin.storage.driver_changed_warning')}
            </div>
          )}

          {data.driver === 's3' && (
            <fieldset className="space-y-4 rounded-md border border-line p-4">
              <legend className="px-1 text-sm font-semibold text-foreground">{t('admin.storage.options.s3')}</legend>
              <Field label={t('admin.storage.fields.s3_key')} htmlFor="s3_key" error={errors.s3_key}>
                <Input id="s3_key" value={data.s3_key} onChange={(e) => setData('s3_key', e.target.value)} />
              </Field>
              <Field
                label={
                  <>
                    {t('admin.storage.fields.s3_secret')}
                    {has_s3_secret && ' ' + t('admin.common.leave_blank_to_keep')}
                  </>
                }
                htmlFor="s3_secret"
                error={errors.s3_secret}
              >
                <Input
                  id="s3_secret"
                  type="password"
                  placeholder={has_s3_secret ? t('admin.common.secret_set') : ''}
                  value={data.s3_secret}
                  onChange={(e) => setData('s3_secret', e.target.value)}
                />
              </Field>
              <Field label={t('admin.storage.fields.s3_region')} htmlFor="s3_region" error={errors.s3_region}>
                <Input id="s3_region" value={data.s3_region} onChange={(e) => setData('s3_region', e.target.value)} />
              </Field>
              <Field label={t('admin.storage.fields.s3_bucket')} htmlFor="s3_bucket" error={errors.s3_bucket}>
                <Input id="s3_bucket" value={data.s3_bucket} onChange={(e) => setData('s3_bucket', e.target.value)} />
              </Field>
              <Field
                label={t('admin.storage.fields.s3_endpoint')}
                htmlFor="s3_endpoint"
                hint={t('admin.storage.fields.s3_endpoint_hint')}
                error={errors.s3_endpoint}
              >
                <Input
                  id="s3_endpoint"
                  value={data.s3_endpoint}
                  onChange={(e) => setData('s3_endpoint', e.target.value)}
                  placeholder="https://..."
                />
              </Field>
              <label className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox
                  id="s3_use_path_style"
                  checked={data.s3_use_path_style}
                  onChange={(e) => setData('s3_use_path_style', e.target.checked)}
                />
                {t('admin.storage.fields.s3_use_path_style')}
              </label>
            </fieldset>
          )}

          <div className="flex items-center gap-3">
            <Button type="submit" loading={processing}>
              {t('common.actions.save')}
            </Button>
            <Button type="button" variant="secondary" onClick={testConnection}>
              {t('admin.storage.test_connection')}
            </Button>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}
