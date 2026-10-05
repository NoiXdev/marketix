import { useTranslation } from '@/lib/i18n';
import { usePoll } from '@inertiajs/react';

export default function LiveVisitors({ count }: { count: number }) {
  const { t } = useTranslation();
  usePoll(30000, { only: ['liveVisitors'] });

  return (
    <span
      className="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-3 py-1 text-xs font-semibold text-foreground"
      title={t('analytics.dashboard.live.hint')}
    >
      <span className="relative flex h-2 w-2">
        {count > 0 && <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-[color:var(--success-dot)] opacity-60" />}
        <span className={`relative inline-flex h-2 w-2 rounded-full ${count > 0 ? 'bg-[color:var(--success-dot)]' : 'bg-[color:var(--neutral-dot)]'}`} />
      </span>
      {t('analytics.dashboard.live.count', { count: count.toLocaleString() })}
    </span>
  );
}
