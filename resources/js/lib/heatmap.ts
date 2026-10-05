const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

export type HourBucket = [timestamp: number, count: number];

export function browserTimeZone(): string {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
  } catch {
    return 'UTC';
  }
}

export function weekdayHourGrid(buckets: HourBucket[], timeZone: string): number[][] {
  const grid = Array.from({ length: 7 }, () => Array<number>(24).fill(0));
  const format = new Intl.DateTimeFormat('en-US', { timeZone, weekday: 'short', hour: '2-digit', hourCycle: 'h23' });

  for (const [timestamp, count] of buckets) {
    const parts = format.formatToParts(new Date(timestamp * 1000));
    const day = WEEKDAYS.indexOf(parts.find((p) => p.type === 'weekday')?.value ?? '');
    const hour = Number(parts.find((p) => p.type === 'hour')?.value);
    if (day >= 0 && hour >= 0 && hour < 24) grid[day][hour] += count;
  }

  return grid;
}
