/*
 * Start page proposal A, "Stage and Wall": the only script of the proposal.
 *
 * Everything that makes the proposal is CSS (lab-home.css); without this
 * file the page is complete. The script does two things:
 * 1. Guard: the layout places the 17 start page elements by position. If the
 *    element sequence differs from the one it was made for, the proposal
 *    switches itself off (removes data-lab-home), and the page renders as
 *    before.
 * 2. In dark mode, on a fine pointer, the soft stage light follows the
 *    pointer across the stage. Nothing under prefers-reduced-motion or touch.
 */
(() => {
    'use strict';

    if (document.documentElement.getAttribute('data-lab-home') !== 'a') {
        return;
    }

    const EXPECTED = [
        'hero', 'feature-video', 'feature-cards', 'feature-alternating', 'feature-cards',
        'cta-inline', 'feature-cards', 'feature-cards', 'gallery', 'use-case-grid',
        'pricing-three-tier', 'feature-checklist', 'cta-inline', 'category-cards',
        'cta-inline', 'how-to-steps', 'cta-banner',
    ];

    const start = () => {
        const wall = document.querySelector('.desiderio-corporate-start');
        const sections = wall ? [...wall.querySelectorAll(':scope > section')] : [];
        const matches = sections.length === EXPECTED.length
            && sections.every((section, i) => section.classList.contains(EXPECTED[i]));
        if (!matches) {
            document.documentElement.removeAttribute('data-lab-home');
            return;
        }
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
        const fine = window.matchMedia('(hover: hover) and (pointer: fine)');
        const firstTile = wall.querySelector(':scope > section:nth-of-type(3)');

        let frame = 0;
        let lastX = 0;
        let lastY = 0;

        const paint = () => {
            frame = 0;
            const box = wall.getBoundingClientRect();
            const stageBottom = firstTile ? firstTile.getBoundingClientRect().top : box.top + 900;
            const height = Math.max(1, stageBottom - box.top);
            if (lastY > stageBottom) {
                return;
            }
            const x = ((lastX - box.left) / Math.max(1, box.width)) * 100;
            const y = ((lastY - box.top) / height) * 100;
            wall.style.setProperty('--lab-mx', `${x.toFixed(1)}%`);
            wall.style.setProperty('--lab-my', `${Math.max(0, y).toFixed(1)}%`);
        };

        const reset = () => {
            wall.style.removeProperty('--lab-mx');
            wall.style.removeProperty('--lab-my');
        };

        wall.addEventListener('pointermove', (event) => {
            if (reduce.matches || !fine.matches || event.pointerType !== 'mouse') {
                return;
            }
            lastX = event.clientX;
            lastY = event.clientY;
            if (!frame) {
                frame = requestAnimationFrame(paint);
            }
        }, { passive: true });
        wall.addEventListener('pointerleave', reset);
        reduce.addEventListener('change', reset);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
