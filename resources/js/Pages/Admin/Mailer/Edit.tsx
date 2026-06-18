import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

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

const inputClass =
  'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';
const labelClass = 'mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300';

export default function AdminMailerEdit({ settings, has_postal_key, has_smtp_password }: Props) {
  const { flash } = usePage<PageProps>().props;
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

  const testForm = useForm({ test_email: '' });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.admin.mailer.update'));
  }

  function sendTest(e: React.FormEvent) {
    e.preventDefault();
    testForm.post(route('app.admin.mailer.test'), { preserveScroll: true });
  }

  return (
    <AdminLayout title="Mailer">
      <div className="px-8 py-8">
        <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Mailer settings</h1>

        {flash?.success && (
          <div className="mb-4 max-w-md rounded-md bg-green-50 px-4 py-3 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{flash.success}</div>
        )}
        {flash?.error && (
          <div className="mb-4 max-w-md rounded-md bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">{flash.error}</div>
        )}

        <form onSubmit={submit} className="max-w-md space-y-4">
          <div>
            <label className={labelClass}>Active mailer</label>
            <select
              value={data.default_mailer}
              onChange={(e) => setData('default_mailer', e.target.value)}
              className={inputClass}
            >
              <option value="postal">Postal</option>
              <option value="smtp">SMTP</option>
              <option value="log">Log (no delivery)</option>
            </select>
            {errors.default_mailer && <p className="mt-1 text-xs text-red-600">{errors.default_mailer}</p>}
          </div>

          <div>
            <label className={labelClass}>From address</label>
            <input
              type="email"
              value={data.from_address}
              onChange={(e) => setData('from_address', e.target.value)}
              className={inputClass}
            />
            {errors.from_address && <p className="mt-1 text-xs text-red-600">{errors.from_address}</p>}
          </div>

          <div>
            <label className={labelClass}>From name</label>
            <input
              value={data.from_name}
              onChange={(e) => setData('from_name', e.target.value)}
              className={inputClass}
            />
            {errors.from_name && <p className="mt-1 text-xs text-red-600">{errors.from_name}</p>}
          </div>

          {data.default_mailer === 'postal' && (
            <fieldset className="space-y-4 rounded-md border border-slate-200 p-4 dark:border-slate-700">
              <legend className="px-1 text-sm font-semibold text-slate-700 dark:text-slate-300">Postal</legend>
              <div>
                <label className={labelClass}>Postal server URL</label>
                <input
                  value={data.postal_url}
                  onChange={(e) => setData('postal_url', e.target.value)}
                  className={inputClass}
                />
                {errors.postal_url && <p className="mt-1 text-xs text-red-600">{errors.postal_url}</p>}
              </div>
              <div>
                <label className={labelClass}>API key {has_postal_key && '(leave blank to keep current)'}</label>
                <input
                  type="password"
                  placeholder={has_postal_key ? '•••••••• set' : ''}
                  value={data.postal_key}
                  onChange={(e) => setData('postal_key', e.target.value)}
                  className={inputClass}
                />
                {errors.postal_key && <p className="mt-1 text-xs text-red-600">{errors.postal_key}</p>}
              </div>
            </fieldset>
          )}

          {data.default_mailer === 'smtp' && (
            <fieldset className="space-y-4 rounded-md border border-slate-200 p-4 dark:border-slate-700">
              <legend className="px-1 text-sm font-semibold text-slate-700 dark:text-slate-300">SMTP</legend>
              <div>
                <label className={labelClass}>Host</label>
                <input value={data.smtp_host} onChange={(e) => setData('smtp_host', e.target.value)} className={inputClass} />
                {errors.smtp_host && <p className="mt-1 text-xs text-red-600">{errors.smtp_host}</p>}
              </div>
              <div>
                <label className={labelClass}>Port</label>
                <input
                  type="number"
                  value={data.smtp_port}
                  onChange={(e) => setData('smtp_port', Number(e.target.value))}
                  className={inputClass}
                />
                {errors.smtp_port && <p className="mt-1 text-xs text-red-600">{errors.smtp_port}</p>}
              </div>
              <div>
                <label className={labelClass}>Username</label>
                <input value={data.smtp_username} onChange={(e) => setData('smtp_username', e.target.value)} className={inputClass} />
                {errors.smtp_username && <p className="mt-1 text-xs text-red-600">{errors.smtp_username}</p>}
              </div>
              <div>
                <label className={labelClass}>Password {has_smtp_password && '(leave blank to keep current)'}</label>
                <input
                  type="password"
                  placeholder={has_smtp_password ? '•••••••• set' : ''}
                  value={data.smtp_password}
                  onChange={(e) => setData('smtp_password', e.target.value)}
                  className={inputClass}
                />
                {errors.smtp_password && <p className="mt-1 text-xs text-red-600">{errors.smtp_password}</p>}
              </div>
              <div>
                <label className={labelClass}>Encryption scheme (e.g. tls)</label>
                <input value={data.smtp_scheme} onChange={(e) => setData('smtp_scheme', e.target.value)} className={inputClass} />
                {errors.smtp_scheme && <p className="mt-1 text-xs text-red-600">{errors.smtp_scheme}</p>}
              </div>
            </fieldset>
          )}

          <button
            disabled={processing}
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
          >
            Save
          </button>
        </form>

        <form onSubmit={sendTest} className="mt-8 max-w-md space-y-3 border-t border-slate-200 pt-6 dark:border-slate-700">
          <h2 className="text-lg font-semibold text-slate-900 dark:text-white">Send test email</h2>
          <div>
            <label className={labelClass}>Recipient (defaults to your address)</label>
            <input
              type="email"
              value={testForm.data.test_email}
              onChange={(e) => testForm.setData('test_email', e.target.value)}
              className={inputClass}
            />
            {testForm.errors.test_email && <p className="mt-1 text-xs text-red-600">{testForm.errors.test_email}</p>}
          </div>
          <button
            disabled={testForm.processing}
            className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            Send test
          </button>
        </form>
      </div>
    </AdminLayout>
  );
}
