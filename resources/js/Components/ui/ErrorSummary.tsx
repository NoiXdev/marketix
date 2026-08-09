export function ErrorSummary({ title, errors }: { title: string; errors: string[] }) {
  if (errors.length === 0) return null;
  return (
    <div className="rounded-[var(--radius)] border border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] bg-danger-soft p-4">
      <p className="text-sm font-semibold text-danger-foreground">{title}</p>
      <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-danger-foreground">
        {errors.map((msg, i) => (
          <li key={i}>{msg}</li>
        ))}
      </ul>
    </div>
  );
}
