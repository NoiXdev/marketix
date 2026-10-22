/**
 * Formats a number into a short, human-friendly form once it reaches 1000:
 * 1k, 5k, 10k, 100k, 1M, 1.5M, … Values below 1000 are returned unchanged.
 */
export function formatCompactNumber(value: number): string {
  return new Intl.NumberFormat('en', {
    notation: 'compact',
    maximumFractionDigits: 1,
  })
    .format(value)
    .replace('K', 'k');
}

export function formatDuration(seconds: number): string {
  const s = Math.max(0, Math.round(seconds));
  if (s < 60) return `${s}s`;
  if (s < 3600) return `${Math.floor(s / 60)}m ${s % 60}s`;
  return `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m`;
}

export function formatMoney(value: number, currency: string, locale: string): string {
  const digits = Math.abs(value) >= 1000 ? 0 : 2;
  const options = { minimumFractionDigits: digits, maximumFractionDigits: digits };
  if (/^[A-Z]{3}$/.test(currency)) {
    try {
      return new Intl.NumberFormat(locale, { style: 'currency', currency, ...options }).format(value);
    } catch {
      return `${value.toLocaleString(locale, options)} ${currency}`;
    }
  }
  return value.toLocaleString(locale, options);
}

export function formatAxisMoney(value: number, currency: string, locale: string): string {
  const options: Intl.NumberFormatOptions =
    Math.abs(value) >= 1_000_000 ? { notation: 'compact', maximumFractionDigits: 1 } : { minimumFractionDigits: 0, maximumFractionDigits: Math.abs(value) >= 10 ? 0 : 2 };
  if (/^[A-Z]{3}$/.test(currency)) {
    try {
      return new Intl.NumberFormat(locale, { style: 'currency', currency, ...options }).format(value);
    } catch {
      return value.toLocaleString(locale, options);
    }
  }
  return value.toLocaleString(locale, options);
}

export function formatPercent(value: number, locale: string): string {
  return `${value.toLocaleString(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;
}

export function percentChange(current: number, previous: number): number | null {
  if (previous === 0) return null;
  return Math.round(((current - previous) / previous) * 1000) / 10;
}

export function pointChange(current: number, previous: number): number {
  return Math.round((current - previous) * 10) / 10;
}
