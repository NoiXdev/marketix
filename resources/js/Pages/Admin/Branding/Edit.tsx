import { Button, Checkbox, Field, Flash, Input, PageHeader } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

interface Props {
  app_name: string | null;
  logo_light_url: string | null;
  logo_dark_url: string | null;
  logo_email_url: string | null;
  favicon_url: string | null;
}

type ImageField = 'logo_light' | 'logo_dark' | 'logo_email' | 'favicon';

export default function AdminBrandingEdit(props: Props) {
  const { t } = useTranslation();

  const currentUrl: Record<ImageField, string | null> = {
    logo_light: props.logo_light_url,
    logo_dark: props.logo_dark_url,
    logo_email: props.logo_email_url,
    favicon: props.favicon_url,
  };

  const { data, setData, post, processing, errors } = useForm<{
    app_name: string;
    logo_light: File | null;
    logo_dark: File | null;
    logo_email: File | null;
    favicon: File | null;
    remove_logo_light: boolean;
    remove_logo_dark: boolean;
    remove_logo_email: boolean;
    remove_favicon: boolean;
  }>({
    app_name: props.app_name ?? '',
    logo_light: null,
    logo_dark: null,
    logo_email: null,
    favicon: null,
    remove_logo_light: false,
    remove_logo_dark: false,
    remove_logo_email: false,
    remove_favicon: false,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.admin.branding.update'), { forceFormData: true });
  }

  const imageFields: { field: ImageField; remove: keyof typeof data; label: string; hint: string }[] = [
    { field: 'logo_light', remove: 'remove_logo_light', label: t('admin.branding.fields.logo_light'), hint: t('admin.branding.fields.logo_light_hint') },
    { field: 'logo_dark', remove: 'remove_logo_dark', label: t('admin.branding.fields.logo_dark'), hint: t('admin.branding.fields.logo_dark_hint') },
    { field: 'logo_email', remove: 'remove_logo_email', label: t('admin.branding.fields.logo_email'), hint: t('admin.branding.fields.logo_email_hint') },
    { field: 'favicon', remove: 'remove_favicon', label: t('admin.branding.fields.favicon'), hint: t('admin.branding.fields.favicon_hint') },
  ];

  return (
    <AdminLayout title={t('admin.branding.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.branding.title')} />

        <Flash />

        <form onSubmit={submit} className="max-w-md space-y-6">
          <Field label={t('admin.branding.fields.app_name')} hint={t('admin.branding.fields.app_name_hint')} error={errors.app_name}>
            <Input value={data.app_name} onChange={(e) => setData('app_name', e.target.value)} placeholder="Marketix" />
          </Field>

          {imageFields.map(({ field, remove, label, hint }) => (
            <Field key={field} label={label} hint={hint} error={errors[field]}>
              {currentUrl[field] && (
                <img src={currentUrl[field]!} alt={label} className="mb-2 h-10 w-auto rounded border border-line bg-elevated p-1" />
              )}
              <input
                type="file"
                accept={field === 'favicon' ? '.ico,.png,.jpg,.jpeg' : 'image/*'}
                onChange={(e) => setData(field, e.target.files?.[0] ?? null)}
                className="block w-full text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-accent-soft file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-accent-soft-foreground"
              />
              {currentUrl[field] && (
                <label className="mt-1 flex items-center gap-2 text-xs text-muted">
                  <Checkbox checked={Boolean(data[remove])} onChange={(e) => setData(remove, e.target.checked)} />
                  {t('admin.branding.remove_current')}
                </label>
              )}
            </Field>
          ))}

          <Button type="submit" loading={processing}>
            {t('common.actions.save')}
          </Button>
        </form>
      </div>
    </AdminLayout>
  );
}
