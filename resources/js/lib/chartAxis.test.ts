import { describe, expect, it } from 'vitest';
import { axisLabelStep, formatAxisDate } from './chartAxis';

describe('axisLabelStep', () => {
  it('labels every bar when the range is small', () => {
    expect(axisLabelStep(7)).toBe(1);
    expect(axisLabelStep(16)).toBe(1);
  });

  it('thins labels for long ranges so roughly maxLabels show', () => {
    expect(axisLabelStep(30)).toBe(2);
    expect(axisLabelStep(90)).toBe(6);
    expect(axisLabelStep(365)).toBe(23);
  });

  it('never returns less than 1', () => {
    expect(axisLabelStep(0)).toBe(1);
    expect(axisLabelStep(1)).toBe(1);
  });
});

describe('formatAxisDate', () => {
  it('formats a valid ISO date without shifting the day across time zones', () => {
    // Must render the 15th, never the 14th (would happen with UTC parsing west of GMT).
    expect(formatAxisDate('2026-01-15')).toContain('15');
    expect(formatAxisDate('2026-01-15')).not.toContain('14');
  });

  it('returns the input unchanged when it is not a parseable date', () => {
    expect(formatAxisDate('not-a-date')).toBe('not-a-date');
  });
});
