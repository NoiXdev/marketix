import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

interface Props {
  app_name: string | null;
  logo_light_url: string | null;
  logo_dark_url: string | null;
  logo_email_url: string | null;
  favicon_url: string | null;
}

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const labelClass = 'mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300';

type ImageField = 'logo_light' | 'logo_dark' | 'logo_email' | 'favicon';

export default function AdminBrandingEdit(props: Props) {
  const { flash } = usePage<PageProps>().props;

  const currentUrl: Record<ImageField, string | null> = {
    logo_light: props.logo_light_url,
    logo_dark: props.logo_dark_url,
    logo_email: props.logo_email_url,
    favicon: props.favicon_url,
  };

  const { data, setData, post, processing, errors } = useForm<{
    app_name: string;
    logo_light: File | null;
    logo_dark: File | null;
    logo_email: File | null;
    favicon: File | null;
    remove_logo_light: boolean;
    remove_logo_dark: boolean;
    remove_logo_email: boolean;
    remove_favicon: boolean;
  }>({
    app_name: props.app_name ?? '',
    logo_light: null,
    logo_dark: null,
    logo_email: null,
    favicon: null,
    remove_logo_light: false,
    remove_logo_dark: false,
    remove_logo_email: false,
    remove_favicon: false,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.admin.branding.update'), { forceFormData: true });
  }

  const imageFields: { field: ImageField; remove: keyof typeof data; label: string; hint: string }[] = [
    { field: 'logo_light', remove: 'remove_logo_light', label: 'Logo (light mode)', hint: 'Shown on light backgrounds.' },
    { field: 'logo_dark', remove: 'remove_logo_dark', label: 'Logo (dark mode)', hint: 'Shown on dark backgrounds.' },
    { field: 'logo_email', remove: 'remove_logo_email', label: 'Email / PDF logo', hint: 'Used in emails and PDF reports.' },
    { field: 'favicon', remove: 'remove_favicon', label: 'Favicon', hint: '.ico, .png or .svg.' },
  ];

  return (
    <AdminLayout title="Branding">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Branding</h1>

        {flash?.success && (
          <div className="mb-4 max-w-md rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>
        )}
        {flash?.error && (
          <div className="mb-4 max-w-md rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">{flash.error}</div>
        )}

        <form onSubmit={submit} className="max-w-md space-y-6">
          <div>
            <label className={labelClass}>Application name</label>
            <input
              value={data.app_name}
              onChange={(e) => setData('app_name', e.target.value)}
              placeholder="Marketix"
              className={inputClass}
            />
            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">Leave blank to use the default ("Marketix").</p>
            {errors.app_name && <p className="mt-1 text-xs text-red-600">{errors.app_name}</p>}
          </div>

          {imageFields.map(({ field, remove, label, hint }) => (
            <div key={field}>
              <label className={labelClass}>{label}</label>
              {currentUrl[field] && (
                <img src={currentUrl[field]!} alt={label} className="mb-2 h-10 w-auto rounded border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800" />
              )}
              <input
                type="file"
                accept={field === 'favicon' ? '.ico,.png,.svg,image/*' : 'image/*'}
                onChange={(e) => setData(field, e.target.files?.[0] ?? null)}
                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-indigo-700 dark:text-slate-400 dark:file:bg-indigo-900/30 dark:file:text-indigo-300"
              />
              <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
              {currentUrl[field] && (
                <label className="mt-1 flex items-center gap-2 text-xs text-slate-600 dark:text-slate-400">
                  <input
                    type="checkbox"
                    checked={Boolean(data[remove])}
                    onChange={(e) => setData(remove, e.target.checked)}
                  />
                  Remove current
                </label>
              )}
              {errors[field] && <p className="mt-1 text-xs text-red-600">{errors[field]}</p>}
            </div>
          ))}

          <button
            disabled={processing}
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            Save
          </button>
        </form>
      </div>
    </AdminLayout>
  );
}
