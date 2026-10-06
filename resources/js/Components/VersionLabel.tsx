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
        className="hover:text-accent inline-flex items-center gap-1.5 rounded focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] focus-visible:outline-none"
      >
        v{version}
        <span className="relative flex h-2 w-2">
          <span className="bg-accent absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" />
          <span className="bg-accent relative inline-flex h-2 w-2 rounded-full" />
        </span>
      </a>
    </p>
  );
}
