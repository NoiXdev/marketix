import ActivityHistory from '@/Components/ActivityHistory';
import AppLayout from '@/Layouts/AppLayout';
import { ActivityEntry, PageProps, Pixel } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';

interface ProviderOption {
  value: string;
  label: string;
}

const inputCls =
  'mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white';

export default function PixelsEdit({
  pixel,
  providers,
  history,
}: {
  pixel: Pick<Pixel, 'id' | 'provider' | 'name' | 'tag'>;
  providers: ProviderOption[];
  history?: ActivityEntry[];
}) {
  const { project } = usePage<PageProps>().props;

  const { data, setData, put, processing, errors } = useForm({
    provider: pixel.provider,
    name: pixel.name,
    tag: pixel.tag,
  });

  return (
    <AppLayout title="Edit pixel">
      <div className="px-8 py-8">
        <div className="mb-6">
          <Link
            href={route('app.project.pixels.index', { project: project!.id })}
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to pixels
          </Link>
          <h1 className="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
            Edit <span className="text-indigo-600 dark:text-indigo-400">{pixel.name}</span>
          </h1>
        </div>

        <div className="max-w-lg space-y-4">
          <form
            onSubmit={(e) => {
              e.preventDefault();
              put(route('app.project.pixels.update', { project: project!.id, pixel: pixel.id }));
            }}
            className="space-y-5"
          >
            <div className="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
              <h2 className="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
                Pixel settings
              </h2>
              <div className="space-y-4">

                <div>
                  <label htmlFor="provider" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Provider <span className="text-red-500">*</span>
                  </label>
                  <select
                    id="provider"
                    value={data.provider}
                    onChange={(e) => setData('provider', e.target.value)}
                    className={inputCls}
                  >
                    {providers.map((p) => (
                      <option key={p.value} value={p.value}>{p.label}</option>
                    ))}
                  </select>
                  {errors.provider && (
                    <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.provider}</p>
                  )}
                </div>

                <div>
                  <label htmlFor="name" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Name <span className="text-red-500">*</span>
                  </label>
                  <input
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="e.g. Main Facebook Pixel"
                    className={inputCls}
                  />
                  {errors.name && (
                    <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.name}</p>
                  )}
                </div>

                <div>
                  <label htmlFor="tag" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                    Tag / ID <span className="text-red-500">*</span>
                  </label>
                  <input
                    id="tag"
                    type="text"
                    value={data.tag}
                    onChange={(e) => setData('tag', e.target.value)}
                    placeholder="Enter your pixel ID or tag"
                    className={inputCls}
                  />
                  {errors.tag && (
                    <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.tag}</p>
                  )}
                </div>

              </div>
            </div>

            <div className="flex items-center gap-3">
              <button
                type="submit"
                disabled={processing}
                className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
              >
                {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                Save changes
              </button>
              <Link
                href={route('app.project.pixels.index', { project: project!.id })}
                className="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
              >
                Cancel
              </Link>
            </div>
          </form>
          <ActivityHistory history={history} />
        </div>
      </div>
    </AppLayout>
  );
}
