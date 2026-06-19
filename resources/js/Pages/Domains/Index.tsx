import AppLayout from '@/Layouts/AppLayout';
import { confirmDelete } from '@/lib/confirm';
import { rowLink, ROW_LINK_CLASS } from '@/lib/rowLink';
import { Domain, PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import StatusPills from '@/Pages/Domains/Partials/StatusPills';
import { Globe, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';

export default function DomainsIndex({ domains }: { domains: Domain[]; appDomain: string }) {
  const { project, flash } = usePage<PageProps>().props;

  async function destroy(domain: Domain) {
    if (!(await confirmDelete({ title: 'Delete domain?', text: `Delete "${domain.name}"? This cannot be undone.` }))) return;
    router.delete(route('app.project.domains.destroy', { project: project!.id, domain: domain.id }));
  }

  const [checking, setChecking] = useState<string | null>(null);

  function check(domain: Domain) {
    setChecking(domain.id);
    router.post(
      route('app.project.domains.check', { project: project!.id, domain: domain.id }),
      {},
      { preserveScroll: true, onFinish: () => setChecking(null) },
    );
  }

  function relativeTime(iso: string | null): string {
    if (!iso) return 'never checked';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.round(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.round(hrs / 24)}d ago`;
  }

  return (
    <AppLayout title="Domains">
      <div className="px-8 py-8">
        {/* Header */}
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Domains</h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
              Manage custom domains for this project
            </p>
          </div>
          <Link
            href={route('app.project.domains.create', { project: project!.id })}
            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          >
            <Plus className="h-4 w-4" />
            Add domain
          </Link>
        </div>

        {/* Flash */}
        {flash?.success && (
          <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
            {flash.success}
          </div>
        )}

        {/* Table */}
        {domains.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 dark:border-slate-700 dark:bg-slate-900">
            <Globe className="mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">No domains yet</p>
            <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">Add a custom domain to start using it for your links.</p>
            <Link
              href={route('app.project.domains.create', { project: project!.id })}
              className="mt-4 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
            >
              <Plus className="h-3.5 w-3.5" />
              Add domain
            </Link>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800">
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Domain
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Root redirect
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    404 redirect
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Status
                  </th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {domains.map((domain) => (
                  <tr
                    key={domain.id}
                    onClick={rowLink(route('app.project.domains.edit', { project: project!.id, domain: domain.id }))}
                    className={`group ${ROW_LINK_CLASS}`}
                  >
                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">
                      <Link
                        href={route('app.project.domains.edit', { project: project!.id, domain: domain.id })}
                        className="hover:text-indigo-600 dark:hover:text-indigo-400"
                      >
                        {domain.name}
                      </Link>
                    </td>
                    <td className="px-4 py-3 text-slate-500 dark:text-slate-400">
                      {domain.redirect_root ? (
                        <span className="truncate max-w-xs block">{domain.redirect_root}</span>
                      ) : (
                        <span className="text-slate-300 dark:text-slate-600">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-slate-500 dark:text-slate-400">
                      {domain.redirect_not_found ? (
                        <span className="truncate max-w-xs block">{domain.redirect_not_found}</span>
                      ) : (
                        <span className="text-slate-300 dark:text-slate-600">—</span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex flex-col gap-1">
                        <StatusPills domain={domain} />
                        <span className="text-xs text-slate-400 dark:text-slate-500">{relativeTime(domain.last_checked_at)}</span>
                      </div>
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                        <button
                          onClick={() => check(domain)}
                          disabled={checking === domain.id}
                          className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-50 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                          title="Check status"
                        >
                          <RefreshCw className={`h-4 w-4 ${checking === domain.id ? 'animate-spin' : ''}`} />
                        </button>
                        <Link
                          href={route('app.project.domains.edit', { project: project!.id, domain: domain.id })}
                          className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                          <Pencil className="h-4 w-4" />
                        </Link>
                        <button
                          onClick={() => destroy(domain)}
                          className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                        >
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
