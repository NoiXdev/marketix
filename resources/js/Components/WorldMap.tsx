import { useMemo, useState } from 'react';
import { geoNaturalEarth1, geoPath } from 'd3-geo';
import { feature } from 'topojson-client';
import type { FeatureCollection, Geometry } from 'geojson';
import { numericToAlpha2 } from 'i18n-iso-countries';
import worldData from 'world-atlas/countries-110m.json';

// Minimal local type for topojson-specification's Topology (not directly importable)
type TopoTopology = { objects: Record<string, unknown> };

export interface CountryDatum {
  country_code: string;
  country: string;
  count: number;
}

// 5-step fill ramp, light → teal (matches the app accent). Fixed data-viz
// hues (legible on both light and dark surfaces); no-data uses a theme token.
const BUCKETS = ['#d5f0ea', '#8fddcf', '#43c2ac', '#0d9488', '#0a6b60'];
const NO_DATA = 'var(--elevated)';

const projection = geoNaturalEarth1();
const pathGen = geoPath(projection);

// world-atlas countries-110m: numeric string ids in `id`, name in properties.name.
const topo = worldData as unknown as TopoTopology;
const countries = feature(
  topo as Parameters<typeof feature>[0],
  topo.objects['countries'] as Parameters<typeof feature>[1],
) as unknown as FeatureCollection<Geometry, { name: string }>;

interface Props {
  data: CountryDatum[];
}

export default function WorldMap({ data }: Props) {
  const [hover, setHover] = useState<{ name: string; count: number; x: number; y: number } | null>(null);

  const byAlpha2 = useMemo(() => {
    const m = new Map<string, CountryDatum>();
    for (const d of data) m.set(d.country_code.toUpperCase(), d);
    return m;
  }, [data]);

  const max = useMemo(() => data.reduce((acc, d) => Math.max(acc, d.count), 0), [data]);

  // Quantize a count into one of BUCKETS by share of the max.
  function fillFor(count: number): string {
    if (max === 0 || count === 0) return NO_DATA;
    const ratio = count / max;
    const idx = Math.min(BUCKETS.length - 1, Math.floor(ratio * BUCKETS.length));
    return BUCKETS[idx];
  }

  const hasData = max > 0;

  return (
    <div className="rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-foreground">Clicks by country</h2>
        {!hasData && (
          <span className="text-xs text-subtle">No location data yet</span>
        )}
      </div>

      <div className="relative">
        <svg viewBox="0 0 960 500" className="h-auto w-full" role="img" aria-label="World map of clicks by country">
          <g>
            {countries.features.map((geo, i) => {
              const numericId = String((geo as unknown as { id: string }).id);
              const alpha2 = numericToAlpha2(numericId);
              const datum = alpha2 ? byAlpha2.get(alpha2.toUpperCase()) : undefined;
              const d = pathGen(geo) ?? undefined;
              return (
                <path
                  key={i}
                  d={d}
                  className="stroke-[color:var(--surface)]"
                  strokeWidth={0.4}
                  fill={datum ? fillFor(datum.count) : NO_DATA}
                  onMouseEnter={(e) =>
                    datum &&
                    setHover({
                      name: datum.country,
                      count: datum.count,
                      x: e.nativeEvent.offsetX,
                      y: e.nativeEvent.offsetY,
                    })
                  }
                  onMouseMove={(e) =>
                    datum &&
                    setHover((h) => (h ? { ...h, x: e.nativeEvent.offsetX, y: e.nativeEvent.offsetY } : h))
                  }
                  onMouseLeave={() => setHover(null)}
                />
              );
            })}
          </g>
        </svg>

        {hover && (
          <div
            className="pointer-events-none absolute z-10 rounded-md bg-foreground px-2 py-1 text-xs text-canvas shadow-[var(--shadow)]"
            style={{ left: hover.x + 12, top: hover.y + 12 }}
          >
            {hover.name}: {hover.count.toLocaleString()}
          </div>
        )}
      </div>

      {/* Legend */}
      {hasData && (
        <div className="mt-4 flex items-center gap-2 text-xs text-subtle">
          <span>Fewer</span>
          {BUCKETS.map((c) => (
            <span key={c} className="inline-block h-3 w-6 rounded-sm" style={{ backgroundColor: c }} />
          ))}
          <span>More</span>
        </div>
      )}
    </div>
  );
}
