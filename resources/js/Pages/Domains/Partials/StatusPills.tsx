import { Badge } from '@/Components/ui';
import { useTranslation } from '@/lib/i18n';
import { Domain } from '@/types';

function Pill({ label, value }: { label: string; value: boolean | null }) {
  const variant = value === true ? 'success' : value === false ? 'danger' : 'neutral';
  const mark = value === true ? '✓' : value === false ? '✗' : '–';
  return (
    <Badge variant={variant}>
      <span aria-hidden>{mark}</span>
      {label}
    </Badge>
  );
}

export default function StatusPills({ domain }: { domain: Domain }) {
  const { t } = useTranslation();
  return (
    <div className="flex flex-wrap items-center gap-1.5">
      <Pill label={t('domains.status.dns')} value={domain.dns_ok} />
      <Pill label={t('domains.status.reachable')} value={domain.reachable_ok} />
      <Pill label={t('domains.status.ssl')} value={domain.ssl_ok} />
    </div>
  );
}
