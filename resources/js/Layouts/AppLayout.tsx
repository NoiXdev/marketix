import Sidebar from '@/Components/Sidebar';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

interface AppLayoutProps {
  title?: string;
}

export default function AppLayout({ children, title }: PropsWithChildren<AppLayoutProps>) {
  const currentProject = usePage<PageProps>().props.project;

  if (!currentProject) {
    return null;
  }

  return (
    <div className="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950">
      {title && <Head title={title} />}
      <Sidebar />
      <main className="flex flex-1 flex-col overflow-y-auto">{children}</main>
    </div>
  );
}
