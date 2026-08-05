import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, Field, FormSection, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import DnsInfoBox from '@/Pages/Domains/Partials/DnsInfoBox';

export default function DomainsCreate({ appDomain }: { appDomain: string }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, post, processing, errors } = useForm({
    name: '',
    redirect_root: '',
    redirect_not_found: '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    post(route('app.project.domains.store', { project: project!.id }));
  };

  return (
    <AppLayout title={t('domains.form.add_title')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.domains.index', { project: project!.id })}>{t('domains.form.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">{t('domains.form.add_title')}</h1>
        </div>

        <div className="max-w-lg">
          <DnsInfoBox appDomain={appDomain} />
        </div>

        <div className="max-w-lg">
          <form onSubmit={submit} className="space-y-5">
            <FormSection>
              <Field label={<>{t('domains.form.name')} <span className="text-danger-foreground">*</span></>} htmlFor="name" error={errors.name}>
                <Input id="name" type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder={t('domains.form.name_placeholder')} />
              </Field>
              <Field label={t('domains.form.root_redirect')} htmlFor="redirect_root" hint={t('domains.form.root_redirect_hint')} error={errors.redirect_root}>
                <Input id="redirect_root" type="url" value={data.redirect_root} onChange={(e) => setData('redirect_root', e.target.value)} placeholder={t('domains.form.root_redirect_placeholder')} />
              </Field>
              <Field label={t('domains.form.not_found_redirect')} htmlFor="redirect_not_found" hint={t('domains.form.not_found_redirect_hint')} error={errors.redirect_not_found}>
                <Input id="redirect_not_found" type="url" value={data.redirect_not_found} onChange={(e) => setData('redirect_not_found', e.target.value)} placeholder={t('domains.form.not_found_redirect_placeholder')} />
              </Field>
            </FormSection>

            <div className="flex items-center gap-3">
              <Button type="submit" loading={processing}>{t('domains.form.create_submit')}</Button>
              <Link href={route('app.project.domains.index', { project: project!.id })} className="text-sm text-muted transition-colors hover:text-foreground">
                {t('common.actions.cancel')}
              </Link>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
