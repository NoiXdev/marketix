import type { PointerEvent } from 'react';

/**
 * Pointer handlers that report the chart slot under the pointer, so values can
 * be scrubbed with a finger as well as a mouse. Slots are marked with a
 * `data-slot` attribute; give the container `touch-pan-y` so vertical page
 * scrolling keeps working on touch screens.
 */
export function scrubHandlers(onSlot: (slot: string) => void) {
  function pick(e: PointerEvent<HTMLElement>) {
    const slot = document.elementFromPoint(e.clientX, e.clientY)?.closest<HTMLElement>('[data-slot]');
    if (slot && e.currentTarget.contains(slot)) onSlot(slot.dataset.slot!);
  }

  return { onPointerDown: pick, onPointerMove: pick };
}
