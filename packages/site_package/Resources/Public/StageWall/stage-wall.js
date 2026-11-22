/*
 * The Desiderio start page "Stage and Wall": its only script.
 *
 * Everything that makes the page is markup and CSS (stage-wall.css). In dark
 * mode, on a fine pointer, the soft stage light follows the pointer across
 * the stage. Nothing under prefers-reduced-motion, on touch, inside the
 * Visual Editor's content area, or when the lab's start page switch shows
 * another version (data-lab-home on the html element).
 */
(() => {
    'use strict';

    const start = () => {
        const wall = document.querySelector('div.desiderio-stage-wall[data-sw-stage]:not([data-sw-stage="none"])');
        if (!wall) {
            return;
        }
        const root = document.documentElement;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
        const fine = window.matchMedia('(hover: hover) and (pointer: fine)');
        const firstTile = wall.querySelector(':scope > [data-sw-lead="first"]');

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
            wall.style.setProperty('--sw-mx', `${x.toFixed(1)}%`);
            wall.style.setProperty('--sw-my', `${Math.max(0, y).toFixed(1)}%`);
        };

        const reset = () => {
            wall.style.removeProperty('--sw-mx');
            wall.style.removeProperty('--sw-my');
        };

        wall.addEventListener('pointermove', (event) => {
            if (reduce.matches || !fine.matches || event.pointerType !== 'mouse'
                || !root.classList.contains('dark') || root.hasAttribute('data-lab-home')) {
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
