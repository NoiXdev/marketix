import { describe, expect, it } from 'vitest';
import { HourBucket, weekdayHourGrid } from './heatmap';

const at = (iso: string) => Date.parse(iso) / 1000;

describe('weekdayHourGrid', () => {
  const buckets: HourBucket[] = [
    [at('2026-10-06T14:00:00Z'), 3],
    [at('2026-10-26T06:00:00Z'), 2],
  ];

  it('places buckets by weekday (Monday first) and hour in UTC', () => {
    const grid = weekdayHourGrid(buckets, 'UTC');
    expect(grid[1][14]).toBe(3);
    expect(grid[0][6]).toBe(2);
  });

  it('shifts buckets into the viewer time zone, honouring daylight saving', () => {
    const zurich = weekdayHourGrid(buckets, 'Europe/Zurich');
    expect(zurich[1][16]).toBe(3);
    expect(zurich[0][7]).toBe(2);

    const newYork = weekdayHourGrid(buckets, 'America/New_York');
    expect(newYork[1][10]).toBe(3);
    expect(newYork[0][2]).toBe(2);
  });

  it('moves late UTC hours to the next local day', () => {
    const grid = weekdayHourGrid([[at('2026-10-04T23:30:00Z'), 1]], 'Asia/Tokyo');
    expect(grid[0][8]).toBe(1);
  });

  it('always returns a 7 × 24 grid', () => {
    const grid = weekdayHourGrid([], 'UTC');
    expect(grid).toHaveLength(7);
    expect(grid.every((row) => row.length === 24)).toBe(true);
  });
});
