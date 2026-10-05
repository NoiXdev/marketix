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

export function formatRange(from: string, to: string, locale: string): string {
  const format = new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short', year: 'numeric' });
  return from === to ? format.format(parseDate(from)) : format.formatRange(parseDate(from), parseDate(to));
}
