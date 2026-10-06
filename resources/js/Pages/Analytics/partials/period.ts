export type Interval = 'hour' | 'day' | 'week' | 'month';

export type Comparison = 'previous' | 'year' | 'none';

export type Period = {
  range: string;
  from: string;
  to: string;
  days: number;
  interval: Interval;
  intervals: Interval[];
  compare: Comparison;
  compare_from: string | null;
  compare_to: string | null;
};

export const RANGE_PRESETS = ['today', 'yesterday', '7d', '30d', '90d', 'month', 'last_month', 'year', '12m'];

export const COMPARISONS: Comparison[] = ['previous', 'year', 'none'];

export function parseDate(iso: string): Date {
  const [y, m, d] = iso.slice(0, 10).split('-').map(Number);
  return new Date(y, m - 1, d);
}

export function toIsoDate(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

export function spansSeveralDays(keys: string[]): boolean {
  return keys.length > 0 && keys[0].slice(0, 10) !== keys[keys.length - 1].slice(0, 10);
}

export function formatBucket(key: string, interval: Interval, multiDay: boolean, locale: string, weekOf: (date: string) => string): string {
  const date = parseDate(key);
  switch (interval) {
    case 'hour': {
      const hour = Number(key.slice(11, 13));
      const hours = `${String(hour).padStart(2, '0')}:00 – ${String((hour + 1) % 24).padStart(2, '0')}:00`;
      return multiDay ? `${date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short' })}, ${hours}` : hours;
    }
    case 'week':
      return weekOf(date.toLocaleDateString(locale, { day: 'numeric', month: 'short', year: 'numeric' }));
    case 'month':
      return date.toLocaleDateString(locale, { month: 'long', year: 'numeric' });
    default:
      return date.toLocaleDateString(locale, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
  }
}

export function formatAxisLabel(key: string, interval: Interval, multiDay: boolean, locale: string): string {
  const date = parseDate(key);
  switch (interval) {
    case 'hour':
      return multiDay ? `${date.toLocaleDateString(locale, { day: 'numeric', month: 'short' })} ${key.slice(11, 16)}` : key.slice(11, 16);
    case 'month':
      return date.toLocaleDateString(locale, { month: 'short', year: '2-digit' });
    default:
      return date.toLocaleDateString(locale, { day: 'numeric', month: 'short' });
  }
}

export function formatRange(from: string, to: string, locale: string): string {
  const format = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric' });
  return from === to ? format.format(parseDate(from)) : format.formatRange(parseDate(from), parseDate(to));
}
