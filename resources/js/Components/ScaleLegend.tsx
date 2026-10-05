import { SCALE_STEPS } from '@/lib/colorScale';
import { useTranslation } from '@/lib/i18n';

export default function ScaleLegend({ className = '' }: { className?: string }) {
  const { t } = useTranslation();

  return (
    <div className={`flex items-center gap-2 text-xs text-subtle ${className}`}>
      <span>{t('common.map.legend_less')}</span>
      {SCALE_STEPS.map((color) => (
        <span key={color} className="inline-block h-3 w-6 rounded-sm" style={{ backgroundColor: color }} />
      ))}
      <span>{t('common.map.legend_more')}</span>
    </div>
  );
}
