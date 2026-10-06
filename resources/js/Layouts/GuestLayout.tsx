import Brand from '@/Components/Brand';
import LocaleSwitcher from '@/Components/LocaleSwitcher';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

interface GuestLayoutProps {
  title?: string;
  description?: string;
}

export default function GuestLayout({ children, title, description }: PropsWithChildren<GuestLayoutProps>) {
  const { version } = usePage<PageProps>().props;

  return (
    <div className="bg-canvas text-foreground flex min-h-screen">
      {/* Left panel — branding */}
      <div className="text-accent-foreground hidden flex-col justify-between bg-[linear-gradient(150deg,var(--accent),var(--accent-hover))] p-10 lg:flex lg:w-96 xl:w-[480px]">
        <Brand
          forceLogo="dark"
          className="flex items-center gap-2 text-lg font-semibold"
          iconClassName="h-5 w-5 text-accent-foreground"
          textClassName="text-lg font-semibold text-accent-foreground"
        />
        <div>
          <blockquote className="space-y-2">
            <p className="text-accent-foreground/90 text-lg leading-relaxed">Short links, big impact. Manage all your branded links and track every click in one place.</p>
          </blockquote>
          <p className="text-accent-foreground/70 mt-6 text-xs">v{version}</p>
        </div>
      </div>

      {/* Right panel — form */}
      <div className="flex flex-1 flex-col items-center justify-center px-4 py-12 sm:px-8">
        <div className="w-full max-w-sm">
          {/* Mobile logo */}
          <Brand
            className="text-foreground mb-8 flex items-center gap-2 text-lg font-semibold lg:hidden"
            iconClassName="h-5 w-5 text-accent"
            textClassName="text-lg font-semibold"
          />

          {(title || description) && (
            <div className="mb-8">
              {title && <h1 className="text-foreground text-2xl font-bold tracking-tight">{title}</h1>}
              {description && <p className="text-muted mt-1 text-sm">{description}</p>}
            </div>
          )}

          {children}

          <div className="mt-6 flex justify-center">
            <LocaleSwitcher />
          </div>

          <p className="text-subtle mt-8 text-center text-xs lg:hidden">v{version}</p>
        </div>
      </div>
    </div>
  );
}
