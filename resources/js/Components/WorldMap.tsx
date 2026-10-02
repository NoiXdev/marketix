import { type MouseEvent as ReactMouseEvent, useEffect, useMemo, useRef, useState } from 'react';
import { geoNaturalEarth1, geoPath } from 'd3-geo';
import { feature } from 'topojson-client';
import type { FeatureCollection, Geometry } from 'geojson';
import { numericToAlpha2 } from 'i18n-iso-countries';
import worldData from 'world-atlas/countries-110m.json';
import { Minus, Plus, RotateCcw } from 'lucide-react';
import { useTranslation } from '@/lib/i18n';

// Minimal local type for topojson-specification's Topology (not directly importable)
type TopoTopology = { objects: Record<string, unknown> };

export interface CountryDatum {
  country_code: string;
  country: string;
  count: number;
}

// 5-step teal fill ramp, fewest → most clicks, defined per theme in app.css:
// light mode runs light → dark, dark mode dark → light, so the most clicks
// always get the strongest contrast. No-data uses a theme token as well.
const BUCKETS = ['var(--map-1)', 'var(--map-2)', 'var(--map-3)', 'var(--map-4)', 'var(--map-5)'];
const NO_DATA = 'var(--elevated)';

const VIEW_W = 960;
const VIEW_H = 500;
const MIN_K = 1;
const MAX_K = 8;

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
  title?: string;
}

interface View {
  k: number;
  x: number;
  y: number;
}

const IDENTITY: View = { k: 1, x: 0, y: 0 };

// Keep the scaled map covering the viewport (no empty gutters).
function clampView(k: number, x: number, y: number): View {
  return {
    k,
    x: Math.min(0, Math.max(VIEW_W * (1 - k), x)),
    y: Math.min(0, Math.max(VIEW_H * (1 - k), y)),
  };
}

export default function WorldMap({ data, title }: Props) {
  const { t } = useTranslation();
  const [hover, setHover] = useState<{ name: string; count: number; x: number; y: number } | null>(null);
  const [view, setView] = useState<View>(IDENTITY);
  const [isDragging, setIsDragging] = useState(false);

  const svgRef = useRef<SVGSVGElement>(null);
  const dragFrom = useRef<{ x: number; y: number } | null>(null);

  const byAlpha2 = useMemo(() => {
    const m = new Map<string, CountryDatum>();
    for (const d of data) m.set(d.country_code.toUpperCase(), d);
    return m;
  }, [data]);

  const max = useMemo(() => data.reduce((acc, d) => Math.max(acc, d.count), 0), [data]);
  const hasData = max > 0;

  // Quantize a count into one of BUCKETS by share of the max.
  function fillFor(count: number): string {
    if (max === 0 || count === 0) return NO_DATA;
    const ratio = count / max;
    const idx = Math.min(BUCKETS.length - 1, Math.floor(ratio * BUCKETS.length));
    return BUCKETS[idx];
  }

  // Convert client coords to the map's viewBox coordinate system.
  function toView(clientX: number, clientY: number) {
    const rect = svgRef.current!.getBoundingClientRect();
    return {
      vx: ((clientX - rect.left) / rect.width) * VIEW_W,
      vy: ((clientY - rect.top) / rect.height) * VIEW_H,
    };
  }

  // Zoom by `factor` while keeping the point (vx, vy) fixed on screen.
  function zoomAt(vx: number, vy: number, factor: number) {
    setView((v) => {
      const k = Math.min(MAX_K, Math.max(MIN_K, v.k * factor));
      const ratio = k / v.k;
      return clampView(k, vx - (vx - v.x) * ratio, vy - (vy - v.y) * ratio);
    });
  }

  // Wheel zoom — only when Ctrl/Cmd is held (trackpad pinch also reports
  // ctrlKey), so a plain scroll over the map keeps scrolling the page.
  // Attached natively so we can preventDefault only in the zoom case.
  useEffect(() => {
    const svg = svgRef.current;
    if (!svg) return;
    const onWheel = (e: WheelEvent) => {
      if (!e.ctrlKey && !e.metaKey) return;
      e.preventDefault();
      const { vx, vy } = toView(e.clientX, e.clientY);
      zoomAt(vx, vy, e.deltaY < 0 ? 1.2 : 1 / 1.2);
    };
    svg.addEventListener('wheel', onWheel, { passive: false });
    return () => svg.removeEventListener('wheel', onWheel);
  }, []);

  // Drag to pan.
  useEffect(() => {
    if (!isDragging) return;
    const onMove = (e: MouseEvent) => {
      if (!dragFrom.current || !svgRef.current) return;
      const rect = svgRef.current.getBoundingClientRect();
      const dx = ((e.clientX - dragFrom.current.x) / rect.width) * VIEW_W;
      const dy = ((e.clientY - dragFrom.current.y) / rect.height) * VIEW_H;
      dragFrom.current = { x: e.clientX, y: e.clientY };
      setView((v) => clampView(v.k, v.x + dx, v.y + dy));
    };
    const onUp = () => {
      dragFrom.current = null;
      setIsDragging(false);
    };
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
    return () => {
      window.removeEventListener('mousemove', onMove);
      window.removeEventListener('mouseup', onUp);
    };
  }, [isDragging]);

  function startDrag(e: ReactMouseEvent) {
    dragFrom.current = { x: e.clientX, y: e.clientY };
    setIsDragging(true);
    setHover(null);
  }

  const zoomBtn =
    'flex h-7 w-7 items-center justify-center rounded-md border border-line bg-surface text-muted shadow-[var(--shadow-sm)] transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] disabled:opacity-40';

  return (
    <div className="rounded-[var(--radius)] border border-line bg-surface p-6 shadow-[var(--shadow-sm)]">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-sm font-semibold text-foreground">{title ?? t('common.map.title')}</h2>
        {hasData ? (
          <span className="hidden text-xs text-subtle sm:inline">{t('common.map.zoom_hint')}</span>
        ) : (
          <span className="text-xs text-subtle">{t('common.map.no_data')}</span>
        )}
      </div>

      <div className="relative overflow-hidden rounded-lg">
        <svg
          ref={svgRef}
          viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
          className={`h-auto w-full select-none ${isDragging ? 'cursor-grabbing' : 'cursor-grab'}`}
          role="img"
          aria-label="World map of clicks by country"
          onMouseDown={startDrag}
        >
          <g transform={`translate(${view.x} ${view.y}) scale(${view.k})`}>
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
                  vectorEffect="non-scaling-stroke"
                  fill={datum ? fillFor(datum.count) : NO_DATA}
                  onMouseEnter={(e) =>
                    datum &&
                    !isDragging &&
                    setHover({
                      name: datum.country,
                      count: datum.count,
                      x: e.nativeEvent.offsetX,
                      y: e.nativeEvent.offsetY,
                    })
                  }
                  onMouseMove={(e) =>
                    datum &&
                    !isDragging &&
                    setHover((h) => (h ? { ...h, x: e.nativeEvent.offsetX, y: e.nativeEvent.offsetY } : h))
                  }
                  onMouseLeave={() => setHover(null)}
                />
              );
            })}
          </g>
        </svg>

        {/* Zoom controls */}
        <div className="absolute right-2 top-2 flex flex-col gap-1">
          <button type="button" className={zoomBtn} aria-label={t('common.map.zoom_in')} title={t('common.map.zoom_in')} onClick={() => zoomAt(VIEW_W / 2, VIEW_H / 2, 1.4)}>
            <Plus className="h-4 w-4" />
          </button>
          <button type="button" className={zoomBtn} aria-label={t('common.map.zoom_out')} title={t('common.map.zoom_out')} onClick={() => zoomAt(VIEW_W / 2, VIEW_H / 2, 1 / 1.4)}>
            <Minus className="h-4 w-4" />
          </button>
          <button type="button" className={zoomBtn} aria-label={t('common.map.reset')} title={t('common.map.reset')} disabled={view.k === 1 && view.x === 0 && view.y === 0} onClick={() => setView(IDENTITY)}>
            <RotateCcw className="h-4 w-4" />
          </button>
        </div>

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
          <span>{t('common.map.legend_less')}</span>
          {BUCKETS.map((c) => (
            <span key={c} className="inline-block h-3 w-6 rounded-sm" style={{ backgroundColor: c }} />
          ))}
          <span>{t('common.map.legend_more')}</span>
        </div>
      )}
    </div>
  );
}
