import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { Description, Dialog, DialogBackdrop, DialogPanel, DialogTitle } from '@headlessui/react';
import { LucideIcon, Trash2, TriangleAlert } from 'lucide-react';
import { FormEvent, useId, useState } from 'react';

export type ConfirmTone = 'danger' | 'warning';

export type ConfirmDialogProps = {
  tone: ConfirmTone;
  title: string;
  text?: string;
  confirmLabel: string;
  cancelLabel: string;
  match?: string;
  matchPrompt?: string;
  onResolve: (confirmed: boolean) => void;
};

const TONES: Record<ConfirmTone, { icon: LucideIcon; badge: string }> = {
  danger: { icon: Trash2, badge: 'bg-danger-soft text-danger-foreground' },
  warning: { icon: TriangleAlert, badge: 'bg-warning-soft text-warning-foreground' },
};

export default function ConfirmDialog({ tone, title, text, confirmLabel, cancelLabel, match, matchPrompt = ':value', onResolve }: ConfirmDialogProps) {
  const [open, setOpen] = useState(true);
  const [value, setValue] = useState('');
  const inputId = useId();
  const { icon: Icon, badge } = TONES[tone];
  const typed = match !== undefined;
  const matches = !typed || value === match;
  const [promptBefore, promptAfter = ''] = matchPrompt.split(':value');

  function close(confirmed: boolean) {
    if (!open) return;
    setOpen(false);
    onResolve(confirmed);
  }

  function submit(event: FormEvent) {
    event.preventDefault();
    if (matches) close(true);
  }

  return (
    <Dialog open={open} onClose={() => close(false)} className="relative z-[100]">
      <DialogBackdrop transition className="fixed inset-0 bg-[rgb(10_14_22/0.45)] backdrop-blur-[2px] transition-opacity duration-150 ease-out data-[closed]:opacity-0" />
      <div className="fixed inset-0 flex items-end justify-center overflow-y-auto p-4 sm:items-center">
        <DialogPanel
          as="form"
          onSubmit={submit}
          transition
          className="border-line bg-surface w-full max-w-md overflow-hidden rounded-[var(--radius)] border shadow-[var(--shadow)] transition duration-150 ease-out data-[closed]:translate-y-2 data-[closed]:scale-[0.98] data-[closed]:opacity-0"
        >
          <div className="flex gap-4 p-5">
            <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-full ${badge}`}>
              <Icon className="h-5 w-5" />
            </span>
            <div className="min-w-0 flex-1 pt-0.5">
              <DialogTitle className="text-foreground text-base font-semibold break-words">{title}</DialogTitle>
              {text && <Description className="text-muted mt-1.5 text-sm leading-relaxed">{text}</Description>}
              {typed && (
                <div className="mt-4 space-y-1.5">
                  <label htmlFor={inputId} className="text-foreground block text-sm">
                    {promptBefore}
                    <span className="bg-elevated rounded px-1.5 py-0.5 font-mono text-[13px] font-semibold break-all">{match}</span>
                    {promptAfter}
                  </label>
                  <Input
                    id={inputId}
                    autoFocus
                    autoComplete="off"
                    autoCapitalize="off"
                    autoCorrect="off"
                    spellCheck={false}
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                  />
                </div>
              )}
            </div>
          </div>
          <div className="border-line bg-elevated/60 flex flex-col-reverse gap-2 border-t px-5 py-3 sm:flex-row sm:justify-end">
            <Button variant="secondary" autoFocus={!typed} onClick={() => close(false)}>
              {cancelLabel}
            </Button>
            <Button type="submit" variant={tone === 'danger' ? 'destructive' : 'primary'} disabled={!matches}>
              {confirmLabel}
            </Button>
          </div>
        </DialogPanel>
      </div>
    </Dialog>
  );
}
