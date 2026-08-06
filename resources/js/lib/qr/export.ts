import { jsPDF } from 'jspdf';
import 'svg2pdf.js';

/** Wrap an SVG string in a Blob (synchronous). */
export function toSvgBlob(svg: string): Blob {
  return new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
}

/** Trigger a browser download of a Blob via a transient object URL. */
export function downloadBlob(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  // Revoke on the next tick so the download has a chance to start.
  setTimeout(() => URL.revokeObjectURL(url), 0);
}

/** Load an SVG string as an <img> element (resolves once decoded). */
function svgToImage(svg: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error('Failed to load SVG image'));
    // Encode as a UTF-8-safe data URL (handles unicode in frame text).
    img.src =
      'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  });
}

/**
 * Rasterise an SVG to a PNG Blob on a canvas sized `width*scale × height*scale`.
 * Rejects if the environment provides no 2d canvas context (e.g. jsdom).
 */
export async function toPngBlob(
  svg: string,
  width: number,
  height: number,
  scale: number,
): Promise<Blob> {
  const img = await svgToImage(svg);
  const canvas = document.createElement('canvas');
  canvas.width = Math.round(width * scale);
  canvas.height = Math.round(height * scale);
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('Canvas 2D context unavailable');
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (blob) resolve(blob);
      else reject(new Error('Canvas toBlob returned null'));
    }, 'image/png');
  });
}

/**
 * Produce a vector PDF from the SVG using jsPDF + svg2pdf.js. The page is
 * sized to the SVG's pixel dimensions (treated as PDF points); orientation
 * follows the aspect ratio.
 */
export async function toPdfBlob(
  svg: string,
  width: number,
  height: number,
): Promise<Blob> {
  const doc = new jsPDF({
    unit: 'pt',
    format: [width, height],
    orientation: width >= height ? 'landscape' : 'portrait',
  });
  const el = new DOMParser().parseFromString(
    svg,
    'image/svg+xml',
  ).documentElement as unknown as SVGElement;
  await doc.svg(el, { x: 0, y: 0, width, height });
  return doc.output('blob');
}
