import { Button, Field, Flash, FormSection, Input, PageHeader } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/lib/i18n';
import { useForm } from '@inertiajs/react';

interface Props {
  agency_name: string | null;
  agency_address: string | null;
  hosting_provider: string | null;
  hosting_location: string | null;
}

export default function AdminOperatorEdit(props: Props) {
  const { t } = useTranslation();

  const { data, setData, put, processing, errors } = useForm({
    agency_name: props.agency_name ?? '',
    agency_address: props.agency_address ?? '',
    hosting_provider: props.hosting_provider ?? '',
    hosting_location: props.hosting_location ?? '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.operator.update'));
  }

  return (
    <AdminLayout title={t('admin.operator.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('admin.operator.title')} />

        <Flash />

        <form onSubmit={submit} className="space-y-6">
          <FormSection title={t('admin.operator.sections.agency')}>
            <Field label={t('admin.operator.fields.agency_name')} htmlFor="agency_name" error={errors.agency_name}>
              <Input id="agency_name" value={data.agency_name} onChange={(e) => setData('agency_name', e.target.value)} />
            </Field>

            <Field label={t('admin.operator.fields.agency_address')} hint={t('admin.operator.fields.agency_address_hint')} htmlFor="agency_address" error={errors.agency_address}>
              <Input id="agency_address" value={data.agency_address} onChange={(e) => setData('agency_address', e.target.value)} />
            </Field>
          </FormSection>

          <FormSection title={t('admin.operator.sections.hosting')}>
            <Field label={t('admin.operator.fields.hosting_provider')} htmlFor="hosting_provider" error={errors.hosting_provider}>
              <Input id="hosting_provider" value={data.hosting_provider} onChange={(e) => setData('hosting_provider', e.target.value)} />
            </Field>

            <Field label={t('admin.operator.fields.hosting_location')} htmlFor="hosting_location" error={errors.hosting_location}>
              <Input id="hosting_location" value={data.hosting_location} onChange={(e) => setData('hosting_location', e.target.value)} />
            </Field>
          </FormSection>

          <Button type="submit" loading={processing}>
            {t('common.actions.save')}
          </Button>
        </form>
      </div>
    </AdminLayout>
  );
}
