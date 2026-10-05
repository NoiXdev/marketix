export const SCALE_STEPS = ['var(--map-1)', 'var(--map-2)', 'var(--map-3)', 'var(--map-4)', 'var(--map-5)'];

export const SCALE_EMPTY = 'var(--elevated)';

export function scaleFill(value: number, max: number): string {
  if (max <= 0 || value <= 0) return SCALE_EMPTY;
  return SCALE_STEPS[Math.min(SCALE_STEPS.length - 1, Math.floor((value / max) * SCALE_STEPS.length))];
}
