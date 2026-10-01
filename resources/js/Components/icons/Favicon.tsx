import { Globe } from 'lucide-react';
import { useState } from 'react';

/**
 * Shows a referrer domain's favicon, served through the app's own cached
 * favicon endpoint (no third party ever sees the domain). Falls back to a globe
 * if the domain is missing or the image fails to load.
 */
export function Favicon({ domain, className = 'h-4 w-4 rounded-[2px]' }: { domain?: string | null; className?: string }) {
  const [failed, setFailed] = useState(false);
  const host = domain?.trim();

  if (!host || failed) {
    return <Globe className="h-4 w-4 text-subtle" aria-hidden />;
  }

  return (
    <img
      src={route('app.favicon.show', { domain: host })}
      alt=""
      className={className}
      loading="lazy"
      onError={() => setFailed(true)}
    />
  );
}
