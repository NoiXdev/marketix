import { axisLabelStep, formatAxisDate } from '@/lib/chartAxis';

/**
 * Vertical date labels rendered beneath a bar chart, aligned 1:1 with the bars.
 * Labels are thinned for long ranges and the last date is always shown, so the
 * timeline stays readable even when every bar is zero. `gapClass` must match the
 * bar row's gap so labels line up under their bars.
 */
export default function ChartDateAxis({
  dates,
  gapClass = 'gap-[3px]',
  format = formatAxisDate,
}: {
  dates: string[];
  gapClass?: string;
  format?: (value: string) => string;
}) {
  const step = axisLabelStep(dates.length);

  return (
    <div className={`mt-2 flex ${gapClass}`}>
      {dates.map((date, i) => (
        <div key={date} className="flex flex-1 justify-center">
          {(i % step === 0 || i === dates.length - 1) && (
            <span className="whitespace-nowrap text-[10px] leading-none text-subtle [writing-mode:vertical-rl]">
              {format(date)}
            </span>
          )}
        </div>
      ))}
    </div>
  );
}
