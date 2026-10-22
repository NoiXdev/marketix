import { axisLabelStep, formatAxisDate } from '@/lib/chartAxis';
import { useLayoutEffect, useRef, useState } from 'react';

const CHAR_WIDTH = 5.5;
const LABEL_GAP = 20;

/**
 * Horizontal date labels rendered beneath a bar chart, aligned 1:1 with the
 * bars. Labels are thinned to fit the available width, counted back from the
 * last date so the most recent bucket is always labelled. `gapClass` must match
 * the bar row's gap so labels line up under their bars.
 */
export default function ChartDateAxis({ dates, gapClass = 'gap-[3px]', format = formatAxisDate }: { dates: string[]; gapClass?: string; format?: (value: string) => string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(0);
  const labels = dates.map(format);
  const labelWidth = Math.max(0, ...labels.map((label) => label.length)) * CHAR_WIDTH + LABEL_GAP;
  const step = width > 0 ? axisLabelStep(dates.length, Math.floor(width / labelWidth)) : axisLabelStep(dates.length);
  const last = dates.length - 1;

  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    setWidth(el.getBoundingClientRect().width);
    const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width));
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div ref={ref} className={`mt-2 flex h-3 ${gapClass}`}>
      {dates.map((date, i) => (
        <div key={date} className="relative min-w-0 flex-1">
          {(last - i) % step === 0 && (
            <span
              className={`text-subtle absolute top-0 text-[10px] leading-none whitespace-nowrap ${
                i === last && dates.length > 1 ? 'right-0' : i === 0 ? 'left-0' : 'left-1/2 -translate-x-1/2'
              }`}
            >
              {labels[i]}
            </span>
          )}
        </div>
      ))}
    </div>
  );
}
