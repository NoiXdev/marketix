import { useTranslation } from '@/lib/i18n';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';

export default function VersionLabel({ className = '' }: { className?: string }) {
  const { version, updateAvailable } = usePage<PageProps>().props;
  const { t } = useTranslation();

  if (!updateAvailable) {
    return <p className={className}>v{version}</p>;
  }

  const label = t('common.update_available', { version: updateAvailable.version });

  return (
    <p className={className}>
      <a
        href={updateAvailable.url}
        target="_blank"
        rel="noopener noreferrer"
        title={label}
        aria-label={label}
        className="inline-flex items-center gap-1.5 rounded hover:text-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]"
      >
        v{version}
        <span className="relative flex h-2 w-2">
          <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-75" />
          <span className="relative inline-flex h-2 w-2 rounded-full bg-accent" />
        </span>
      </a>
    </p>
  );
}
