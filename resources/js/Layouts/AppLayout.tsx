import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
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
    <div className="flex h-screen overflow-hidden bg-canvas text-foreground">
      {title && <Head title={title} />}
      <Sidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar title={title} />
        <main className="flex-1 overflow-y-auto">{children}</main>
      </div>
    </div>
  );
}
