import AppLayout from '@/Layouts/AppLayout';
import { confirmDelete } from '@/lib/confirm';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, QrCode, Trash2 } from 'lucide-react';

interface QrRow {
  id: string;
  name: string;
  type: string;
  is_dynamic: boolean;
  scans: number;
  unique_scans: number;
  created_at: string;
}

export default function QrCodesIndex({ qrCodes }: { qrCodes: QrRow[] }) {
  const { project, flash } = usePage<PageProps>().props;

  async function destroy(qr: QrRow) {
    if (!(await confirmDelete({ title: 'Delete QR code?', text: `Delete "${qr.name}"?` }))) return;
    router.delete(route('app.project.qrcodes.destroy', { project: project!.id, qrCode: qr.id }));
  }

  return (
    <AppLayout title="QR Codes">
      <div className="px-8 py-8">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">QR Codes</h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Generate and manage your QR codes</p>
          </div>
          <Link href={route('app.project.qrcodes.create', { project: project!.id })}
            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
            <Plus className="h-4 w-4" /> Create QR code
          </Link>
        </div>

        {flash?.success && (
          <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
            {flash.success}
          </div>
        )}

        {qrCodes.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 dark:border-slate-700 dark:bg-slate-900">
            <QrCode className="mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">No QR codes yet</p>
            <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">Create your first QR code to get started.</p>
            <Link href={route('app.project.qrcodes.create', { project: project!.id })}
              className="mt-4 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">
              <Plus className="h-3.5 w-3.5" /> Create QR code
            </Link>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800">
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Name</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Kind</th>
                  <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Scans</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {qrCodes.map(qr => (
                  <tr
                    key={qr.id}
                    onClick={rowLink(route('app.project.qrcodes.edit', { project: project!.id, qrCode: qr.id }))}
                    className={`group ${ROW_LINK_CLASS}`}
                  >
                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                      <Link
                        href={route('app.project.qrcodes.edit', { project: project!.id, qrCode: qr.id })}
                        className="hover:text-indigo-600 dark:hover:text-indigo-400"
                      >
                        {qr.name}
                      </Link>
                    </td>
                    <td className="px-4 py-3 capitalize text-slate-600 dark:text-slate-400">{qr.type.replace('_', ' ')}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                        qr.is_dynamic
                          ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400'
                          : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
                      }`}>
                        {qr.is_dynamic ? 'Dynamic' : 'Static'}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums text-slate-500 dark:text-slate-400">
                      {qr.is_dynamic ? qr.scans.toLocaleString() : '—'}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                        <Link href={route('app.project.qrcodes.edit', { project: project!.id, qrCode: qr.id })}
                          className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                          <Pencil className="h-4 w-4" />
                        </Link>
                        <button onClick={() => destroy(qr)}
                          className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400">
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
