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
