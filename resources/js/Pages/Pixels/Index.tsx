import AppLayout from '@/Layouts/AppLayout';
import { EmptyState, Flash, IconButton, LinkButton, PageHeader, RowActions, TableCard } from '@/Components/ui';
import { confirmDelete } from '@/lib/confirm';
import { useTranslation } from '@/lib/i18n';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { PageProps, Pixel } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Zap } from 'lucide-react';

interface ProviderOption {
  value: string;
  label: string;
}

// Brand/content colors (documented exception to the token rule) — dark-safe,
// none of the forbidden slate/gray/indigo families. Unknown → neutral token.
const PROVIDER_COLORS: Record<string, string> = {
  google_tag_manager: 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300',
  google_analytics: 'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
  facebook: 'bg-violet-50 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
  google_ads: 'bg-green-50 text-green-700 dark:bg-green-500/15 dark:text-green-300',
  linkedin: 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
  twitter: 'bg-neutral-soft text-neutral-foreground',
  adroll: 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-300',
  quora: 'bg-red-50 text-red-700 dark:bg-red-500/15 dark:text-red-300',
  pinterest: 'bg-rose-50 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
  bing: 'bg-teal-50 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300',
  snapchat: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/15 dark:text-yellow-300',
  reddit: 'bg-orange-50 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
  tiktok: 'bg-pink-50 text-pink-700 dark:bg-pink-500/15 dark:text-pink-300',
};

export default function PixelsIndex({ pixels, providers }: { pixels: (Pixel & { created_at: string })[]; providers: ProviderOption[] }) {
  const { project } = usePage<PageProps>().props;
  const { t } = useTranslation();

  const providerLabel = (value: string) => providers.find((p) => p.value === value)?.label ?? value;

  async function destroy(pixel: Pixel) {
    if (!(await confirmDelete({ title: t('pixels.delete.title'), text: t('pixels.delete.confirm', { name: pixel.name }) }))) return;
    router.delete(route('app.project.pixels.destroy', { project: project!.id, pixel: pixel.id }));
  }

  const createBtn = (
    <LinkButton href={route('app.project.pixels.create', { project: project!.id })}>
      <Plus className="h-4 w-4" /> {t('pixels.create')}
    </LinkButton>
  );

  return (
    <AppLayout title={t('pixels.title')}>
      <div className="px-8 py-8">
        <PageHeader title={t('pixels.title')} subtitle={t('pixels.subtitle')} action={createBtn} />
        <Flash />

        {pixels.length === 0 ? (
          <EmptyState
            icon={Zap}
            title={t('pixels.empty')}
            hint={t('pixels.empty_hint')}
            action={
              <LinkButton size="sm" href={route('app.project.pixels.create', { project: project!.id })}>
                <Plus className="h-3.5 w-3.5" /> {t('pixels.create')}
              </LinkButton>
            }
          />
        ) : (
          <TableCard
            columns={[
              { label: t('pixels.columns.name') },
              { label: t('pixels.columns.provider') },
              { label: t('pixels.columns.tag') },
              { label: '' },
            ]}
          >
            <tbody className="divide-y divide-line">
              {pixels.map((pixel) => (
                <tr
                  key={pixel.id}
                  onClick={rowLink(route('app.project.pixels.edit', { project: project!.id, pixel: pixel.id }))}
                  className={`group ${ROW_LINK_CLASS}`}
                >
                  <td className="px-4 py-3 font-medium text-foreground">
                    <Link href={route('app.project.pixels.edit', { project: project!.id, pixel: pixel.id })} className="hover:text-accent-soft-foreground">
                      {pixel.name}
                    </Link>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${PROVIDER_COLORS[pixel.provider] ?? 'bg-neutral-soft text-neutral-foreground'}`}>
                      {providerLabel(pixel.provider)}
                    </span>
                  </td>
                  <td className="px-4 py-3 font-mono text-xs text-muted">{pixel.tag}</td>
                  <RowActions>
                    <IconButton icon={Pencil} label={t('common.actions.edit')} href={route('app.project.pixels.edit', { project: project!.id, pixel: pixel.id })} />
                    <IconButton icon={Trash2} label={t('common.actions.delete')} variant="danger" onClick={() => destroy(pixel)} />
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
