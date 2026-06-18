import ProfileLayout from '@/Layouts/ProfileLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

interface ProfileUser {
  name: string;
  email: string;
}

export default function ProfileEdit({ user }: { user: ProfileUser }) {
  const { flash } = usePage<PageProps>().props;
  const { data, setData, put, processing, errors, reset } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put(route('app.profile.update'), {
      preserveScroll: true,
      onSuccess: () => reset('current_password', 'password', 'password_confirmation'),
    });
  }

  const inputClass =
    'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  return (
    <ProfileLayout title="Profile">
      <h1 className="mb-6 text-2xl font-bold text-slate-900 dark:text-white">Profile</h1>

      {flash?.success && (
        <div className="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300">
          {flash.success}
        </div>
      )}

      <div className="mb-8 space-y-3 rounded-md border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <div>
          <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Name</p>
          <p className="text-sm text-slate-900 dark:text-white">{user.name}</p>
        </div>
        <div>
          <p className="text-xs font-medium text-slate-500 dark:text-slate-400">Email</p>
          <p className="text-sm text-slate-900 dark:text-white">{user.email}</p>
        </div>
      </div>

      <form onSubmit={submit} className="space-y-4">
        <h2 className="text-sm font-semibold text-slate-900 dark:text-white">Change password</h2>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Current password</label>
          <input
            type="password"
            value={data.current_password}
            onChange={(e) => setData('current_password', e.target.value)}
            className={inputClass}
          />
          {errors.current_password && <p className="mt-1 text-xs text-red-600">{errors.current_password}</p>}
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">New password</label>
          <input
            type="password"
            value={data.password}
            onChange={(e) => setData('password', e.target.value)}
            className={inputClass}
          />
          {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm new password</label>
          <input
            type="password"
            value={data.password_confirmation}
            onChange={(e) => setData('password_confirmation', e.target.value)}
            className={inputClass}
          />
        </div>
        <button
          disabled={processing}
          className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
        >
          Save
        </button>
      </form>
    </ProfileLayout>
  );
}
