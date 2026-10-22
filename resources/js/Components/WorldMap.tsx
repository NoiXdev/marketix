import { CountryFlag } from '@/Components/icons/CountryFlag';
import ScaleLegend from '@/Components/ScaleLegend';
import { SCALE_EMPTY, scaleFill } from '@/lib/colorScale';
import { countryName } from '@/lib/displayNames';
import { useTranslation } from '@/lib/i18n';
import { geoArea, geoNaturalEarth1, geoPath } from 'd3-geo';
import type { Feature, FeatureCollection, Geometry, Polygon } from 'geojson';
import { numericToAlpha2 } from 'i18n-iso-countries';
import { Minus, Plus, RotateCcw } from 'lucide-react';
import { type MouseEvent as ReactMouseEvent, useEffect, useEffectEvent, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { feature } from 'topojson-client';
import worldData from 'world-atlas/countries-110m.json';

// Minimal local type for topojson-specification's Topology (not directly importable)
type TopoTopology = { objects: Record<string, unknown> };

export interface CountryDatum {
  country_code: string;
  country: string;
  count: number;
}

type Bounds = [[number, number], [number, number]];

interface View {
  k: number;
  x: number;
  y: number;
}

interface Rect {
  x: number;
  y: number;
  w: number;
  h: number;
}

const NO_DATA = SCALE_EMPTY;
const ANTARCTICA = '010';
const VIEW_W = 960;
const MIN_K = 1;
const MAX_K = 8;
const MAX_FIT_K = 4;
const FIT_PADDING = 1.4;
const FOCUS_SHARE = 2 / 3;

// world-atlas countries-110m: numeric string ids in `id`, name in properties.name.
const topo = worldData as unknown as TopoTopology;
const world = feature(topo as Parameters<typeof feature>[0], topo.objects['countries'] as Parameters<typeof feature>[1]) as unknown as FeatureCollection<
  Geometry,
  { name: string }
>;
const countries: FeatureCollection<Geometry, { name: string }> = { ...world, features: world.features.filter((f) => String(f.id) !== ANTARCTICA) };

const projection = geoNaturalEarth1().fitWidth(VIEW_W, countries);
const pathGen = geoPath(projection);
const VIEW_H = Math.ceil(pathGen.bounds(countries)[1][1]);

function mainBounds(f: Feature<Geometry>): Bounds {
  if (f.geometry.type !== 'MultiPolygon') return pathGen.bounds(f) as Bounds;
  const polygons: Polygon[] = f.geometry.coordinates.map((coordinates) => ({ type: 'Polygon', coordinates }));
  return pathGen.bounds(polygons.reduce((a, b) => (geoArea(b) > geoArea(a) ? b : a))) as Bounds;
}

const shapes = countries.features.map((f) => {
  const alpha2 = numericToAlpha2(String(f.id))?.toUpperCase();
  return { alpha2, d: pathGen(f) ?? undefined, bounds: alpha2 ? mainBounds(f) : null };
});
const boundsByAlpha2 = new Map(shapes.filter((s) => s.alpha2 && s.bounds).map((s) => [s.alpha2 as string, s.bounds as Bounds]));

function clamp(value: number, min: number, max: number) {
  return Math.min(max, Math.max(min, value));
}

function visibleRect(width: number, height: number, slice: boolean): Rect {
  if (!width || !height) return { x: 0, y: 0, w: VIEW_W, h: VIEW_H };
  const scale = slice ? Math.max(width / VIEW_W, height / VIEW_H) : Math.min(width / VIEW_W, height / VIEW_H);
  const w = Math.min(VIEW_W, width / scale);
  const h = Math.min(VIEW_H, height / scale);
  return { x: (VIEW_W - w) / 2, y: (VIEW_H - h) / 2, w, h };
}

// Keep the scaled map covering the visible area (no empty gutters).
function clampView(k: number, x: number, y: number, rect: Rect): View {
  return {
    k,
    x: clamp(x, rect.x + rect.w - VIEW_W * k, rect.x),
    y: clamp(y, rect.y + rect.h - VIEW_H * k, rect.y),
  };
}

function focusBounds(data: CountryDatum[]): Bounds | null {
  const total = data.reduce((sum, d) => sum + d.count, 0);
  if (total <= 0) return null;

  let covered = 0;
  let bounds: Bounds | null = null;
  for (const d of [...data].sort((a, b) => b.count - a.count)) {
    const box = boundsByAlpha2.get(d.country_code.toUpperCase());
    if (box) {
      bounds = bounds
        ? [
            [Math.min(bounds[0][0], box[0][0]), Math.min(bounds[0][1], box[0][1])],
            [Math.max(bounds[1][0], box[1][0]), Math.max(bounds[1][1], box[1][1])],
          ]
        : box;
    }
    covered += d.count;
    if (covered / total >= FOCUS_SHARE) break;
  }
  return bounds;
}

function fitView(bounds: Bounds | null, rect: Rect): View {
  if (!bounds) return clampView(1, 0, 0, rect);
  const [[x0, y0], [x1, y1]] = bounds;
  const k = clamp(Math.min(rect.w / (Math.max(x1 - x0, 1) * FIT_PADDING), rect.h / (Math.max(y1 - y0, 1) * FIT_PADDING)), MIN_K, MAX_FIT_K);
  return clampView(k, rect.x + rect.w / 2 - (k * (x0 + x1)) / 2, rect.y + rect.h / 2 - (k * (y0 + y1)) / 2, rect);
}

interface Props {
  data: CountryDatum[];
  title?: string;
  /** When true, renders the map content without the outer card wrapper so it nests inside another container (e.g. WidgetFrame). */
  bare?: boolean;
}

export default function WorldMap({ data, title, bare }: Props) {
  const { t, locale } = useTranslation();
  const [hover, setHover] = useState<{ code: string; name: string; count: number; x: number; y: number; right: number | null } | null>(null);
  const [viewState, setViewState] = useState<{ data: CountryDatum[]; view: View } | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [size, setSize] = useState({ width: 0, height: 0 });

  const svgRef = useRef<SVGSVGElement>(null);
  const dragFrom = useRef<{ x: number; y: number } | null>(null);

  const slice = !bare;
  const rect = visibleRect(size.width, size.height, slice);
  const focus = useMemo(() => focusBounds(data), [data]);
  const fitted = fitView(focus, rect);
  const userView = viewState?.data === data ? viewState.view : null;
  const view = userView ?? fitted;

  const byAlpha2 = useMemo(() => {
    const m = new Map<string, CountryDatum>();
    for (const d of data) m.set(d.country_code.toUpperCase(), d);
    return m;
  }, [data]);

  const max = useMemo(() => data.reduce((acc, d) => Math.max(acc, d.count), 0), [data]);
  const hasData = max > 0;

  useLayoutEffect(() => {
    const svg = svgRef.current;
    if (!svg) return;
    setSize({ width: svg.clientWidth, height: svg.clientHeight });
    const observer = new ResizeObserver(([entry]) => setSize({ width: entry.contentRect.width, height: entry.contentRect.height }));
    observer.observe(svg);
    return () => observer.disconnect();
  }, []);

  // Convert client coords to the map's viewBox coordinate system.
  function toView(clientX: number, clientY: number) {
    const box = svgRef.current!.getBoundingClientRect();
    return {
      vx: rect.x + ((clientX - box.left) / box.width) * rect.w,
      vy: rect.y + ((clientY - box.top) / box.height) * rect.h,
    };
  }

  // Zoom by `factor` while keeping the point (vx, vy) fixed on screen.
  function zoomAt(vx: number, vy: number, factor: number) {
    const k = clamp(view.k * factor, MIN_K, MAX_K);
    const ratio = k / view.k;
    setViewState({ data, view: clampView(k, vx - (vx - view.x) * ratio, vy - (vy - view.y) * ratio, rect) });
  }

  // Wheel zoom — only when Ctrl/Cmd is held (trackpad pinch also reports
  // ctrlKey), so a plain scroll over the map keeps scrolling the page.
  // Attached natively so we can preventDefault only in the zoom case.
  const onWheel = useEffectEvent((e: WheelEvent) => {
    if (!e.ctrlKey && !e.metaKey) return;
    e.preventDefault();
    const { vx, vy } = toView(e.clientX, e.clientY);
    zoomAt(vx, vy, e.deltaY < 0 ? 1.2 : 1 / 1.2);
  });

  useEffect(() => {
    const svg = svgRef.current;
    if (!svg) return;
    const listener = (e: WheelEvent) => onWheel(e);
    svg.addEventListener('wheel', listener, { passive: false });
    return () => svg.removeEventListener('wheel', listener);
  }, []);

  // Drag to pan.
  const onDragMove = useEffectEvent((e: MouseEvent) => {
    if (!dragFrom.current || !svgRef.current) return;
    const box = svgRef.current.getBoundingClientRect();
    const dx = ((e.clientX - dragFrom.current.x) / box.width) * rect.w;
    const dy = ((e.clientY - dragFrom.current.y) / box.height) * rect.h;
    dragFrom.current = { x: e.clientX, y: e.clientY };
    setViewState((state) => {
      const current = state?.data === data ? state.view : fitted;
      return { data, view: clampView(current.k, current.x + dx, current.y + dy, rect) };
    });
  });

  useEffect(() => {
    if (!isDragging) return;
    const onMove = (e: MouseEvent) => onDragMove(e);
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

  const mapSvg = (svgClass: string) => (
    <svg
      ref={svgRef}
      viewBox={`0 0 ${VIEW_W} ${VIEW_H}`}
      preserveAspectRatio={slice ? 'xMidYMid slice' : 'xMidYMid meet'}
      className={`${svgClass} select-none ${isDragging ? 'cursor-grabbing' : 'cursor-grab'}`}
      role="img"
      aria-label={title ?? t('common.map.title')}
      onMouseDown={startDrag}
    >
      <g transform={`translate(${view.x} ${view.y}) scale(${view.k})`}>
        {shapes.map((shape, i) => {
          const datum = shape.alpha2 ? byAlpha2.get(shape.alpha2) : undefined;
          return (
            <path
              key={i}
              d={shape.d}
              className="stroke-[color:var(--surface)]"
              strokeWidth={0.4}
              vectorEffect="non-scaling-stroke"
              fill={datum ? scaleFill(datum.count, max) : NO_DATA}
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

  const centre = () => ({ vx: rect.x + rect.w / 2, vy: rect.y + rect.h / 2 });

  const controls = (
    <div className="absolute top-2 right-2 flex flex-col gap-1">
      <button type="button" className={zoomBtn} aria-label={t('common.map.zoom_in')} title={t('common.map.zoom_in')} onClick={() => zoomAt(centre().vx, centre().vy, 1.4)}>
        <Plus className="h-4 w-4" />
      </button>
      <button type="button" className={zoomBtn} aria-label={t('common.map.zoom_out')} title={t('common.map.zoom_out')} onClick={() => zoomAt(centre().vx, centre().vy, 1 / 1.4)}>
        <Minus className="h-4 w-4" />
      </button>
      <button type="button" className={zoomBtn} aria-label={t('common.map.reset')} title={t('common.map.reset')} disabled={userView === null} onClick={() => setViewState(null)}>
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
        {mapSvg('block h-[260px] w-full sm:h-[340px]')}
        {controls}
        {hoverTip}
      </div>
      {hasData && <ScaleLegend className="mt-4" />}
    </div>
  );
}
