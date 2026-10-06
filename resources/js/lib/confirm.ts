import ConfirmDialog, { ConfirmDialogProps } from '@/Components/ConfirmDialog';
import { translate } from '@/lib/i18n';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';

const LEAVE_DURATION_MS = 200;

let catalog: Record<string, unknown> = {};

export function setConfirmTranslations(translations: unknown) {
    if (translations && typeof translations === 'object') {
        catalog = translations as Record<string, unknown>;
    }
}

function label(key: string, fallback: string): string {
    const value = translate(catalog, key);
    return value === key ? fallback : value;
}

function openDialog(props: Omit<ConfirmDialogProps, 'onResolve'>): Promise<boolean> {
    return new Promise((resolve) => {
        const host = document.createElement('div');
        document.body.appendChild(host);
        const root = createRoot(host);

        root.render(
            createElement(ConfirmDialog, {
                ...props,
                onResolve: (confirmed: boolean) => {
                    resolve(confirmed);
                    window.setTimeout(() => {
                        root.unmount();
                        host.remove();
                    }, LEAVE_DURATION_MS);
                },
            }),
        );
    });
}

type ConfirmOptions = {
    title?: string;
    text?: string;
    confirmText?: string;
};

export function confirmDelete(opts: ConfirmOptions = {}): Promise<boolean> {
    return openDialog({
        tone: 'danger',
        title: opts.title ?? label('common.dialog.title', 'Are you sure?'),
        text: opts.text,
        confirmLabel: opts.confirmText ?? label('common.actions.delete', 'Delete'),
        cancelLabel: label('common.actions.cancel', 'Cancel'),
    });
}

export function confirmAction(opts: ConfirmOptions = {}): Promise<boolean> {
    return openDialog({
        tone: 'warning',
        title: opts.title ?? label('common.dialog.title', 'Are you sure?'),
        text: opts.text,
        confirmLabel: opts.confirmText ?? label('common.actions.confirm', 'Confirm'),
        cancelLabel: label('common.actions.cancel', 'Cancel'),
    });
}

export function confirmTyped(opts: ConfirmOptions & { match: string }): Promise<boolean> {
    return openDialog({
        tone: 'danger',
        title: opts.title ?? label('common.dialog.title', 'Are you sure?'),
        text: opts.text,
        confirmLabel: opts.confirmText ?? label('common.actions.confirm', 'Confirm'),
        cancelLabel: label('common.actions.cancel', 'Cancel'),
        match: opts.match,
        matchPrompt: label('common.dialog.type_to_confirm', 'Type :value to confirm.'),
    });
}
