import GuestLayout from '@/Layouts/GuestLayout';
import { Link, useForm } from '@inertiajs/react';

interface Props {
  state: 'valid' | 'invalid' | 'wrong_user';
  token?: string;
  email?: string;
  projectName?: string;
  needsAccount?: boolean;
  authenticated?: boolean;
}

export default function AcceptInvitation({ state, token, email, projectName, needsAccount, authenticated }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    password: '',
    password_confirmation: '',
  });

  const inputClass = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('app.invitations.accept', { token }));
  }

  if (state === 'invalid') {
    return (
      <GuestLayout title="Invitation invalid" description="This invitation link is invalid or has expired.">
        <Link href={route('app.auth.show-login')} className="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Go to login</Link>
      </GuestLayout>
    );
  }

  if (state === 'wrong_user') {
    return (
      <GuestLayout title="Wrong account" description={`This invitation is for ${email}. Log out and sign in with that email to accept it.`}>
        <Link href={route('app.auth.show-login')} className="text-sm font-semibold text-indigo-600 hover:text-indigo-500">Go to login</Link>
      </GuestLayout>
    );
  }

  // Existing user, not logged in → prompt login first.
  if (!needsAccount && !authenticated) {
    return (
      <GuestLayout title="Accept invitation" description={`You've been invited to ${projectName}. Log in as ${email} to accept.`}>
        <Link href={route('app.auth.show-login')} className="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Log in to accept</Link>
      </GuestLayout>
    );
  }

  // Logged-in existing user → one-click confirm.
  if (!needsAccount && authenticated) {
    return (
      <GuestLayout title="Accept invitation" description={`Join ${projectName} as ${email}.`}>
        <form onSubmit={submit}>
          <button disabled={processing} className="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Accept invitation</button>
        </form>
      </GuestLayout>
    );
  }

  // New user → set name + password.
  return (
    <GuestLayout title="Accept invitation" description={`Create your account to join ${projectName}.`}>
      <form onSubmit={submit} className="space-y-4">
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
          <input value={email} disabled className={`${inputClass} opacity-60`} />
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
          <input value={data.name} onChange={(e) => setData('name', e.target.value)} className={inputClass} />
          {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Password</label>
          <input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className={inputClass} />
          {errors.password && <p className="mt-1 text-xs text-red-600">{errors.password}</p>}
        </div>
        <div>
          <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Confirm password</label>
          <input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} className={inputClass} />
        </div>
        <button disabled={processing} className="w-full rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">Create account & join</button>
      </form>
    </GuestLayout>
  );
}
