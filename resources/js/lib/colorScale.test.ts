import { describe, expect, it } from 'vitest';
import { SCALE_EMPTY, SCALE_STEPS, scaleFill } from './colorScale';

describe('scaleFill', () => {
  it('uses the empty fill when there is no value', () => {
    expect(scaleFill(0, 10)).toBe(SCALE_EMPTY);
    expect(scaleFill(5, 0)).toBe(SCALE_EMPTY);
  });

  it('quantizes the share of the max into five steps', () => {
    expect(scaleFill(1, 10)).toBe(SCALE_STEPS[0]);
    expect(scaleFill(5, 10)).toBe(SCALE_STEPS[2]);
    expect(scaleFill(9, 10)).toBe(SCALE_STEPS[4]);
    expect(scaleFill(10, 10)).toBe(SCALE_STEPS[4]);
  });
});
