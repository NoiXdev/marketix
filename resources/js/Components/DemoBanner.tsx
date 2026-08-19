import { useTranslation } from '@/lib/i18n';
import { useDemo } from '@/lib/useDemo';

export default function DemoBanner() {
  const { enabled, resetAt } = useDemo();
  const { t } = useTranslation();

  if (!enabled || !resetAt) {
    return null;
  }

  return (
    <div className="bg-accent px-4 py-2 text-center text-sm text-accent-foreground">
      {t('demo.banner', { time: resetAt })}
    </div>
  );
}
