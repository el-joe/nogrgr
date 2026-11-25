/**
 * Opens a popover above its field when there is not enough room below, measured
 * against the nearest scrolling ancestor (a sheet panel) as well as the viewport.
 */
function scrollParent(node) {
    for (let parent = node.parentElement; parent; parent = parent.parentElement) {
        const { overflowY } = getComputedStyle(parent);

        if (overflowY === 'auto' || overflowY === 'scroll') {
            return parent;
        }
    }

    return null;
}

export function placement(anchor, needed = 280) {
    const rect = anchor.getBoundingClientRect();
    const frame = scrollParent(anchor)?.getBoundingClientRect();
    const bottom = Math.min(window.innerHeight, frame?.bottom ?? Infinity);
    const top = Math.max(0, frame?.top ?? 0);
    const below = bottom - rect.bottom;
    const above = rect.top - top;

    return below < needed && above > below ? 'top' : 'bottom';
}
