import AdminLayout from '@/Layouts/AdminLayout';
import { useForm } from '@inertiajs/react';

interface MailerSettings {
  default_mailer: string;
  from_address: string;
  from_name: string;
  postal_url: string;
  smtp_host: string;
  smtp_port: number;
  smtp_username: string;
  smtp_scheme: string;
}

interface Props {
  settings: MailerSettings;
  has_postal_key: boolean;
  has_smtp_password: boolean;
}

export default function AdminMailerEdit({ settings, has_postal_key, has_smtp_password }: Props) {
  const { data, setData, put, processing, errors } = useForm({
    default_mailer: settings.default_mailer,
    from_address: settings.from_address,
    from_name: settings.from_name,
    postal_url: settings.postal_url,
    postal_key: '',
    smtp_host: settings.smtp_host,
    smtp_port: settings.smtp_port,
    smtp_username: settings.smtp_username,
    smtp_password: '',
    smtp_scheme: settings.smtp_scheme,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.mailer.update'));
  }

  const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  return (
    <AdminLayout title="Mailer settings">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Mailer settings</h1>
        <form onSubmit={submit} className="max-w-lg space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Default mailer</label>
            <select value={data.default_mailer} onChange={(e) => setData('default_mailer', e.target.value)} className={inputClass}>
              <option value="postal">Postal</option>
              <option value="smtp">SMTP</option>
              <option value="log">Log</option>
            </select>
            {errors.default_mailer && <p className="mt-1 text-xs text-red-600">{errors.default_mailer}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">From address</label>
            <input type="email" value={data.from_address} onChange={(e) => setData('from_address', e.target.value)} className={inputClass} />
            {errors.from_address && <p className="mt-1 text-xs text-red-600">{errors.from_address}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">From name</label>
            <input value={data.from_name} onChange={(e) => setData('from_name', e.target.value)} className={inputClass} />
            {errors.from_name && <p className="mt-1 text-xs text-red-600">{errors.from_name}</p>}
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Postal URL</label>
            <input value={data.postal_url} onChange={(e) => setData('postal_url', e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Postal key {has_postal_key && <span className="text-green-600">(set)</span>}
            </label>
            <input
              type="password"
              value={data.postal_key}
              onChange={(e) => setData('postal_key', e.target.value)}
              placeholder={has_postal_key ? 'Leave blank to keep existing' : ''}
              className={inputClass}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP host</label>
            <input value={data.smtp_host} onChange={(e) => setData('smtp_host', e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP port</label>
            <input type="number" value={data.smtp_port} onChange={(e) => setData('smtp_port', Number(e.target.value))} className={inputClass} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP username</label>
            <input value={data.smtp_username} onChange={(e) => setData('smtp_username', e.target.value)} className={inputClass} />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              SMTP password {has_smtp_password && <span className="text-green-600">(set)</span>}
            </label>
            <input
              type="password"
              value={data.smtp_password}
              onChange={(e) => setData('smtp_password', e.target.value)}
              placeholder={has_smtp_password ? 'Leave blank to keep existing' : ''}
              className={inputClass}
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">SMTP scheme</label>
            <input value={data.smtp_scheme} onChange={(e) => setData('smtp_scheme', e.target.value)} className={inputClass} />
          </div>
          <button disabled={processing} className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
            Save
          </button>
        </form>
      </div>
    </AdminLayout>
  );
}
