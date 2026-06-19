import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { useForm } from '@inertiajs/react';

interface Props extends PageProps {
    app_name: string | null;
    logo_light_url: string | null;
    logo_dark_url: string | null;
    logo_email_url: string | null;
    favicon_url: string | null;
}

export default function BrandingEdit({ app_name, logo_light_url, logo_dark_url, logo_email_url, favicon_url }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        app_name: app_name ?? '',
        logo_light: null as File | null,
        logo_dark: null as File | null,
        logo_email: null as File | null,
        favicon: null as File | null,
        remove_logo_light: false,
        remove_logo_dark: false,
        remove_logo_email: false,
        remove_favicon: false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(route('app.admin.branding.update'));
    }

    return (
        <AdminLayout title="Branding">
            <form onSubmit={submit}>
                <div>
                    <label>App Name</label>
                    <input
                        type="text"
                        value={data.app_name}
                        onChange={(e) => setData('app_name', e.target.value)}
                    />
                    {errors.app_name && <p>{errors.app_name}</p>}
                </div>
                <button type="submit" disabled={processing}>Save</button>
            </form>
        </AdminLayout>
    );
}
