import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

interface Option {
    value: string;
    label: string;
}

interface Props {
    frequency: string;
    options: Option[];
}

export default function NotificationSettings({ frequency, options }: Props) {
    const { flash, project } = usePage<PageProps>().props;
    const { data, setData, put, processing } = useForm({ frequency });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(route('app.project.settings.notifications.update', { project: project?.id }), {
            preserveScroll: true,
        });
    }

    const inputClass =
        'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white';

    return (
        <AppLayout title="Email reports">
            <div className="px-8 py-8">
                <div className="mx-auto max-w-xl">
                    <h1 className="mb-2 text-2xl font-bold text-slate-900 dark:text-white">Email reports</h1>
                    <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">
                        Get a statistics summary for this project by email. Reports are sent at 08:00 and cover the
                        previous full day, week, or month.
                    </p>

                    {flash?.success && (
                        <div className="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-300">
                            {flash.success}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="space-y-4 rounded-md border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Frequency
                            </label>
                            <select
                                value={data.frequency}
                                onChange={(e) => setData('frequency', e.target.value)}
                                className={inputClass}
                            >
                                {options.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <button
                            disabled={processing}
                            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            Save
                        </button>
                    </form>
                </div>
            </div>
        </AppLayout>
    );
}
