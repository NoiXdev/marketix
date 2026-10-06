import { Badge, EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, TableCard } from '@/Components/ui';
import AppLayout from '@/Layouts/AppLayout';
import QrDownloadMenu from '@/Pages/QrCodes/partials/QrDownloadMenu';
import { QrStyle, QrType } from '@/data/qrTypes';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { ROW_LINK_CLASS, rowLink } from '@/lib/rowLink';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Palette, Pencil, Plus, QrCode, Trash2 } from 'lucide-react';

interface QrRow {
  id: string;
  name: string;
  type: QrType;
  is_dynamic: boolean;
  scans: number;
  unique_scans: number;
  created_at: string;
  content: Record<string, string>;
  style: QrStyle;
  dynamic_url: string | null;
}

export default function QrCodesIndex({ qrCodes }: { qrCodes: QrRow[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  async function destroy(qr: QrRow) {
    if (!(await confirmDelete({ title: t('qrcodes.delete.title'), text: t('qrcodes.delete.confirm', { name: qr.name }) }))) return;
    router.delete(route('app.project.qrcodes.destroy', { project: project!.id, qrCode: qr.id }));
  }

  const createBtn = (
    <div className="flex items-center gap-2">
      <LinkButton variant="secondary" href={route('app.project.qr-templates.index', { project: project!.id })}>
        <Palette className="h-4 w-4" /> {t('qr.template.manage')}
      </LinkButton>
      <LinkButton href={route('app.project.qrcodes.create', { project: project!.id })}>
        <Plus className="h-4 w-4" /> {t('qrcodes.create')}
      </LinkButton>
    </div>
  );

  return (
    <AppLayout title={t('qrcodes.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('qrcodes.title')} subtitle={t('qrcodes.subtitle')} action={createBtn} />
        <Flash />

        {qrCodes.length === 0 ? (
          <EmptyState
            icon={QrCode}
            title={t('qrcodes.empty')}
            hint={t('qrcodes.empty_hint')}
            action={
              <LinkButton size="sm" href={route('app.project.qrcodes.create', { project: project!.id })}>
                <Plus className="h-3.5 w-3.5" /> {t('qrcodes.create')}
              </LinkButton>
            }
          />
        ) : (
          <TableCard
            columns={[
              { label: t('qrcodes.columns.name') },
              { label: t('qrcodes.columns.type') },
              { label: t('qrcodes.columns.kind') },
              { label: t('qrcodes.columns.scans'), align: 'right' },
              { label: '' },
            ]}
          >
            <tbody className="divide-line divide-y">
              {qrCodes.map((qr) => (
                <tr key={qr.id} onClick={rowLink(route('app.project.qrcodes.edit', { project: project!.id, qrCode: qr.id }))} className={`group ${ROW_LINK_CLASS}`}>
                  <td className="text-foreground px-4 py-3 font-medium">
                    <Link href={route('app.project.qrcodes.edit', { project: project!.id, qrCode: qr.id })} className="hover:text-accent-soft-foreground">
                      {qr.name}
                    </Link>
                  </td>
                  <td className="text-muted px-4 py-3 capitalize">{qr.type.replace('_', ' ')}</td>
                  <td className="px-4 py-3">
                    <Badge variant={qr.is_dynamic ? 'accent' : 'neutral'}>{qr.is_dynamic ? t('qrcodes.kind.dynamic') : t('qrcodes.kind.static')}</Badge>
                  </td>
                  <td className="text-muted px-4 py-3 text-right tabular-nums">{qr.is_dynamic ? qr.scans.toLocaleString() : '—'}</td>
                  <RowActions>
                    <QrDownloadMenu name={qr.name} type={qr.type} isDynamic={qr.is_dynamic} content={qr.content} dynamicUrl={qr.dynamic_url} style={qr.style} />
                    <IconButton icon={Pencil} label={t('common.actions.edit')} href={route('app.project.qrcodes.edit', { project: project!.id, qrCode: qr.id })} />
                    <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(qr)} />
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
