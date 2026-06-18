import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Link2 } from 'lucide-react';
import { PropsWithChildren } from 'react';

interface ProfileLayoutProps {
  title?: string;
}

export default function ProfileLayout({ children, title }: PropsWithChildren<ProfileLayoutProps>) {
  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      {title && <Head title={title} />}
      <header className="flex h-14 items-center gap-2 border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900">
        <Link2 className="h-5 w-5 text-indigo-600" />
        <span className="text-sm font-semibold text-slate-900 dark:text-white">Marketix</span>
      </header>
      <main className="mx-auto w-full max-w-md px-4 py-10">
        <Link
          href="/"
          className="mb-6 inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
        >
          <ArrowLeft className="h-4 w-4" />
          Back
        </Link>
        {children}
      </main>
    </div>
  );
}
