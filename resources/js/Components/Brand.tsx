import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { Link2 } from 'lucide-react';

interface BrandProps {
  className?: string;
  iconClassName?: string;
  textClassName?: string;
  logoClassName?: string;
  forceLogo?: 'light' | 'dark';
  suffix?: string;
}

export default function Brand({
  className = 'flex items-center gap-2',
  iconClassName = 'h-5 w-5 text-indigo-600',
  textClassName = 'text-sm font-semibold text-slate-900 dark:text-white',
  logoClassName = 'h-6 w-auto',
  forceLogo,
  suffix,
}: BrandProps) {
  const { branding } = usePage<PageProps>().props;
  const { appName, logoLight, logoDark } = branding;
  const hasLogo = Boolean(logoLight || logoDark);

  return (
    <span className={className}>
      {hasLogo ? (
        forceLogo === 'dark' ? (
          <img src={(logoDark || logoLight)!} alt={appName} className={logoClassName} />
        ) : forceLogo === 'light' ? (
          <img src={(logoLight || logoDark)!} alt={appName} className={logoClassName} />
        ) : (
          <>
            <img src={(logoLight || logoDark)!} alt={appName} className={`${logoClassName} block dark:hidden`} />
            <img src={(logoDark || logoLight)!} alt={appName} className={`${logoClassName} hidden dark:block`} />
          </>
        )
      ) : (
        <>
          <Link2 className={iconClassName} />
          <span className={textClassName}>{appName}</span>
        </>
      )}
      {suffix && <span className={textClassName}>{suffix}</span>}
    </span>
  );
}
