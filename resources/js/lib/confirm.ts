import Swal from 'sweetalert2';

// Token-driven button/popup styling. SweetAlert renders in a portal on
// document.body, so `var(--…)` resolves against :root / .dark just like the
// rest of the app — no JS theme branching needed.
const POPUP = 'rounded-[var(--radius)] border border-line';
const CANCEL_BTN =
    'mr-3 inline-flex items-center rounded-[var(--radius-sm)] border border-line-strong bg-surface px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-elevated focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]';
const DANGER_BTN =
    'inline-flex items-center rounded-[var(--radius-sm)] bg-[color:var(--danger-dot)] px-4 py-2 text-sm font-semibold text-white transition-colors hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]';
const PRIMARY_BTN =
    'inline-flex items-center rounded-[var(--radius-sm)] bg-accent px-4 py-2 text-sm font-semibold text-accent-foreground transition-colors hover:bg-accent-hover focus:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)]';

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
 * user confirms, `false` otherwise. Styling follows the app's design tokens.
 */
export async function confirmDelete(opts: ConfirmDeleteOptions = {}): Promise<boolean> {
    const result = await Swal.fire({
        title: opts.title ?? 'Are you sure?',
        text: opts.text,
        icon: 'warning',
        iconColor: 'var(--danger-dot)',
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Delete',
        cancelButtonText: 'Cancel',
        focusCancel: true,
        reverseButtons: true,
        buttonsStyling: false,
        background: 'var(--surface)',
        color: 'var(--foreground)',
        customClass: {
            popup: POPUP,
            confirmButton: DANGER_BTN,
            cancelButton: CANCEL_BTN,
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
 * Themed non-destructive confirmation (accent confirm button). Resolves to
 * `true` when confirmed. Use for risky-but-not-deleting actions.
 */
export async function confirmAction(opts: ConfirmActionOptions = {}): Promise<boolean> {
    const result = await Swal.fire({
        title: opts.title ?? 'Are you sure?',
        text: opts.text,
        icon: 'warning',
        iconColor: 'var(--warning-dot)',
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Confirm',
        cancelButtonText: 'Cancel',
        focusCancel: true,
        reverseButtons: true,
        buttonsStyling: false,
        background: 'var(--surface)',
        color: 'var(--foreground)',
        customClass: {
            popup: POPUP,
            confirmButton: PRIMARY_BTN,
            cancelButton: CANCEL_BTN,
        },
    });

    return result.isConfirmed;
}

type ConfirmTypedOptions = {
    /** Dialog heading. */
    title?: string;
    /** Body text — names the entity and notes irreversibility. */
    text?: string;
    /** The exact string the user must type to enable confirmation. */
    match: string;
    /** Label for the confirm button. Defaults to 'Confirm'. */
    confirmText?: string;
    /** Validation message shown when the typed value does not match. */
    mismatchText?: string;
};

/**
 * Themed destructive confirmation that requires the user to type an exact
 * string (e.g. the entity's slug) before confirming. Resolves to `true` only
 * when confirmed with a matching value.
 */
export async function confirmTyped(opts: ConfirmTypedOptions): Promise<boolean> {
    const result = await Swal.fire({
        title: opts.title ?? 'Are you sure?',
        text: opts.text,
        icon: 'warning',
        iconColor: 'var(--danger-dot)',
        input: 'text',
        inputPlaceholder: opts.match,
        inputAttributes: { autocapitalize: 'off', autocorrect: 'off', autocomplete: 'off' },
        showCancelButton: true,
        confirmButtonText: opts.confirmText ?? 'Confirm',
        cancelButtonText: 'Cancel',
        focusCancel: false,
        reverseButtons: true,
        buttonsStyling: false,
        background: 'var(--surface)',
        color: 'var(--foreground)',
        preConfirm: (value: string) => {
            if (value !== opts.match) {
                Swal.showValidationMessage(opts.mismatchText ?? `Please type "${opts.match}" to confirm.`);
                return false;
            }
            return true;
        },
        customClass: {
            popup: POPUP,
            confirmButton: DANGER_BTN,
            cancelButton: CANCEL_BTN,
        },
    });

    return result.isConfirmed;
}
