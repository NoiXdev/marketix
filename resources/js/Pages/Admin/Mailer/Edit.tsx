import { Button, Field, Flash, FormSection, Input, PageHeader, Select } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

interface MailerSettings {
  default_mailer: string;
  from_address: string;
  from_name: string;
  postal_url: string;
  smtp_host: string;
  smtp_port: number;
  smtp_username: string;
  smtp_scheme: string;
}

interface Props {
  settings: MailerSettings;
  has_postal_key: boolean;
  has_smtp_password: boolean;
}

export default function AdminMailerEdit({ settings, has_postal_key, has_smtp_password }: Props) {
  const { t } = useTranslation();
  const { data, setData, put, processing, errors } = useForm({
    default_mailer: settings.default_mailer,
    from_address: settings.from_address,
    from_name: settings.from_name,
    postal_url: settings.postal_url,
    postal_key: '',
    smtp_host: settings.smtp_host,
    smtp_port: settings.smtp_port,
    smtp_username: settings.smtp_username,
    smtp_password: '',
    smtp_scheme: settings.smtp_scheme,
  });

  const testForm = useForm({ test_email: '' });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.mailer.update'));
  }

  function sendTest(e: React.FormEvent) {
    e.preventDefault();
    testForm.post(route('app.admin.mailer.test'), { preserveScroll: true });
  }

  return (
    <AdminLayout title={t('admin.mailer.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.mailer.title')} />

        <Flash />

        <form onSubmit={submit} className="max-w-md space-y-4">
          <Field label={t('admin.mailer.fields.default_mailer')} htmlFor="default_mailer" error={errors.default_mailer}>
            <Select id="default_mailer" value={data.default_mailer} onChange={(e) => setData('default_mailer', e.target.value)}>
              <option value="postal">{t('admin.mailer.options.postal')}</option>
              <option value="smtp">{t('admin.mailer.options.smtp')}</option>
              <option value="log">{t('admin.mailer.options.log')}</option>
            </Select>
          </Field>

          <Field label={t('admin.mailer.fields.from_address')} htmlFor="from_address" error={errors.from_address}>
            <Input id="from_address" type="email" value={data.from_address} onChange={(e) => setData('from_address', e.target.value)} />
          </Field>

          <Field label={t('admin.mailer.fields.from_name')} htmlFor="from_name" error={errors.from_name}>
            <Input id="from_name" value={data.from_name} onChange={(e) => setData('from_name', e.target.value)} />
          </Field>

          {data.default_mailer === 'postal' && (
            <fieldset className="space-y-4 rounded-md border border-line p-4">
              <legend className="px-1 text-sm font-semibold text-foreground">{t('admin.mailer.options.postal')}</legend>
              <Field label={t('admin.mailer.fields.postal_url')} htmlFor="postal_url" error={errors.postal_url}>
                <Input id="postal_url" value={data.postal_url} onChange={(e) => setData('postal_url', e.target.value)} />
              </Field>
              <Field
                label={
                  <>
                    {t('admin.mailer.fields.postal_key')}
                    {has_postal_key && ' ' + t('admin.common.leave_blank_to_keep')}
                  </>
                }
                htmlFor="postal_key"
                error={errors.postal_key}
              >
                <Input
                  id="postal_key"
                  type="password"
                  placeholder={has_postal_key ? t('admin.common.secret_set') : ''}
                  value={data.postal_key}
                  onChange={(e) => setData('postal_key', e.target.value)}
                />
              </Field>
            </fieldset>
          )}

          {data.default_mailer === 'smtp' && (
            <fieldset className="space-y-4 rounded-md border border-line p-4">
              <legend className="px-1 text-sm font-semibold text-foreground">{t('admin.mailer.options.smtp')}</legend>
              <Field label={t('admin.mailer.fields.smtp_host')} htmlFor="smtp_host" error={errors.smtp_host}>
                <Input id="smtp_host" value={data.smtp_host} onChange={(e) => setData('smtp_host', e.target.value)} />
              </Field>
              <Field label={t('admin.mailer.fields.smtp_port')} htmlFor="smtp_port" error={errors.smtp_port}>
                <Input
                  id="smtp_port"
                  type="number"
                  value={data.smtp_port}
                  onChange={(e) => setData('smtp_port', Number(e.target.value))}
                />
              </Field>
              <Field label={t('admin.mailer.fields.smtp_username')} htmlFor="smtp_username" error={errors.smtp_username}>
                <Input id="smtp_username" value={data.smtp_username} onChange={(e) => setData('smtp_username', e.target.value)} />
              </Field>
              <Field
                label={
                  <>
                    {t('admin.mailer.fields.smtp_password')}
                    {has_smtp_password && ' ' + t('admin.common.leave_blank_to_keep')}
                  </>
                }
                htmlFor="smtp_password"
                error={errors.smtp_password}
              >
                <Input
                  id="smtp_password"
                  type="password"
                  placeholder={has_smtp_password ? t('admin.common.secret_set') : ''}
                  value={data.smtp_password}
                  onChange={(e) => setData('smtp_password', e.target.value)}
                />
              </Field>
              <Field label={t('admin.mailer.fields.smtp_scheme')} htmlFor="smtp_scheme" error={errors.smtp_scheme}>
                <Input id="smtp_scheme" value={data.smtp_scheme} onChange={(e) => setData('smtp_scheme', e.target.value)} />
              </Field>
            </fieldset>
          )}

          <Button type="submit" loading={processing}>
            {t('common.actions.save')}
          </Button>
        </form>

        <form onSubmit={sendTest} className="mt-8 max-w-md">
          <FormSection title={t('admin.mailer.test.title')}>
            <Field label={t('admin.mailer.test.recipient_label')} htmlFor="test_email" error={testForm.errors.test_email}>
              <Input
                id="test_email"
                type="email"
                value={testForm.data.test_email}
                onChange={(e) => testForm.setData('test_email', e.target.value)}
              />
            </Field>
            <Button type="submit" variant="secondary" loading={testForm.processing}>
              {t('admin.mailer.test.send_button')}
            </Button>
          </FormSection>
        </form>
      </div>
    </AdminLayout>
  );
}
