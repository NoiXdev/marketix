import { CountryFlag } from '@/Components/icons/CountryFlag';
import ScaleLegend from '@/Components/ScaleLegend';
import { SCALE_EMPTY, scaleFill } from '@/lib/colorScale';
import { countryName } from '@/lib/displayNames';
import { useTranslation } from '@/lib/i18n';
import { geoNaturalEarth1, geoPath } from 'd3-geo';
import type { FeatureCollection, Geometry } from 'geojson';
import { numericToAlpha2 } from 'i18n-iso-countries';
import { Minus, Plus, RotateCcw } from 'lucide-react';
import { type MouseEvent as ReactMouseEvent, useEffect, useMemo, useRef, useState } from 'react';
import { feature } from 'topojson-client';
import worldData from 'world-atlas/countries-110m.json';

// Minimal local type for topojson-specification's Topology (not directly importable)
type TopoTopology = { objects: Record<string, unknown> };

export interface CountryDatum {
  country_code: string;
  country: string;
  count: number;
}

const NO_DATA = SCALE_EMPTY;

const VIEW_W = 960;
const VIEW_H = 500;
const MIN_K = 1;
const MAX_K = 8;

const projection = geoNaturalEarth1();
const pathGen = geoPath(projection);

// world-atlas countries-110m: numeric string ids in `id`, name in properties.name.
const topo = worldData as unknown as TopoTopology;
const countries = feature(topo as Parameters<typeof feature>[0], topo.objects['countries'] as Parameters<typeof feature>[1]) as unknown as FeatureCollection<
  Geometry,
  { name: string }
>;

interface Props {
  data: CountryDatum[];
  title?: string;
  /** When true, renders the map content without the outer card wrapper so it nests inside another container (e.g. WidgetFrame). */
  bare?: boolean;
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

export default function WorldMap({ data, title, bare }: Props) {
  const { t, locale } = useTranslation();
  const [hover, setHover] = useState<{ code: string; name: string; count: number; x: number; y: number; right: number | null } | null>(null);
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

  function fillFor(count: number): string {
    return scaleFill(count, max);
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

  function pointer(e: ReactMouseEvent) {
    const x = e.nativeEvent.offsetX;
    const width = svgRef.current?.clientWidth ?? 0;
    return { x, y: e.nativeEvent.offsetY, right: x > width / 2 ? width - x : null };
  }

  function startDrag(e: ReactMouseEvent) {
    dragFrom.current = { x: e.clientX, y: e.clientY };
    setIsDragging(true);
    setHover(null);
  }

  const zoomBtn =
    'flex h-7 w-7 items-center justify-center rounded-md border border-line bg-surface text-muted shadow-[var(--shadow-sm)] transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--accent-ring)] disabled:opacity-40';

  const titleRow = (
    <>
      <h2 className="text-foreground text-sm font-semibold">{title ?? t('common.map.title')}</h2>
      {hasData ? <span className="text-subtle hidden text-xs sm:inline">{t('common.map.zoom_hint')}</span> : <span className="text-subtle text-xs">{t('common.map.no_data')}</span>}
    </>
  );

  // `svgClass` lets the bare (dashboard widget) rendering fill the available
  // height (`h-full`, letterboxed to fit) while the page rendering keeps its
  // width-driven aspect ratio (`h-auto`).
  const mapSvg = (svgClass: string) => (
    <svg
      ref={svgRef}
      viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
      preserveAspectRatio="xMidYMid meet"
      className={`${svgClass} select-none ${isDragging ? 'cursor-grabbing' : 'cursor-grab'}`}
      role="img"
      aria-label={title ?? t('common.map.title')}
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
                datum && !isDragging && setHover({ code: datum.country_code, name: countryName(datum.country_code, locale, datum.country), count: datum.count, ...pointer(e) })
              }
              onMouseMove={(e) => datum && !isDragging && setHover((h) => (h ? { ...h, ...pointer(e) } : h))}
              onMouseLeave={() => setHover(null)}
            />
          );
        })}
      </g>
    </svg>
  );

  const controls = (
    <div className="absolute top-2 right-2 flex flex-col gap-1">
      <button type="button" className={zoomBtn} aria-label={t('common.map.zoom_in')} title={t('common.map.zoom_in')} onClick={() => zoomAt(VIEW_W / 2, VIEW_H / 2, 1.4)}>
        <Plus className="h-4 w-4" />
      </button>
      <button type="button" className={zoomBtn} aria-label={t('common.map.zoom_out')} title={t('common.map.zoom_out')} onClick={() => zoomAt(VIEW_W / 2, VIEW_H / 2, 1 / 1.4)}>
        <Minus className="h-4 w-4" />
      </button>
      <button
        type="button"
        className={zoomBtn}
        aria-label={t('common.map.reset')}
        title={t('common.map.reset')}
        disabled={view.k === 1 && view.x === 0 && view.y === 0}
        onClick={() => setView(IDENTITY)}
      >
        <RotateCcw className="h-4 w-4" />
      </button>
    </div>
  );

  const hoverTip = hover && (
    <div
      className="bg-foreground text-canvas pointer-events-none absolute z-10 inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs whitespace-nowrap shadow-[var(--shadow)]"
      style={{ top: hover.y + 12, ...(hover.right !== null ? { right: hover.right + 12 } : { left: hover.x + 12 }) }}
    >
      <CountryFlag code={hover.code} />
      {hover.name}: {hover.count.toLocaleString(locale)}
    </div>
  );

  // Bare: fill the cell (title + flex map area + legend), so the map never scrolls.
  if (bare) {
    return (
      <div className="flex h-full flex-col">
        <div className="mb-2 flex shrink-0 items-center justify-between">{titleRow}</div>
        <div className="relative min-h-0 flex-1 overflow-hidden rounded-lg">
          {mapSvg('h-full w-full')}
          {controls}
          {hoverTip}
        </div>
        {hasData && <ScaleLegend className="mt-2 shrink-0" />}
      </div>
    );
  }

  return (
    <div className="border-line bg-surface rounded-[var(--radius)] border p-6 shadow-[var(--shadow-sm)]">
      <div className="mb-4 flex items-center justify-between">{titleRow}</div>
      <div className="relative overflow-hidden rounded-lg">
        {mapSvg('h-auto w-full')}
        {controls}
        {hoverTip}
      </div>
      {hasData && <ScaleLegend className="mt-4" />}
    </div>
  );
}
