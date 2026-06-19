import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { router, useForm, usePage } from '@inertiajs/react';

interface StorageSettings {
  driver: string;
  s3_key: string;
  s3_region: string;
  s3_bucket: string;
  s3_endpoint: string;
  s3_use_path_style: boolean;
}

interface Props {
  settings: StorageSettings;
  has_s3_secret: boolean;
}

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const labelClass = 'mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300';

export default function AdminStorageEdit({ settings, has_s3_secret }: Props) {
  const { flash } = usePage<PageProps>().props;
  const { data, setData, put, processing, errors } = useForm({
    driver: settings.driver,
    s3_key: settings.s3_key,
    s3_secret: '',
    s3_region: settings.s3_region,
    s3_bucket: settings.s3_bucket,
    s3_endpoint: settings.s3_endpoint,
    s3_use_path_style: settings.s3_use_path_style,
  });

  const driverChanged = data.driver !== settings.driver;

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.storage.update'));
  }

  function testConnection() {
    // Reuse the current form values; post them to the test endpoint.
    router.post(route('app.admin.storage.test'), { ...data }, { preserveScroll: true });
  }

  return (
    <AdminLayout title="Storage">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Storage settings</h1>

        {flash?.success && (
          <div className="mb-4 max-w-md rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>
        )}
        {flash?.error && (
          <div className="mb-4 max-w-md rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">{flash.error}</div>
        )}

        <form onSubmit={submit} className="max-w-md space-y-4">
          <div>
            <label htmlFor="driver" className={labelClass}>Storage backend</label>
            <select
              id="driver"
              value={data.driver}
              onChange={(e) => setData('driver', e.target.value)}
              className={inputClass}
            >
              <option value="local">Local disk</option>
              <option value="s3">S3-compatible</option>
            </select>
            {errors.driver && <p className="mt-1 text-xs text-red-600">{errors.driver}</p>}
          </div>

          {driverChanged && (
            <div className="max-w-md rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
              Existing files (logos, favicons) stay on the previous disk and may need re-uploading. New uploads will use the selected disk.
            </div>
          )}

          {data.driver === 's3' && (
            <fieldset className="space-y-4 rounded-md border border-slate-200 p-4 dark:border-slate-700">
              <legend className="px-1 text-sm font-semibold text-slate-700 dark:text-slate-300">S3-compatible</legend>
              <div>
                <label htmlFor="s3_key" className={labelClass}>Access key ID</label>
                <input id="s3_key" value={data.s3_key} onChange={(e) => setData('s3_key', e.target.value)} className={inputClass} />
                {errors.s3_key && <p className="mt-1 text-xs text-red-600">{errors.s3_key}</p>}
              </div>
              <div>
                <label htmlFor="s3_secret" className={labelClass}>Secret access key {has_s3_secret && '(leave blank to keep current)'}</label>
                <input
                  id="s3_secret"
                  type="password"
                  placeholder={has_s3_secret ? '•••••••• set' : ''}
                  value={data.s3_secret}
                  onChange={(e) => setData('s3_secret', e.target.value)}
                  className={inputClass}
                />
                {errors.s3_secret && <p className="mt-1 text-xs text-red-600">{errors.s3_secret}</p>}
              </div>
              <div>
                <label htmlFor="s3_region" className={labelClass}>Region</label>
                <input id="s3_region" value={data.s3_region} onChange={(e) => setData('s3_region', e.target.value)} className={inputClass} />
                {errors.s3_region && <p className="mt-1 text-xs text-red-600">{errors.s3_region}</p>}
              </div>
              <div>
                <label htmlFor="s3_bucket" className={labelClass}>Bucket</label>
                <input id="s3_bucket" value={data.s3_bucket} onChange={(e) => setData('s3_bucket', e.target.value)} className={inputClass} />
                {errors.s3_bucket && <p className="mt-1 text-xs text-red-600">{errors.s3_bucket}</p>}
              </div>
              <div>
                <label htmlFor="s3_endpoint" className={labelClass}>Endpoint</label>
                <input
                  id="s3_endpoint"
                  value={data.s3_endpoint}
                  onChange={(e) => setData('s3_endpoint', e.target.value)}
                  className={inputClass}
                  placeholder="https://..."
                />
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                  Leave blank for AWS; set for Cloudflare R2, MinIO, DigitalOcean Spaces, Hetzner.
                </p>
                {errors.s3_endpoint && <p className="mt-1 text-xs text-red-600">{errors.s3_endpoint}</p>}
              </div>
              <div className="flex items-center gap-2">
                <input
                  id="s3_use_path_style"
                  type="checkbox"
                  checked={data.s3_use_path_style}
                  onChange={(e) => setData('s3_use_path_style', e.target.checked)}
                  className="h-4 w-4 rounded border-slate-300"
                />
                <label htmlFor="s3_use_path_style" className="text-sm text-slate-700 dark:text-slate-300">
                  Use path-style endpoint
                </label>
              </div>
            </fieldset>
          )}

          <div className="flex items-center gap-3">
            <button
              disabled={processing}
              className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
            >
              Save
            </button>
            <button
              type="button"
              onClick={testConnection}
              className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
            >
              Test connection
            </button>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
}
