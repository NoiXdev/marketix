/**
 * Chart x-axis helpers shared by the "over time" bar charts.
 */

/**
 * How many bars to skip between visible date labels, so roughly `maxLabels`
 * labels show regardless of the range length (7, 30, 90, 365 days). Always at
 * least 1 (label every bar).
 */
export function axisLabelStep(count: number, maxLabels = 16): number {
  if (count <= maxLabels) return 1;
  return Math.ceil(count / maxLabels);
}

/**
 * Format an ISO `YYYY-MM-DD` date as a short, locale-aware axis label. Built
 * from the parts (local midnight) so the displayed day never shifts due to the
 * UTC-parsing of a bare date string.
 */
export function formatAxisDate(iso: string): string {
  const [y, m, d] = iso.split('-').map(Number);
  if (!y || !m || !d) return iso;
  return new Date(y, m - 1, d).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
