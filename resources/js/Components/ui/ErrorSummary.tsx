export function ErrorSummary({ title, errors }: { title: string; errors: string[] }) {
  if (errors.length === 0) return null;
  return (
    <div className="bg-danger-soft rounded-[var(--radius)] border border-[color:color-mix(in_srgb,var(--danger-foreground)_35%,transparent)] p-4">
      <p className="text-danger-foreground text-sm font-semibold">{title}</p>
      <ul className="text-danger-foreground mt-2 list-disc space-y-1 pl-5 text-xs">
        {errors.map((msg, i) => (
          <li key={i}>{msg}</li>
        ))}
      </ul>
    </div>
  );
}
