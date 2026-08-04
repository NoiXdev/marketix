import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type Option = { value: string; label: string };

export default function SitesCreate({ trackingModes, consentModes }: { trackingModes: Option[]; consentModes: Option[] }) {
  const { project } = usePage<PageProps>().props;
  const { data, setData, post, processing, errors, transform } = useForm({
    name: '',
    domain: '',
    tracking_mode: 'cookieless',
    consent_mode: 'immediate',
    consent_signal: '',
    respect_dnt: false,
    retention_days: '' as string | number,
  });

  transform((data) => ({
    ...data,
    retention_days: data.retention_days === '' ? null : data.retention_days,
  }));

  function submit(e: FormEvent) {
    e.preventDefault();
    post(route('app.project.sites.store', { project: project!.id }));
  }

  const inputClass =
    'mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100';

  return (
    <AppLayout title="Add site">
      <h1 className="mb-6 text-xl font-semibold text-gray-900 dark:text-gray-100">Add site</h1>
      <form onSubmit={submit} className="max-w-lg space-y-4">
        <div>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
          <input className={inputClass} value={data.name} onChange={(e) => setData('name', e.target.value)} />
          {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
        </div>
        <div>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
          <input className={inputClass} value={data.domain} onChange={(e) => setData('domain', e.target.value)} placeholder="example.com" />
          {errors.domain && <p className="mt-1 text-sm text-red-600">{errors.domain}</p>}
        </div>
        <div>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Tracking mode</label>
          <select className={inputClass} value={data.tracking_mode} onChange={(e) => setData('tracking_mode', e.target.value)}>
            {trackingModes.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Consent mode</label>
          <select className={inputClass} value={data.consent_mode} onChange={(e) => setData('consent_mode', e.target.value)}>
            {consentModes.map((o) => (
              <option key={o.value} value={o.value}>{o.label}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Consent signal (optional)</label>
          <input className={inputClass} value={data.consent_signal} onChange={(e) => setData('consent_signal', e.target.value)} placeholder="e.g. UC_UI (Usercentrics)" />
          <p className="mt-1 text-xs text-gray-500">
            Set window.&lt;name&gt; = true when consent is granted and dispatch a &apos;marketix:consent&apos; event on change.
          </p>
        </div>
        <div>
          <label className="text-sm font-medium text-gray-700 dark:text-gray-300">Retention (days, optional)</label>
          <input
            type="number"
            min={1}
            className={inputClass}
            value={data.retention_days}
            onChange={(e) => setData('retention_days', e.target.value)}
          />
          <p className="mt-1 text-xs text-gray-500">Days to retain raw analytics (blank = project default 24 months).</p>
          {errors.retention_days && <p className="mt-1 text-sm text-red-600">{errors.retention_days}</p>}
        </div>
        <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
          <input type="checkbox" checked={data.respect_dnt} onChange={(e) => setData('respect_dnt', e.target.checked)} />
          Respect Do-Not-Track header
        </label>
        <button disabled={processing} className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-50">
          Create site
        </button>
      </form>
    </AppLayout>
  );
}
