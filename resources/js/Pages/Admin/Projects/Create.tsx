import { BackLink, Button, Checkbox, Field, FormSection, Input } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';

export default function AdminProjectsCreate() {
  const { data, setData, post, processing, errors } = useForm({ name: '', locked: false as boolean });
  const { t } = useTranslation();

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.admin.projects.store'));
  }

  return (
    <AdminLayout title={t('admin.projects.create.title')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.admin.projects.index')}>{t('common.actions.cancel')}</BackLink>
          <h1 className="mt-2 text-2xl font-bold tracking-tight text-foreground">{t('admin.projects.create.title')}</h1>
        </div>

        <form onSubmit={submit} className="max-w-md">
          <FormSection>
            <Field label={t('admin.projects.fields.name')} error={errors.name}>
              <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
            </Field>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <Checkbox checked={data.locked} onChange={(e) => setData('locked', e.target.checked)} />
              {t('admin.projects.fields.locked')}
            </label>
            <div className="flex gap-2">
              <Button type="submit" loading={processing}>
                {t('common.actions.create')}
              </Button>
              <Link
                href={route('app.admin.projects.index')}
                className="inline-flex items-center rounded-[var(--radius-sm)] px-4 py-2 text-sm font-semibold text-muted transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
              >
                {t('common.actions.cancel')}
              </Link>
            </div>
          </FormSection>
        </form>
      </div>
    </AdminLayout>
  );
}
