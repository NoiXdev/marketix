import ActivityHistory from '@/Components/ActivityHistory';
import AppLayout from '@/Layouts/AppLayout';
import { BackLink, Button, Field, FormSection, Input } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { ActivityEntry, Domain, PageProps } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import DnsInfoBox from '@/Pages/Domains/Partials/DnsInfoBox';
import StatusPills from '@/Pages/Domains/Partials/StatusPills';

export default function DomainsEdit({ domain, appDomain, history }: { domain: Domain; appDomain: string; history?: ActivityEntry[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const { data, setData, put, processing, errors } = useForm({
    name: domain.name,
    redirect_root: domain.redirect_root ?? '',
    redirect_not_found: domain.redirect_not_found ?? '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    put(route('app.project.domains.update', { project: project!.id, domain: domain.id }));
  };

  const [checking, setChecking] = useState(false);

  function check() {
    setChecking(true);
    router.post(
      route('app.project.domains.check', { project: project!.id, domain: domain.id }),
      {},
      { preserveScroll: true, onFinish: () => setChecking(false) },
    );
  }

  return (
    <AppLayout title={t('domains.form.edit_page_title')}>
      <div className="px-8 py-8">
        <div className="mb-6">
          <BackLink href={route('app.project.domains.index', { project: project!.id })}>{t('domains.form.back')}</BackLink>
          <h1 className="mt-3 text-2xl font-bold tracking-tight text-foreground">
            {t('domains.form.edit_title')} <span className="text-accent-soft-foreground">{domain.name}</span>
          </h1>
        </div>

        <div className="mb-6 max-w-lg space-y-4">
          <DnsInfoBox appDomain={appDomain} />

          <FormSection>
            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-foreground">{t('domains.form.status_title')}</h2>
              <Button variant="secondary" size="sm" onClick={check} disabled={checking}>
                <RefreshCw className={`h-3.5 w-3.5 ${checking ? 'animate-spin' : ''}`} />
                {t('domains.form.check_now')}
              </Button>
            </div>
            <StatusPills domain={domain} />
            <dl className="space-y-1 text-xs text-muted">
              {domain.check_details?.dns?.domain_ips && (
                <div>{t('domains.form.resolves_to')} {domain.check_details.dns.domain_ips.join(', ') || '—'}</div>
              )}
              {domain.check_details?.ssl?.error && <div>{t('domains.form.ssl_error')} {domain.check_details.ssl.error}</div>}
              {domain.check_details?.reachable?.error && <div>{t('domains.form.reachable_error')} {domain.check_details.reachable.error}</div>}
              {domain.last_checked_at && <div>{t('domains.form.last_checked')} {new Date(domain.last_checked_at).toLocaleString()}</div>}
            </dl>
          </FormSection>
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
              <Button type="submit" loading={processing}>{t('domains.form.save_submit')}</Button>
              <Link href={route('app.project.domains.index', { project: project!.id })} className="text-sm text-muted transition-colors hover:text-foreground">
                {t('common.actions.cancel')}
              </Link>
            </div>
          </form>
        </div>

        <div className="mb-6 max-w-lg">
          <ActivityHistory history={history} />
        </div>
      </div>
    </AppLayout>
  );
}
