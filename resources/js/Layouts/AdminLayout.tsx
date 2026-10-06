import AdminSidebar from '@/Components/AdminSidebar';
import Topbar from '@/Components/Topbar';
import { useMobileNav } from '@/lib/mobileNav';
import { Head } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function AdminLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
  const mobileNav = useMobileNav();

  return (
    <div className="bg-canvas text-foreground flex h-dvh overflow-hidden">
      {title && <Head title={title} />}
      <AdminSidebar mobileOpen={mobileNav.open} onMobileClose={mobileNav.close} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar title={title} onMenuClick={mobileNav.show} />
        <main className="flex-1 overflow-y-auto">{children}</main>
      </div>
    </div>
  );
}
