import AdminSidebar from '@/Components/AdminSidebar';
import Topbar from '@/Components/Topbar';
import { Head } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function AdminLayout({ children, title }: PropsWithChildren<{ title?: string }>) {
  return (
    <div className="flex h-screen overflow-hidden bg-canvas text-foreground">
      {title && <Head title={title} />}
      <AdminSidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar title={title} />
        <main className="flex-1 overflow-y-auto">{children}</main>
      </div>
    </div>
  );
}
