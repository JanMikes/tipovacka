/*
 * Cursor spotlight for card surfaces.
 *
 * Tracks the pointer and writes its position (relative to each card) into the
 * `--mx`/`--my` CSS custom properties. The "Cursor spotlight" section in
 * styles/app.css uses them to paint a soft inner glow (:hover-gated) and a lit
 * border segment that follow the mouse.
 *
 * PROXIMITY mode (Hyperplexed-style): borders of cards NEAR the cursor light up
 * before hover, scaled by distance via `--spot-o` (0..1). Flip the constant to
 * false to fall back to the hover-only behavior — no other change needed.
 *
 * Keep SELECTOR in sync with the CSS selector lists.
 */
const SELECTOR = '.card, .card-glass, .tip-card, .tip-row, .stat, .option-card, .variant-card, .spotlight';
const PROXIMITY = true;
const PROXIMITY_RADIUS = 300; // px from a card's edge where its border starts to light up

if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    let frame = 0;
    let pointer = null;
    const lit = new Set();

    const schedule = () => {
        if (!frame && pointer) {
            frame = requestAnimationFrame(apply);
        }
    };

    const applyProximity = () => {
        const seen = new Set();
        for (const card of document.querySelectorAll(SELECTOR)) {
            const rect = card.getBoundingClientRect();
            if (rect.width === 0) {
                continue;
            }
            const dx = Math.max(rect.left - pointer.x, 0, pointer.x - rect.right);
            const dy = Math.max(rect.top - pointer.y, 0, pointer.y - rect.bottom);
            const distance = Math.hypot(dx, dy);
            if (distance >= PROXIMITY_RADIUS) {
                continue;
            }
            card.style.setProperty('--mx', `${pointer.x - rect.left}px`);
            card.style.setProperty('--my', `${pointer.y - rect.top}px`);
            card.style.setProperty('--spot-o', (1 - distance / PROXIMITY_RADIUS).toFixed(3));
            seen.add(card);
            lit.add(card);
        }
        for (const card of lit) {
            if (!seen.has(card)) {
                card.style.setProperty('--spot-o', '0');
                lit.delete(card);
            }
        }
    };

    const applyHovered = () => {
        // Hover-only: position vars on the hovered card and any card ancestors
        // (e.g. a .stat grid inside a .card), which are also :hover at that point.
        let card = pointer.target instanceof Element ? pointer.target.closest(SELECTOR) : null;
        while (card) {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${pointer.x - rect.left}px`);
            card.style.setProperty('--my', `${pointer.y - rect.top}px`);
            card = card.parentElement ? card.parentElement.closest(SELECTOR) : null;
        }
    };

    const apply = () => {
        frame = 0;
        if (PROXIMITY) {
            applyProximity();
        } else {
            applyHovered();
        }
    };

    if (PROXIMITY) {
        document.documentElement.classList.add('spotlight-prox');
    }

    document.addEventListener('pointermove', (event) => {
        pointer = { x: event.clientX, y: event.clientY, target: event.target };
        schedule();
    }, { passive: true });

    // Card rects shift under a stationary cursor while scrolling — recompute.
    document.addEventListener('scroll', schedule, { passive: true, capture: true });

    document.addEventListener('mouseleave', () => {
        pointer = null;
        for (const card of lit) {
            card.style.setProperty('--spot-o', '0');
        }
        lit.clear();
    });
}
