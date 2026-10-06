import { Badge } from '@/Components/ui';

export type Choice = { value: string; title: string; text: string; badge?: string };

export default function ChoiceCards({
  legend,
  name,
  value,
  options,
  onChange,
}: {
  legend: string;
  name: string;
  value: string;
  options: Choice[];
  onChange: (value: string) => void;
}) {
  return (
    <fieldset>
      <legend className="text-foreground mb-2 text-sm font-semibold">{legend}</legend>
      <div className="grid gap-3 sm:grid-cols-2">
        {options.map((option) => {
          const checked = option.value === value;
          return (
            <label
              key={option.value}
              className={`flex cursor-pointer gap-3 rounded-lg border p-4 transition-colors focus-within:ring-2 focus-within:ring-[color:var(--accent-ring)] ${
                checked ? 'border-accent bg-accent-soft/40' : 'border-line hover:border-line-strong hover:bg-elevated'
              }`}
            >
              <input type="radio" name={name} value={option.value} checked={checked} onChange={() => onChange(option.value)} className="sr-only" />
              <span
                aria-hidden
                className={`mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full border-2 transition-colors ${checked ? 'border-accent' : 'border-line-strong'}`}
              >
                {checked && <span className="bg-accent h-1.5 w-1.5 rounded-full" />}
              </span>
              <span className="min-w-0">
                <span className="text-foreground flex flex-wrap items-center gap-2 text-sm font-semibold">
                  {option.title}
                  {option.badge && <Badge variant="accent">{option.badge}</Badge>}
                </span>
                <span className="text-muted mt-1 block text-xs leading-relaxed">{option.text}</span>
              </span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
