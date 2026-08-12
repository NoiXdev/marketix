/** Seconds between two ISO timestamps, or null if either is missing. */
export function durationBetween(start?: string | null, end?: string | null): number | null {
  if (!start || !end) return null;
  return (new Date(end).getTime() - new Date(start).getTime()) / 1000;
}

/** Human-readable duration, e.g. 95 -> "1m 35s". */
export function formatDuration(seconds: number | null | undefined): string {
  if (seconds == null || seconds < 0) return '—';
  if (seconds < 60) return `${Math.round(seconds)}s`;

  const totalMinutes = Math.floor(seconds / 60);
  const secs = Math.round(seconds % 60);
  if (totalMinutes < 60) return secs ? `${totalMinutes}m ${secs}s` : `${totalMinutes}m`;

  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  return minutes ? `${hours}h ${minutes}m` : `${hours}h`;
}
