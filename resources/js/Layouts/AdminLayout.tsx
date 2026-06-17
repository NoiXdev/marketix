import AdminSidebar from '@/Components/AdminSidebar';
import { Head } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

interface AdminLayoutProps {
  title?: string;
}

export default function AdminLayout({ children, title }: PropsWithChildren<AdminLayoutProps>) {
  return (
    <div className="flex h-screen overflow-hidden bg-slate-50 dark:bg-slate-950">
      {title && <Head title={title} />}
      <AdminSidebar />
      <main className="flex flex-1 flex-col overflow-y-auto">{children}</main>
    </div>
  );
}
