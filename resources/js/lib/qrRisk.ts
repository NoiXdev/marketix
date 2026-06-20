import { QrStyle, QrType } from '@/data/qrTypes';

export interface QrEditState {
  type: QrType;
  is_dynamic: boolean;
  domain_id: string | '';
  slug: string;
  content: Record<string, string>;
  style: QrStyle;
}

/**
 * True when saving `next` would change the scannable image of `original`,
 * invalidating any already-printed codes. The only safe change to a dynamic
 * QR is its redirect target (content) — the encoded short link is unchanged.
 */
export function isRiskyEdit(original: QrEditState, next: QrEditState): boolean {
  if (original.is_dynamic !== next.is_dynamic) return true; // mode switch
  if (original.type !== next.type) return true;              // payload kind changes
  if (JSON.stringify(original.style) !== JSON.stringify(next.style)) return true; // re-render

  if (next.is_dynamic) {
    // Image encodes the short link; changing it (domain/slug) breaks printed codes.
    return original.domain_id !== next.domain_id || original.slug !== next.slug;
  }

  // Static: the image encodes the content directly.
  return JSON.stringify(original.content) !== JSON.stringify(next.content);
}
