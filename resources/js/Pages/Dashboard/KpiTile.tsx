import { Card } from '@/Components/ui';
import { formatCompactNumber } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

export default function KpiTile({
  label, value, deltaPct, subtitle, icon: Icon, compact = true,
}: {
  label: string; value: number; deltaPct?: number | null; subtitle?: ReactNode; icon: LucideIcon; compact?: boolean;
}) {
  const { t } = useTranslation();
  const showDelta = deltaPct !== undefined;
  const up = (deltaPct ?? 0) >= 0;

  return (
    <Card className="p-4">
      <div className="flex items-center justify-between">
        <p className="text-[12.5px] font-semibold text-muted">{label}</p>
        <span className="grid h-[30px] w-[30px] place-items-center rounded-lg bg-accent-soft text-accent-soft-foreground">
          <Icon className="h-4 w-4" />
        </span>
      </div>
      <p className="mt-2 text-[27px] font-bold tracking-tight text-foreground tabular-nums" title={compact ? value.toLocaleString() : undefined}>
        {compact ? formatCompactNumber(value) : value.toLocaleString()}
      </p>
      {showDelta && (
        <p className={`mt-0.5 inline-flex items-center gap-1 text-xs font-bold ${deltaPct === null ? 'text-muted' : up ? 'text-success-foreground' : 'text-danger-foreground'}`}>
          {deltaPct === null ? '—' : `${up ? '▲' : '▼'} ${Math.abs(deltaPct)} %`}
          <span className="font-medium text-muted">{t('common.dashboard.vs_previous')}</span>
        </p>
      )}
      {subtitle && <p className="mt-2 text-xs text-muted">{subtitle}</p>}
    </Card>
  );
}
