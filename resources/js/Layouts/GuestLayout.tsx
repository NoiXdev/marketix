import { Link2 } from 'lucide-react';
import { PropsWithChildren } from 'react';

interface GuestLayoutProps {
  title?: string;
  description?: string;
}

export default function GuestLayout({ children, title, description }: PropsWithChildren<GuestLayoutProps>) {
  return (
    <div className="flex min-h-screen bg-slate-50 dark:bg-slate-950">
      {/* Left panel — branding */}
      <div className="hidden flex-col justify-between bg-slate-900 p-10 text-white lg:flex lg:w-96 xl:w-[480px]">
        <div className="flex items-center gap-2 text-lg font-semibold">
          <Link2 className="h-5 w-5 text-indigo-400" />
          <span>Marketix</span>
        </div>
        <div>
          <blockquote className="space-y-2">
            <p className="text-lg leading-relaxed text-slate-300">Short links, big impact. Manage all your branded links and track every click in one place.</p>
          </blockquote>
        </div>
      </div>

      {/* Right panel — form */}
      <div className="flex flex-1 flex-col items-center justify-center px-4 py-12 sm:px-8">
        <div className="w-full max-w-sm">
          {/* Mobile logo */}
          <div className="mb-8 flex items-center gap-2 text-lg font-semibold text-slate-900 lg:hidden dark:text-white">
            <Link2 className="h-5 w-5 text-indigo-500" />
            <span>Marketix</span>
          </div>

          {(title || description) && (
            <div className="mb-8">
              {title && <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{title}</h1>}
              {description && <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>}
            </div>
          )}

          {children}
        </div>
      </div>
    </div>
  );
}
