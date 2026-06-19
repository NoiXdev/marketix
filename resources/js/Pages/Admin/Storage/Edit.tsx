import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';

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

export default function Edit({ settings, has_s3_secret }: PageProps & Props) {
    return (
        <AdminLayout title="Storage Settings">
            <div>
                <p>Storage settings page (placeholder — Task 4 will implement full UI)</p>
            </div>
        </AdminLayout>
    );
}
