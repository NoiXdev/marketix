import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Check, Copy, ExternalLink, LinkIcon, Pencil, Plus, Power, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface UrlRow {
  id: string;
  slug: string;
  url: string;
  status: number;
  archived: boolean;
  clicks: number;
  expired_at: string | null;
  created_at: string;
  domain: { id: string; name: string } | null;
}

function StatusBadge({ status }: { status: number }) {
  return status === 1 ? (
    <span className="inline-flex items-center rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">
      Active
    </span>
  ) : (
    <span className="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">
      Inactive
    </span>
  );
}

function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);

  function copy() {
    navigator.clipboard.writeText(text).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <button
      onClick={copy}
      title={copied ? 'Copied!' : 'Copy short link'}
      className={`rounded p-1.5 transition-colors ${
        copied
          ? 'text-green-500'
          : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200'
      }`}
    >
      {copied ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
    </button>
  );
}

export default function LinksIndex({ urls }: { urls: UrlRow[] }) {
  const { project, flash } = usePage<PageProps>().props;

  function destroy(url: UrlRow) {
    if (!confirm(`Delete "${url.slug}"? This cannot be undone.`)) return;
    router.delete(route('app.project.links.destroy', { project: project!.id, url: url.id }));
  }

  function toggle(url: UrlRow) {
    router.patch(route('app.project.links.toggle-status', { project: project!.id, url: url.id }));
  }

  return (
    <AppLayout title="Links">
      <div className="px-8 py-8">
        {/* Header */}
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Links</h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage your short links</p>
          </div>
          <Link
            href={route('app.project.links.create', { project: project!.id })}
            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          >
            <Plus className="h-4 w-4" />
            Create link
          </Link>
        </div>

        {/* Flash */}
        {flash?.success && (
          <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">
            {flash.success}
          </div>
        )}

        {/* Table */}
        {urls.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 dark:border-slate-700 dark:bg-slate-900">
            <LinkIcon className="mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">No links yet</p>
            <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">Create your first short link to get started.</p>
            <Link
              href={route('app.project.links.create', { project: project!.id })}
              className="mt-4 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
            >
              <Plus className="h-3.5 w-3.5" />
              Create link
            </Link>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800">
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Short link
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Destination
                  </th>
                  <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Status
                  </th>
                  <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Clicks
                  </th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {urls.map((url) => {
                  const shortUrl = url.domain
                    ? `https://${url.domain.name}/${url.slug}`
                    : url.slug;

                  return (
                    <tr key={url.id} className="group">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-1 font-medium text-slate-900 dark:text-white">
                          <Link
                            href={route('app.project.links.show', { project: project!.id, url: url.id })}
                            className="flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-400"
                          >
                            {url.domain ? (
                              <>
                                <span className="text-slate-400 dark:text-slate-500">{url.domain.name}/</span>
                                <span>{url.slug}</span>
                              </>
                            ) : (
                              <span>{url.slug}</span>
                            )}
                          </Link>
                          <CopyButton text={shortUrl} />
                        </div>
                        {url.expired_at && (
                          <p className="mt-0.5 text-xs text-amber-500">
                            Expires {new Date(url.expired_at).toLocaleDateString()}
                          </p>
                        )}
                      </td>
                      <td className="max-w-xs px-4 py-3">
                        <a
                          href={url.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center gap-1 truncate text-slate-500 hover:text-indigo-600 dark:text-slate-400 dark:hover:text-indigo-400"
                        >
                          <span className="truncate">{url.url}</span>
                          <ExternalLink className="h-3 w-3 shrink-0" />
                        </a>
                      </td>
                      <td className="px-4 py-3">
                        <StatusBadge status={url.status} />
                      </td>
                      <td className="px-4 py-3 text-right text-slate-500 tabular-nums dark:text-slate-400">
                        {url.clicks.toLocaleString()}
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                          <Link
                            href={route('app.project.links.show', { project: project!.id, url: url.id })}
                            title="View stats"
                            className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                          >
                            <BarChart3 className="h-4 w-4" />
                          </Link>
                          <button
                            onClick={() => toggle(url)}
                            title={url.status === 1 ? 'Deactivate' : 'Activate'}
                            className={`rounded p-1.5 hover:bg-slate-100 dark:hover:bg-slate-800 ${
                              url.status === 1
                                ? 'text-green-500 hover:text-green-700'
                                : 'text-slate-400 hover:text-slate-600'
                            }`}
                          >
                            <Power className="h-4 w-4" />
                          </button>
                          <Link
                            href={route('app.project.links.edit', { project: project!.id, url: url.id })}
                            className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                          >
                            <Pencil className="h-4 w-4" />
                          </Link>
                          <button
                            onClick={() => destroy(url)}
                            className="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                          >
                            <Trash2 className="h-4 w-4" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppLayout>
  );
}
