import Swal from 'sweetalert2';

import { getStoredTheme, resolveIsDark } from '@/lib/theme';

type ConfirmDeleteOptions = {
    /** Dialog heading. Defaults to 'Are you sure?'. */
    title?: string;
    /** Body text — usually names the entity and notes irreversibility. */
    text?: string;
    /** Label for the confirm button. Defaults to 'Delete'. */
    confirmText?: string;
};

/**
 * Themed delete confirmation backed by SweetAlert2. Resolves to `true` when the
 * user confirms, `false` otherwise. Styling follows the app's light/dark theme.
 */
export async function confirmDelete(opts: ConfirmDeleteOptions = {}): Promise<boolean> {
    const isDark = resolveIsDark(getStoredTheme());

    const result = await Swal.fire({
        title: opts.title ?? 'Are you sure?',
        text: opts.text,
        icon: 'warning',
        iconColor: '#ef4444',
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Delete',
        cancelButtonText: 'Cancel',
        focusCancel: true,
        reverseButtons: true,
        buttonsStyling: false,
        background: isDark ? '#1e293b' : '#ffffff',
        color: isDark ? '#e2e8f0' : '#0f172a',
        customClass: {
            popup: 'rounded-xl',
            confirmButton:
                'inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2',
            cancelButton:
                'mr-3 inline-flex items-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        },
    });

    return result.isConfirmed;
}

type ConfirmActionOptions = {
    /** Dialog heading. Defaults to 'Are you sure?'. */
    title?: string;
    /** Body text. */
    text?: string;
    /** Label for the confirm button. Defaults to 'Confirm'. */
    confirmText?: string;
};

/**
 * Themed non-destructive confirmation (indigo confirm button). Resolves to
 * `true` when confirmed. Use for risky-but-not-deleting actions.
 */
export async function confirmAction(opts: ConfirmActionOptions = {}): Promise<boolean> {
    const isDark = resolveIsDark(getStoredTheme());

    const result = await Swal.fire({
        title: opts.title ?? 'Are you sure?',
        text: opts.text,
        icon: 'warning',
        iconColor: '#f59e0b',
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Confirm',
        cancelButtonText: 'Cancel',
        focusCancel: true,
        reverseButtons: true,
        buttonsStyling: false,
        background: isDark ? '#1e293b' : '#ffffff',
        color: isDark ? '#e2e8f0' : '#0f172a',
        customClass: {
            popup: 'rounded-xl',
            confirmButton:
                'inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
            cancelButton:
                'mr-3 inline-flex items-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800',
        },
    });

    return result.isConfirmed;
}
