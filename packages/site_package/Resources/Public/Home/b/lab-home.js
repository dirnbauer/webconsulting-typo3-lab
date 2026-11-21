/* Start page proposal B: "Layer Stack" (see lab-home.css).

   What this script adds to the CSS-only deck:
   1. Measures: the header height and each sheet's height, so a sheet knows
      where to pin (kept current with a ResizeObserver).
   2. Timelines: names a view timeline on every sheet and points each
      sheet's step-back animations at the next two sheets.
   3. The layer index: a list of links to the layers' anchors.
   4. Focus: a keyboard focus that lands under a covering sheet scrolls the
      page so the focused element shows (sticky stacks hide it otherwise).

   It only reads and writes custom properties, data attributes and inline
   CSSOM styles (no inline <style> or <script>, so the CSP stays quiet), and
   it changes no text or order of the content. */
(function () {
    'use strict';

    var root = document.documentElement;
    if (root.getAttribute('data-lab-home') !== 'b') {
        return;
    }

    var LABELS = {
        en: 'Layers of this page',
        de: 'Ebenen dieser Seite',
        zh: '本页的层级',
        hu: 'Az oldal rétegei'
    };

    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn, { once: true });
        } else {
            fn();
        }
    }

    onReady(function () {
        if (window.performance && performance.mark) {
            performance.mark('lsb-init-start');
        }
        var stack = document.querySelector('.desiderio-main > .desiderio-corporate-start');
        if (!stack) {
            return;
        }
        var layers = Array.prototype.filter.call(stack.children, function (el) {
            return el.matches('section.desiderio-section');
        });
        if (layers.length < 2) {
            return;
        }
        var header = document.querySelector('[data-d-site-header]');
        var motionQuery = window.matchMedia('(prefers-reduced-motion: no-preference)');
        var total = String(layers.length).padStart(2, '0');

        /* 1. Measures ---------------------------------------------------- */

        var heights = [];
        var headerHeight = 0;

        function measure() {
            headerHeight = header ? Math.round(header.getBoundingClientRect().height) : 0;
            if (headerHeight > 0) {
                root.style.setProperty('--lsb-header', headerHeight + 'px');
            }
            layers.forEach(function (layer, i) {
                var h = layer.offsetHeight;
                if (heights[i] !== h) {
                    heights[i] = h;
                    layer.style.setProperty('--lsb-h', h + 'px');
                }
            });
            flowTops = null;
        }

        /* 2. Timelines --------------------------------------------------- */

        var names = layers.map(function (_, i) { return '--lsb-layer-' + i; });
        stack.style.setProperty('timeline-scope', names.join(', '));
        /* A sheet without a next (or next-but-one) sheet gets no step
           animation at all: Chrome renders an animation whose timeline is
           "none" in its end state. */
        layers.forEach(function (layer, i) {
            var next = names[i + 1];
            var after = names[i + 2];
            layer.style.setProperty('view-timeline-name', names[i]);
            layer.style.setProperty('animation-name', (next ? 'lsb-step-1' : 'none') + ', ' + (after ? 'lsb-step-2' : 'none'));
            layer.style.setProperty('animation-timeline', (next || 'auto') + ', ' + (after || 'auto'));
            layer.setAttribute('data-lsb-of', total);
            var container = layer.querySelector(':scope > .desiderio-container');
            if (container) {
                container.style.setProperty('animation-name', next ? 'lsb-hush' : 'none');
                container.style.setProperty('animation-timeline', next || 'auto');
            }
        });

        /* Where each sheet starts in the flow (sticky offsets never move the
           flow), as a document coordinate. */
        var flowTops = null;

        function getFlowTops() {
            if (flowTops) {
                return flowTops;
            }
            /* The #c<uid> anchor before each sheet stays in the flow. */
            var y = stack.getBoundingClientRect().top + window.scrollY;
            flowTops = layers.map(function (layer, i) {
                var anchor = layer.previousElementSibling;
                var top = anchor && anchor.matches('a[id]')
                    ? anchor.getBoundingClientRect().top + window.scrollY
                    : y;
                y = top + (heights[i] || layer.offsetHeight);
                return top;
            });
            return flowTops;
        }

        function pinLine() {
            var v = parseFloat(getComputedStyle(root).getPropertyValue('scroll-padding-top'));
            return isNaN(v) ? headerHeight + 48 : v;
        }

        /* 3. The layer index -------------------------------------------- */

        var lang = (root.getAttribute('lang') || 'en').slice(0, 2).toLowerCase();
        var nav = document.createElement('nav');
        nav.className = 'lsb-index';
        nav.setAttribute('aria-label', LABELS[lang] || LABELS.en);
        var list = document.createElement('ol');
        var links = layers.map(function (layer, i) {
            var anchor = layer.previousElementSibling;
            var id = anchor && anchor.matches('a[id]') ? anchor.id : '';
            var heading = layer.querySelector('h2, h3');
            var text = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
            var item = document.createElement('li');
            var link = document.createElement('a');
            link.href = id ? '#' + id : '#main-content';
            var number = document.createElement('span');
            number.className = 'lsb-index__n';
            number.setAttribute('aria-hidden', 'true');
            number.textContent = String(i + 1).padStart(2, '0');
            var label = document.createElement('span');
            label.className = 'lsb-index__label';
            label.textContent = text || number.textContent;
            link.appendChild(number);
            link.appendChild(label);
            link.addEventListener('click', function (event) {
                /* Pin the sheet exactly at its pin line; keep #home=… in the
                   address bar. Keyboard users get the focus moved into the
                   layer, as a fragment link would do. */
                event.preventDefault();
                var top = Math.max(0, i === 0 ? 0 : getFlowTops()[i] - pinLine() + 1);
                window.scrollTo({ top: top, behavior: motionQuery.matches ? 'smooth' : 'auto' });
                if (heading) {
                    if (!heading.hasAttribute('tabindex')) {
                        heading.setAttribute('tabindex', '-1');
                    }
                    heading.focus({ preventScroll: true });
                }
            });
            item.appendChild(link);
            list.appendChild(item);
            return link;
        });
        nav.appendChild(list);
        stack.insertAdjacentElement('afterend', nav);

        var current = -1;

        function updateCurrent() {
            var tops = getFlowTops();
            var line = window.scrollY + pinLine() + 2;
            var index = 0;
            for (var i = 0; i < tops.length; i++) {
                if (tops[i] <= line) {
                    index = i;
                }
            }
            /* At the end of the page the last sheets cannot reach the pin
               line; the bottom of the page means the last layer. */
            if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4) {
                index = tops.length - 1;
            }
            if (index !== current) {
                if (links[current]) {
                    links[current].removeAttribute('aria-current');
                }
                links[index].setAttribute('aria-current', 'location');
                current = index;
            }
        }

        var ticking = false;
        window.addEventListener('scroll', function () {
            if (ticking) {
                return;
            }
            ticking = true;
            window.requestAnimationFrame(function () {
                ticking = false;
                updateCurrent();
            });
        }, { passive: true });

        /* 4. Focus under a covering sheet ------------------------------- */

        document.addEventListener('focusin', function (event) {
            if (!motionQuery.matches) {
                return;
            }
            var el = event.target;
            if (!(el instanceof Element) || el === document.body) {
                return;
            }
            var i = layers.findIndex(function (layer) { return layer.contains(el); });
            if (i < 0) {
                return;
            }
            var r = el.getBoundingClientRect();
            if (r.width === 0 && r.height === 0) {
                return;
            }
            var pin = pinLine();
            var x = Math.min(Math.max(r.left + Math.min(r.width / 2, 24), 1), window.innerWidth - 1);
            var y = Math.min(Math.max(r.top + Math.min(r.height / 2, 12), 1), window.innerHeight - 1);
            var hit = document.elementFromPoint(x, y);
            var shown = hit && (hit === el || el.contains(hit) || hit.contains(el)) &&
                r.top >= pin - 8 && r.bottom <= window.innerHeight;
            if (shown) {
                return;
            }
            /* Scroll to the element's place in the flow, or, if that is
               further down than the moment its sheet pins, to that moment:
               then the sheet is whole on screen and nothing covers it. */
            var tops = getFlowTops();
            var layerRect = layers[i].getBoundingClientRect();
            var within = r.top - layerRect.top;
            var stickTop = Math.min(i === 0 ? headerHeight : pin, window.innerHeight - heights[i]);
            var atElement = tops[i] + within - pin - 16;
            var atPin = tops[i] - stickTop;
            window.scrollTo({ top: Math.max(0, Math.min(atElement, atPin)), behavior: 'auto' });
        });

        /* Start ------------------------------------------------------------ */

        measure();
        updateCurrent();
        root.setAttribute('data-lsb-on', '');
        if (window.performance && performance.measure) {
            performance.measure('lsb-init', 'lsb-init-start');
        }

        if ('ResizeObserver' in window) {
            var pending = false;
            var observer = new ResizeObserver(function () {
                if (pending) {
                    return;
                }
                pending = true;
                window.requestAnimationFrame(function () {
                    pending = false;
                    measure();
                    updateCurrent();
                });
            });
            layers.forEach(function (layer) { observer.observe(layer); });
            if (header) {
                observer.observe(header);
            }
        } else {
            window.addEventListener('resize', function () { measure(); updateCurrent(); });
        }
        window.addEventListener('load', function () { measure(); updateCurrent(); });
    });
})();
