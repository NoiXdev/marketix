import RankedList from '@/Pages/Dashboard/RankedList';
import { CountryFlag } from '@/Components/icons/CountryFlag';
import { Favicon } from '@/Components/icons/Favicon';
import { PlatformIcon } from '@/Components/icons/PlatformIcon';
import { useTranslation } from '@/lib/i18n';
import { useWidgetData } from '@/lib/widgets/useWidgetData';
import type { WidgetConfig } from '@/lib/widgets/schema';
import { ReactNode } from 'react';
import WidgetFrame from './WidgetFrame';

type Row = Record<string, string | number>;

export default function TopListWidget({ config, editing, onConfigure, onRemove }: { config: WidgetConfig; editing: boolean; onConfigure: () => void; onRemove: () => void }) {
  const { t } = useTranslation();
  const { data, loading, error, reload } = useWidgetData<{ rows: Row[] }>('top_list', config);
  const dim = config.dimension ?? 'links';

  function toRow(r: Row, i: number): { key: string; label: string; value: number; prefix?: ReactNode } {
    switch (dim) {
      case 'links': return { key: `${r.id}`, label: `${r.domain_name}/${r.slug}`, value: Number(r.clicks), prefix: <span className="text-xs font-bold text-subtle">{i + 1}</span> };
      case 'countries': return { key: `${r.country_code}-${i}`, label: String(r.country || '—'), value: Number(r.count), prefix: <CountryFlag code={String(r.country_code ?? '')} /> };
      case 'browsers': return { key: `${r.browser}-${i}`, label: String(r.browser || '—'), value: Number(r.count), prefix: <PlatformIcon kind="browser" name={String(r.browser ?? '')} /> };
      case 'os': return { key: `${r.os}-${i}`, label: String(r.os || '—'), value: Number(r.count), prefix: <PlatformIcon kind="os" name={String(r.os ?? '')} /> };
      case 'referrers': return { key: `${r.domain}-${i}`, label: String(r.domain || '—'), value: Number(r.count), prefix: <Favicon domain={String(r.domain ?? '')} /> };
      default: return { key: `${r.city}-${i}`, label: String(r.city || '—'), value: Number(r.count) };
    }
  }

  return (
    <WidgetFrame title={config.title || t(`widgets.dimension.${dim}`)} loading={loading} error={error} onReload={reload} editing={editing} onConfigure={onConfigure} onRemove={onRemove} frameless={!!config.hide_frame} scroll>
      {data && <RankedList emptyLabel={t('widgets.no_data')} rows={data.rows.map(toRow)} />}
    </WidgetFrame>
  );
}
