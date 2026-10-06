import Sidebar from '@/Components/Sidebar';
import Topbar from '@/Components/Topbar';
import { useMobileNav } from '@/lib/mobileNav';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

interface AppLayoutProps {
  title?: string;
}

export default function AppLayout({ children, title }: PropsWithChildren<AppLayoutProps>) {
  const currentProject = usePage<PageProps>().props.project;
  const mobileNav = useMobileNav();

  if (!currentProject) {
    return null;
  }

  return (
    <div className="flex h-dvh overflow-hidden bg-canvas text-foreground">
      {title && <Head title={title} />}
      <Sidebar mobileOpen={mobileNav.open} onMobileClose={mobileNav.close} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar title={title} onMenuClick={mobileNav.show} />
        <main className="flex-1 overflow-y-auto">{children}</main>
      </div>
    </div>
  );
}
