import AppLayout from '@/Layouts/AppLayout';
import { PageProps, Pixel } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Zap } from 'lucide-react';

interface ProviderOption {
  value: string;
  label: string;
}

const PROVIDER_COLORS: Record<string, string> = {
  google_tag_manager: 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
  google_analytics: 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
  facebook: 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400',
  google_ads: 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400',
  linkedin: 'bg-sky-50 text-sky-700 dark:bg-sky-900/20 dark:text-sky-400',
  twitter: 'bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
  adroll: 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-400',
  quora: 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400',
  pinterest: 'bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-400',
  bing: 'bg-teal-50 text-teal-700 dark:bg-teal-900/20 dark:text-teal-400',
  snapchat: 'bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400',
  reddit: 'bg-orange-50 text-orange-700 dark:bg-orange-900/20 dark:text-orange-400',
  tiktok: 'bg-pink-50 text-pink-700 dark:bg-pink-900/20 dark:text-pink-400',
};

export default function PixelsIndex({ pixels, providers }: { pixels: (Pixel & { created_at: string })[]; providers: ProviderOption[] }) {
  const { project, flash } = usePage<PageProps>().props;

  const providerLabel = (value: string) => providers.find((p) => p.value === value)?.label ?? value;

  function destroy(pixel: Pixel) {
    if (!confirm(`Delete pixel "${pixel.name}"? This cannot be undone.`)) return;
    router.delete(route('app.project.pixels.destroy', { project: project!.id, pixel: pixel.id }));
  }

  return (
    <AppLayout title="Pixels">
      <div className="px-8 py-8">
        {/* Header */}
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Pixels</h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage tracking pixels attached to your short links</p>
          </div>
          <Link
            href={route('app.project.pixels.create', { project: project!.id })}
            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
          >
            <Plus className="h-4 w-4" />
            Add pixel
          </Link>
        </div>

        {/* Flash */}
        {flash?.success && <div className="mb-4 rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>}

        {/* Table */}
        {pixels.length === 0 ? (
          <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-16 dark:border-slate-700 dark:bg-slate-900">
            <Zap className="mb-3 h-10 w-10 text-slate-300 dark:text-slate-600" />
            <p className="text-sm font-medium text-slate-500 dark:text-slate-400">No pixels yet</p>
            <p className="mt-1 text-xs text-slate-400 dark:text-slate-500">Add a tracking pixel and attach it to your links.</p>
            <Link
              href={route('app.project.pixels.create', { project: project!.id })}
              className="mt-4 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
            >
              <Plus className="h-3.5 w-3.5" />
              Add pixel
            </Link>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-800">
                  <th className="px-4 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Name</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Provider</th>
                  <th className="px-4 py-3 text-left text-xs font-semibold tracking-wider text-slate-500 uppercase dark:text-slate-400">Tag / ID</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {pixels.map((pixel) => (
                  <tr key={pixel.id} className="group">
                    <td className="px-4 py-3 font-medium text-slate-900 dark:text-white">{pixel.name}</td>
                    <td className="px-4 py-3">
                      <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${PROVIDER_COLORS[pixel.provider] ?? 'bg-slate-100 text-slate-600'}`}>
                        {providerLabel(pixel.provider)}
                      </span>
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{pixel.tag}</td>
                    <td className="px-4 py-3">
                      <div className="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                        <Link
                          href={route('app.project.pixels.edit', { project: project!.id, pixel: pixel.id })}
                          className="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                          <Pencil className="h-4 w-4" />
                        </Link>
                        <button
                          onClick={() => destroy(pixel)}
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
