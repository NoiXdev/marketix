<?php

return [
    'nav' => [
        'back_to_app' => 'Back to app',
        'users' => 'Users',
        'projects' => 'Projects',
        'mailer' => 'Mailer',
        'branding' => 'Branding',
        'storage' => 'Storage',
        'activity' => 'Activity',
        'horizon' => 'Horizon',
        'documentation' => 'Documentation',
    ],
    'common' => [
        'leave_blank_to_keep' => '(leave blank to keep current)',
        'secret_set' => '•••••••• set',
        'role' => 'Role',
    ],
    'activity' => [
        'title' => 'Activity log',
        'filters' => [
            'all_types' => 'All types',
            'all_projects' => 'All projects',
            'causer_placeholder' => 'Causer name/email',
        ],
    ],
    'projects' => [
        'title' => 'Projects',
        'add' => 'Add project',
        'search_placeholder' => 'Search projects…',
        'columns' => [
            'name' => 'Name',
            'members' => 'Members',
        ],
        'actions' => [
            'open' => 'Open project',
        ],
        'fields' => [
            'name' => 'Name',
            'locked' => 'Locked',
        ],
        'create' => [
            'title' => 'Add project',
        ],
        'edit' => [
            'title' => 'Edit project',
        ],
        'members' => [
            'title' => 'Members',
            'empty' => 'No members yet.',
            'assign_label' => 'Assign user',
            'select_user_placeholder' => 'Select a user…',
            'assign_button' => 'Assign',
            'remove_confirm' => [
                'title' => 'Remove member?',
                'text' => 'Remove :name from this project?',
                'confirm' => 'Remove',
            ],
        ],
        'delete_confirm' => [
            'title' => 'Delete project?',
            'text' => 'Delete ":name"?',
        ],
    ],
    'users' => [
        'title' => 'Users',
        'add' => 'Add user',
        'search_placeholder' => 'Search name or email…',
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'projects' => 'Projects',
        ],
        'fields' => [
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'new_password_hint' => 'New password (leave blank to keep)',
            'super_admin' => 'Super admin',
            'force_password_change' => 'Force password change on next login',
        ],
        'sections' => [
            'account' => 'Account',
            'security' => 'Security actions',
        ],
        'security' => [
            'reset_description' => 'Email this user a link to reset their password.',
            'send_reset' => 'Send password reset',
        ],
        'memberships' => [
            'title' => 'Project memberships',
            'empty' => 'Not a member of any project yet.',
            'add_label' => 'Add to project',
            'select_project_placeholder' => 'Select a project…',
            'remove_confirm' => [
                'title' => 'Remove from project?',
                'text' => 'Remove :name from :project?',
                'confirm' => 'Remove',
            ],
        ],
        'create' => [
            'title' => 'Add user',
        ],
        'edit' => [
            'title' => 'Edit user',
        ],
        'delete_confirm' => [
            'title' => 'Delete user?',
            'text' => 'Delete ":name"? This cannot be undone.',
        ],
    ],
    'branding' => [
        'title' => 'Branding',
        'fields' => [
            'app_name' => 'Application name',
            'app_name_hint' => 'Leave blank to use the default ("Marketix").',
            'logo_light' => 'Logo (light mode)',
            'logo_light_hint' => 'Shown on light backgrounds.',
            'logo_dark' => 'Logo (dark mode)',
            'logo_dark_hint' => 'Shown on dark backgrounds.',
            'logo_email' => 'Email / PDF logo',
            'logo_email_hint' => 'Used in emails and PDF reports.',
            'favicon' => 'Favicon',
            'favicon_hint' => '.ico, .png, .jpg.',
        ],
        'remove_current' => 'Remove current',
    ],
    'mailer' => [
        'title' => 'Mailer settings',
        'fields' => [
            'default_mailer' => 'Active mailer',
            'from_address' => 'From address',
            'from_name' => 'From name',
            'postal_url' => 'Postal server URL',
            'postal_key' => 'API key',
            'smtp_host' => 'Host',
            'smtp_port' => 'Port',
            'smtp_username' => 'Username',
            'smtp_password' => 'Password',
            'smtp_scheme' => 'Encryption scheme (e.g. tls)',
        ],
        'options' => [
            'postal' => 'Postal',
            'smtp' => 'SMTP',
            'log' => 'Log (no delivery)',
        ],
        'test' => [
            'title' => 'Send test email',
            'recipient_label' => 'Recipient (defaults to your address)',
            'send_button' => 'Send test',
        ],
    ],
    'storage' => [
        'title' => 'Storage settings',
        'driver_changed_warning' => 'Existing files (logos, favicons) stay on the previous disk and may need re-uploading. New uploads will use the selected disk.',
        'fields' => [
            'driver' => 'Storage backend',
            's3_key' => 'Access key ID',
            's3_secret' => 'Secret access key',
            's3_region' => 'Region',
            's3_bucket' => 'Bucket',
            's3_endpoint' => 'Endpoint',
            's3_endpoint_hint' => 'Leave blank for AWS; set for Cloudflare R2, MinIO, DigitalOcean Spaces, Hetzner.',
            's3_use_path_style' => 'Use path-style endpoint',
        ],
        'options' => [
            'local' => 'Local disk',
            's3' => 'S3-compatible',
        ],
        'test_connection' => 'Test connection',
    ],
];
