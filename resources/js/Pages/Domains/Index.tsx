import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { Domain, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import StatusPills from '@/Pages/Domains/Partials/StatusPills';
import { Globe, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

function relativeTime(iso: string | null, locale: string, neverLabel: string): string {
  if (!iso) return neverLabel;
  const rtf = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' });
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return rtf.format(-s, 'second');
  if (s < 3600) return rtf.format(-Math.floor(s / 60), 'minute');
  if (s < 86400) return rtf.format(-Math.floor(s / 3600), 'hour');
  return rtf.format(-Math.floor(s / 86400), 'day');
}

export default function DomainsIndex({ domains }: { domains: Domain[]; appDomain: string }) {
  const { project } = usePage<PageProps>().props;
  const { t, locale } = useTranslation();
  const [checking, setChecking] = useState<string | null>(null);

  async function destroy(domain: Domain) {
    if (!(await confirmDelete({ title: t('domains.delete.title'), text: t('domains.delete.confirm', { name: domain.name }) }))) return;
    router.delete(route('app.project.domains.destroy', { project: project!.id, domain: domain.id }));
  }

  function check(domain: Domain) {
    setChecking(domain.id);
    router.post(route('app.project.domains.check', { project: project!.id, domain: domain.id }), {}, { preserveScroll: true, onFinish: () => setChecking(null) });
  }

  const createBtn = (
    <LinkButton href={route('app.project.domains.create', { project: project!.id })}>
      <Plus className="h-4 w-4" /> {t('domains.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('domains.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('domains.title')} subtitle={t('domains.subtitle')} action={createBtn} />
        <Flash />

        {domains.length === 0 ? (
          <EmptyState
            icon={Globe}
            title={t('domains.empty')}
            hint={t('domains.empty_hint')}
            action={
              <LinkButton size="sm" href={route('app.project.domains.create', { project: project!.id })}>
                <Plus className="h-3.5 w-3.5" /> {t('domains.create')}
              </LinkButton>
            }
          />
        ) : (
          <TableCard
            columns={[
              { label: t('domains.columns.domain') },
              { label: t('domains.columns.root_redirect') },
              { label: t('domains.columns.not_found_redirect') },
              { label: t('domains.columns.status') },
              { label: '' },
            ]}
          >
            <tbody className="divide-y divide-line">
              {domains.map((domain) => (
                <tr
                  key={domain.id}
                  onClick={rowLink(route('app.project.domains.edit', { project: project!.id, domain: domain.id }))}
                  className={`group ${ROW_LINK_CLASS}`}
                >
                  <td className="px-4 py-3 font-medium text-foreground">
                    <Link href={route('app.project.domains.edit', { project: project!.id, domain: domain.id })} className="hover:text-accent-soft-foreground">
                      {domain.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3 text-muted">
                    {domain.redirect_root ? <span className="block max-w-xs truncate">{domain.redirect_root}</span> : <span className="text-subtle">—</span>}
                  </td>
                  <td className="px-4 py-3 text-muted">
                    {domain.redirect_not_found ? <span className="block max-w-xs truncate">{domain.redirect_not_found}</span> : <span className="text-subtle">—</span>}
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex flex-col gap-1">
                      <StatusPills domain={domain} />
                      <span className="text-xs text-subtle">{relativeTime(domain.last_checked_at, locale, t('domains.never_checked'))}</span>
                    </div>
                  </td>
                  <RowActions>
                    <IconButton icon={RefreshCw} label={t('domains.actions.check')} onClick={() => check(domain)} disabled={checking === domain.id} spinning={checking === domain.id} />
                    <IconButton icon={Pencil} label={t('common.actions.edit')} href={route('app.project.domains.edit', { project: project!.id, domain: domain.id })} />
                    <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(domain)} />
                  </RowActions>
                </tr>
              ))}
            </tbody>
          </TableCard>
        )}
      </div>
    </AppLayout>
  );
}
