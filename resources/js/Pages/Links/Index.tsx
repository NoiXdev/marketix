import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, StatusPill, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Check, Copy, ExternalLink, LinkIcon, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface UrlRow {
  id: string;
  slug: string;
  url: string;
  status: number;
  archived: boolean;
  clicks: number;
  expired_at: string | null;
  created_at: string;
  domain: { id: string; name: string } | null;
}

function CopyButton({ text }: { text: string }) {
  const { t } = useTranslation();
  const [copied, setCopied] = useState(false);

  function copy() {
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <button
      onClick={copy}
      title={copied ? t('links.copy.copied') : t('links.copy.idle')}
      className={`rounded-md p-1.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] ${
        copied ? 'text-success-foreground' : 'text-subtle hover:bg-elevated hover:text-foreground'
      }`}
    >
      {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
    </button>
  );
}

export default function LinksIndex({ urls }: { urls: UrlRow[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  async function destroy(url: UrlRow) {
    if (!(await confirmDelete({ title: t('links.delete.title'), text: t('links.delete.confirm', { slug: url.slug }) }))) return;
    router.delete(route('app.project.links.destroy', { project: project!.id, url: url.id }));
  }

  function toggle(url: UrlRow) {
    router.patch(route('app.project.links.toggle-status', { project: project!.id, url: url.id }));
  }

  const createBtn = (
    <LinkButton href={route('app.project.links.create', { project: project!.id })}>
      <Plus className="h-4 w-4" />
      {t('links.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('links.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('links.title')} subtitle={`${t('links.subtitle')} · ${t('links.count', { count: urls.length })}`} action={createBtn} />
        <Flash />

        {urls.length === 0 ? (
          <EmptyState
            icon={LinkIcon}
            title={t('links.empty')}
            hint={t('links.empty_hint')}
            action={
              <LinkButton size="sm" href={route('app.project.links.create', { project: project!.id })}>
                <Plus className="h-3.5 w-3.5" />
                {t('links.create')}
              </LinkButton>
            }
          />
        ) : (
          <TableCard
            columns={[
              { label: t('links.columns.slug') },
              { label: t('links.columns.target') },
              { label: t('links.columns.status') },
              { label: t('links.columns.clicks'), align: 'right' },
              { label: '' },
            ]}
          >
            <tbody className="divide-y divide-line">
              {urls.map((url) => {
                const shortUrl = url.domain ? `https://${url.domain.name}/${url.slug}` : url.slug;

                return (
                  <tr
                    key={url.id}
                    onClick={rowLink(route('app.project.links.show', { project: project!.id, url: url.id }))}
                    className={`group ${ROW_LINK_CLASS}`}
                  >
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1 font-medium text-foreground">
                        <Link
                          href={route('app.project.links.show', { project: project!.id, url: url.id })}
                          className="flex items-center gap-1 hover:text-accent-soft-foreground"
                        >
                          {url.domain ? (
                            <>
                              <span className="text-subtle">{url.domain.name}/</span>
                              <span>{url.slug}</span>
                            </>
                          ) : (
                            <span>{url.slug}</span>
                          )}
                        </Link>
                        <CopyButton text={shortUrl} />
                      </div>
                      {url.expired_at && (
                        <p className="mt-0.5 text-xs text-warning-foreground">
                          {t('links.expires', { date: new Date(url.expired_at).toLocaleDateString() })}
                        </p>
                      )}
                    </td>
                    <td className="max-w-xs px-4 py-3">
                      <a
                        href={url.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-1 truncate text-muted hover:text-accent-soft-foreground"
                      >
                        <span className="truncate">{url.url}</span>
                        <ExternalLink className="h-3 w-3 shrink-0" />
                      </a>
                    </td>
                    <td className="px-4 py-3">
                      {url.status === 1 ? (
                        <StatusPill status="success">{t('links.status.active')}</StatusPill>
                      ) : (
                        <StatusPill status="neutral">{t('links.status.inactive')}</StatusPill>
                      )}
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums text-muted">{url.clicks.toLocaleString()}</td>
                    <RowActions>
                      <IconButton icon={BarChart3} label={t('links.actions.view_stats')} href={route('app.project.links.show', { project: project!.id, url: url.id })} />
                      <IconButton
                        icon={Power}
                        label={url.status === 1 ? t('links.actions.deactivate') : t('links.actions.activate')}
                        onClick={() => toggle(url)}
                      />
                      <IconButton icon={Pencil} label={t('common.actions.edit')} href={route('app.project.links.edit', { project: project!.id, url: url.id })} />
                      <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(url)} />
                    </RowActions>
                  </tr>
                );
              })}
            </tbody>
          </TableCard>
        )}
      </div>
    </AppLayout>
  );
}
