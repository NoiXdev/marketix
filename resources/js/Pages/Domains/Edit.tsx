import AppLayout from '@/Layouts/AppLayout';
import { Domain, PageProps } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Loader2 } from 'lucide-react';
import { FormEventHandler } from 'react';

export default function DomainsEdit({ domain }: { domain: Domain }) {
  const { project } = usePage<PageProps>().props;

  const { data, setData, put, processing, errors } = useForm({
    name: domain.name,
    redirect_root: domain.redirect_root ?? '',
    redirect_not_found: domain.redirect_not_found ?? '',
  });

  const submit: FormEventHandler = (e) => {
    e.preventDefault();
    put(route('app.project.domains.update', { project: project!.id, domain: domain.id }));
  };

  return (
    <AppLayout title="Edit domain">
      <div className="px-8 py-8">
        <div className="mb-6">
          <Link
            href={route('app.project.domains.index', { project: project!.id })}
            className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
          >
            <ArrowLeft className="h-4 w-4" />
            Back to domains
          </Link>
          <h1 className="mt-3 text-2xl font-bold text-slate-900 dark:text-white">
            Edit <span className="text-indigo-600 dark:text-indigo-400">{domain.name}</span>
          </h1>
        </div>

        <div className="max-w-lg rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
          <form onSubmit={submit} className="space-y-5">
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Domain name <span className="text-red-500">*</span>
              </label>
              <input
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                placeholder="links.example.com"
                className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              />
              {errors.name && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.name}</p>}
            </div>

            <div>
              <label htmlFor="redirect_root" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                Root redirect
              </label>
              <p className="text-xs text-slate-400 dark:text-slate-500">Where to redirect visitors who hit the bare domain with no slug.</p>
              <input
                id="redirect_root"
                type="url"
                value={data.redirect_root}
                onChange={(e) => setData('redirect_root', e.target.value)}
                placeholder="https://example.com"
                className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              />
              {errors.redirect_root && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.redirect_root}</p>}
            </div>

            <div>
              <label htmlFor="redirect_not_found" className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                404 redirect
              </label>
              <p className="text-xs text-slate-400 dark:text-slate-500">Where to redirect visitors when a slug is not found.</p>
              <input
                id="redirect_not_found"
                type="url"
                value={data.redirect_not_found}
                onChange={(e) => setData('redirect_not_found', e.target.value)}
                placeholder="https://example.com/404"
                className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white"
              />
              {errors.redirect_not_found && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{errors.redirect_not_found}</p>}
            </div>

            <div className="flex items-center gap-3 pt-2">
              <button
                type="submit"
                disabled={processing}
                className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-60"
              >
                {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                Save changes
              </button>
              <Link
                href={route('app.project.domains.index', { project: project!.id })}
                className="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
              >
                Cancel
              </Link>
            </div>
          </form>
        </div>
      </div>
    </AppLayout>
  );
}
