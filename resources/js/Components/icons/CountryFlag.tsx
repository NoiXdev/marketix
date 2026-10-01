import { Globe } from 'lucide-react';

/**
 * Renders a country's flag from its ISO 3166-1 alpha-2 code using the
 * `flag-icons` SVG set, so flags look identical on every OS (unlike emoji
 * flags, which Windows browsers render as bare letter pairs). Falls back to a
 * globe when the code is missing or unknown.
 */
export function CountryFlag({ code, className = '' }: { code?: string | null; className?: string }) {
  const cc = code?.trim().toLowerCase();

  if (!cc || cc.length !== 2) {
    return <Globe className="h-4 w-4 text-subtle" aria-hidden />;
  }

  return (
    <span
      className={`fi fi-${cc} rounded-[2px] bg-elevated ${className}`}
      style={{ fontSize: '1rem', lineHeight: 1 }}
      aria-hidden
    />
  );
}
