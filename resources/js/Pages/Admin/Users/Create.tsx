import { BackLink, Button, Checkbox, Field, FormSection, Input } from '@/Components/ui';
import AdminLayout from '@/Layouts/AdminLayout';
import { useTranslation } from '@/lib/i18n';
import { Link, useForm } from '@inertiajs/react';

export default function AdminUsersCreate() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    super_admin: false as boolean,
  });
  const { t } = useTranslation();

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.admin.users.store'));
  }

  return (
    <AdminLayout title={t('admin.users.create.title')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.admin.users.index')}>{t('common.actions.cancel')}</BackLink>
          <h1 className="mt-2 text-2xl font-bold tracking-tight text-foreground">{t('admin.users.create.title')}</h1>
        </div>

        <form onSubmit={submit} className="max-w-md">
          <FormSection>
            <Field label={t('admin.users.fields.name')} error={errors.name}>
              <Input value={data.name} onChange={(e) => setData('name', e.target.value)} />
            </Field>
            <Field label={t('admin.users.fields.email')} error={errors.email}>
              <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
            </Field>
            <Field label={t('admin.users.fields.password')} error={errors.password}>
              <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} />
            </Field>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <Checkbox checked={data.super_admin} onChange={(e) => setData('super_admin', e.target.checked)} />
              {t('admin.users.fields.super_admin')}
            </label>
            <div className="flex gap-2">
              <Button type="submit" loading={processing}>
                {t('common.actions.create')}
              </Button>
              <Link
                href={route('app.admin.users.index')}
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
