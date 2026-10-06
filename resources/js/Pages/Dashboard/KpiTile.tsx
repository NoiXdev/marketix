import { Card } from '@/Components/ui';
import { formatCompactNumber } from '@/lib/format';
import { useTranslation } from '@/lib/i18n';
import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

export default function KpiTile({
  label,
  value,
  deltaPct,
  deltaLabel,
  subtitle,
  icon: Icon,
  compact = true,
  lowerIsBetter = false,
  className = '',
}: {
  label: string;
  value: number | string;
  deltaPct?: number | null;
  deltaLabel?: string;
  subtitle?: ReactNode;
  icon: LucideIcon;
  compact?: boolean;
  lowerIsBetter?: boolean;
  className?: string;
}) {
  const { t, locale } = useTranslation();
  const showDelta = deltaPct !== undefined;
  const up = (deltaPct ?? 0) >= 0;
  const good = lowerIsBetter ? !up : up;
  const display = typeof value === 'number' ? (compact ? formatCompactNumber(value) : value.toLocaleString(locale)) : value;
  // The value scales with the tile width; long values such as currency amounts need more room before growing
  const size = String(display).length > 8 ? 'text-xl @min-[10.5rem]:text-[22px] @min-[13rem]:text-[27px]' : 'text-[22px] @min-[7rem]:text-[27px]';

  return (
    <Card className={`@container p-3 sm:p-4 ${className}`}>
      <div className="flex items-center justify-between gap-2">
        <p className="text-muted min-w-0 text-[12.5px] font-semibold hyphens-auto">{label}</p>
        <span className="bg-accent-soft text-accent-soft-foreground grid h-[30px] w-[30px] shrink-0 place-items-center rounded-lg">
          <Icon className="h-4 w-4" />
        </span>
      </div>
      <p
        className={`text-foreground mt-2 font-bold tracking-tight wrap-anywhere tabular-nums ${size}`}
        title={typeof value === 'number' && compact ? value.toLocaleString() : undefined}
      >
        {display}
      </p>
      {showDelta && (
        <p
          className={`mt-0.5 flex flex-wrap items-center gap-x-1 text-xs font-bold ${deltaPct === null || deltaPct === 0 ? 'text-muted' : good ? 'text-success-foreground' : 'text-danger-foreground'}`}
        >
          <span className="whitespace-nowrap">{deltaPct === null ? '—' : deltaPct === 0 ? '± 0 %' : `${up ? '▲' : '▼'} ${Math.abs(deltaPct).toLocaleString(locale)} %`}</span>
          <span className="text-muted font-medium whitespace-nowrap">{deltaLabel ?? t('common.dashboard.vs_previous')}</span>
        </p>
      )}
      {subtitle && <p className="text-muted mt-2 text-xs">{subtitle}</p>}
    </Card>
  );
}
