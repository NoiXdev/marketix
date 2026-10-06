import { Checkbox } from '@/Components/ui';

export default function ToggleRow({ checked, onChange, title, text }: { checked: boolean; onChange: (checked: boolean) => void; title: string; text?: string }) {
  return (
    <label className="hover:bg-elevated -mx-2 flex cursor-pointer items-start gap-3 rounded-lg px-2 py-2 transition-colors">
      <Checkbox className="mt-0.5 shrink-0" checked={checked} onChange={(event) => onChange(event.target.checked)} />
      <span className="min-w-0">
        <span className="text-foreground block text-sm font-medium">{title}</span>
        {text && <span className="text-muted mt-0.5 block text-xs leading-relaxed">{text}</span>}
      </span>
    </label>
  );
}
