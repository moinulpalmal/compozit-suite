/**
 * Places a floating element next to an anchor, clamped to the viewport.
 *
 * Extracted from `components/ui/dropdown-menu.tsx` when `components/ui/combobox.tsx`
 * needed the identical behaviour. Both render into the top layer with the Popover
 * API, which escapes `overflow: hidden` without a portal but supplies no
 * positioning of its own — so the maths lives here rather than in two places.
 *
 * Placement is computed in JS rather than with CSS anchor positioning: it needs no
 * support guard, and clamping to the viewport replaces the collision handling a
 * positioning library would otherwise be pulled in for.
 */

export type Side = 'top' | 'right' | 'bottom' | 'left';
export type Align = 'start' | 'center' | 'end';

/** Keeps a floating element this far from the viewport edge. */
export const VIEWPORT_MARGIN = 8;

/** Default gap between the anchor and the floating element. */
export const ANCHOR_GAP = 4;

type PositionOptions = {
    side?: Side;
    align?: Align;
    sideOffset?: number;
    /**
     * Custom property set to the anchor's width, so the floating element can
     * match it in CSS — `w-(--dropdown-trigger-width)`, as `nav-user.tsx` does.
     */
    widthVar?: string;
};

/**
 * Position `floating` relative to `anchor`, flipping to the opposite side when
 * the preferred one has no room and the opposite one does.
 */
export function positionAnchored(
    anchor: HTMLElement,
    floating: HTMLElement,
    {
        side = 'bottom',
        align = 'start',
        sideOffset = ANCHOR_GAP,
        widthVar = '--anchor-width',
    }: PositionOptions = {},
): void {
    const anchorRect = anchor.getBoundingClientRect();
    const floatingRect = floating.getBoundingClientRect();

    floating.style.setProperty(widthVar, `${anchorRect.width}px`);

    let top: number;
    let left: number;

    if (side === 'top' || side === 'bottom') {
        top =
            side === 'bottom'
                ? anchorRect.bottom + sideOffset
                : anchorRect.top - floatingRect.height - sideOffset;

        left =
            align === 'end'
                ? anchorRect.right - floatingRect.width
                : align === 'center'
                  ? anchorRect.left + (anchorRect.width - floatingRect.width) / 2
                  : anchorRect.left;

        // Flip when the preferred side has no room but the opposite one does.
        if (
            side === 'bottom' &&
            top + floatingRect.height > window.innerHeight - VIEWPORT_MARGIN &&
            anchorRect.top - floatingRect.height - sideOffset > VIEWPORT_MARGIN
        ) {
            top = anchorRect.top - floatingRect.height - sideOffset;
        } else if (
            side === 'top' &&
            top < VIEWPORT_MARGIN &&
            anchorRect.bottom + floatingRect.height + sideOffset <
                window.innerHeight - VIEWPORT_MARGIN
        ) {
            top = anchorRect.bottom + sideOffset;
        }
    } else {
        left =
            side === 'right'
                ? anchorRect.right + sideOffset
                : anchorRect.left - floatingRect.width - sideOffset;

        top =
            align === 'end'
                ? anchorRect.bottom - floatingRect.height
                : align === 'center'
                  ? anchorRect.top +
                    (anchorRect.height - floatingRect.height) / 2
                  : anchorRect.top;

        if (
            side === 'right' &&
            left + floatingRect.width > window.innerWidth - VIEWPORT_MARGIN &&
            anchorRect.left - floatingRect.width - sideOffset > VIEWPORT_MARGIN
        ) {
            left = anchorRect.left - floatingRect.width - sideOffset;
        } else if (
            side === 'left' &&
            left < VIEWPORT_MARGIN &&
            anchorRect.right + floatingRect.width + sideOffset <
                window.innerWidth - VIEWPORT_MARGIN
        ) {
            left = anchorRect.right + sideOffset;
        }
    }

    floating.style.left = `${clamp(left, floatingRect.width, window.innerWidth)}px`;
    floating.style.top = `${clamp(top, floatingRect.height, window.innerHeight)}px`;
}

/** Keep a coordinate inside the viewport, allowing for the element's own size. */
function clamp(value: number, size: number, viewport: number): number {
    const max = Math.max(VIEWPORT_MARGIN, viewport - size - VIEWPORT_MARGIN);

    return Math.min(Math.max(VIEWPORT_MARGIN, value), max);
}
