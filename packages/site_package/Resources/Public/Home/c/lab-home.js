/*
 * Start page proposal C, "Preset Theatre" (lab only, /#home=c).
 *
 * Adds a lighting desk to the hero and a small dock that follow the visitor:
 * both switch the page's own theme preset the way the site does it
 * (body[data-shadcn-preset]); every preset is already in the site bundle, so
 * a switch loads no stylesheet. The swatches use the generated element-scoped
 * presets (preset-samples.css, the /themes mechanism), loaded once.
 *
 * The choice is play, not a setting: it lives in this document only. Nothing
 * is stored, so every other page and every new visit shows the site's look.
 * Without JS the page is the complete start page.
 */
(() => {
    'use strict';

    const html = document.documentElement;
    if (html.dataset.labHome !== 'c') {
        return;
    }

    const PRESETS = [
        ['b0', 'Default neutral'],
        ['b27GcrRo', 'Rhea modern neutral'],
        ['b6G5977cw', 'Lyra mono olive'],
        ['b4hb38Fyj', 'Olive product system'],
        ['b3IWPgRwnI', 'Mist dashboard'],
        ['aurora', 'Aurora'],
        ['marine', 'Marine'],
        ['forest', 'Forest'],
        ['ember', 'Ember'],
        ['bloom', 'Bloom'],
        ['lagoon', 'Lagoon'],
        ['gold', 'Gold'],
        ['midnight', 'Midnight'],
        ['blossom', 'Blossom'],
        ['citrus', 'Citrus'],
    ];

    const STRINGS = {
        en: {
            label: 'Pick a theme preset. The whole page changes.',
            group: 'Theme presets',
            now: 'On stage:',
            note: 'Only for this visit. Nothing is saved.',
            own: '{name}, the look of this site',
            live: 'The page now shows the {name} preset.',
            prev: 'Previous preset',
            next: 'Next preset',
            reset: 'Original',
            resetLabel: 'Back to the look of this site',
            dock: 'Theme preset of this page',
        },
        de: {
            label: 'Wählen Sie ein Theme-Preset. Die ganze Seite ändert sich.',
            group: 'Theme-Presets',
            now: 'Auf der Bühne:',
            note: 'Nur für diesen Besuch. Nichts wird gespeichert.',
            own: '{name}, das Aussehen dieser Website',
            live: 'Die Seite zeigt jetzt das Preset {name}.',
            prev: 'Vorheriges Preset',
            next: 'Nächstes Preset',
            reset: 'Original',
            resetLabel: 'Zurück zum Aussehen dieser Website',
            dock: 'Theme-Preset dieser Seite',
        },
        zh: {
            label: '选择一个主题预设，整个页面随之改变。',
            group: '主题预设',
            now: '当前预设：',
            note: '仅限本次访问，不会保存。',
            own: '{name}（本网站的外观）',
            live: '页面现在显示 {name} 预设。',
            prev: '上一个预设',
            next: '下一个预设',
            reset: '原始外观',
            resetLabel: '恢复本网站的外观',
            dock: '本页的主题预设',
        },
        hu: {
            label: 'Válasszon téma-előbeállítást. Az egész oldal megváltozik.',
            group: 'Téma-előbeállítások',
            now: 'Most a színpadon:',
            note: 'Csak erre a látogatásra. Semmit sem mentünk.',
            own: '{name}, a webhely saját megjelenése',
            live: 'Az oldal most a(z) {name} előbeállítást mutatja.',
            prev: 'Előző előbeállítás',
            next: 'Következő előbeállítás',
            reset: 'Eredeti',
            resetLabel: 'Vissza a webhely saját megjelenéséhez',
            dock: 'Az oldal téma-előbeállítása',
        },
    };

    const lang = (html.lang || 'en').slice(0, 2).toLowerCase();
    const t = STRINGS[lang] || STRINGS.en;
    const fill = (s, name) => s.replace('{name}', name);
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const el = (tag, attrs = {}, children = []) => {
        const node = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (v === false || v == null) continue;
            if (k === 'text') node.textContent = v;
            else node.setAttribute(k, v === true ? '' : v);
        }
        for (const c of children) node.append(c);
        return node;
    };

    const icon = (d) => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', d);
        svg.append(path);
        return svg;
    };

    /* preset-samples.css: one stylesheet with all fifteen element-scoped
       presets, next to preset-corporate.css in Desiderio's public assets. */
    const loadSamples = () => {
        const ref = document.querySelector('link[rel="stylesheet"][href*="/Css/preset-corporate.css"]');
        if (!ref) return;
        const href = ref.getAttribute('href').replace(/preset-corporate\.css(\?.*)?$/, 'preset-samples.css$1');
        const link = el('link', { rel: 'stylesheet', href });
        link.addEventListener('load', () => html.classList.add('lh-samples'), { once: true });
        document.head.append(link);
    };

    const swatch = (id) => el('span', { class: 'lh-chip__swatch', 'data-shadcn-preset-sample': id, 'aria-hidden': 'true' });

    const init = () => {
        const body = document.body;
        const hero = document.querySelector('.desiderio-corporate-start .hero--split');
        const content = hero && hero.querySelector('.hero__content');
        const media = hero && hero.querySelector('.hero__media');
        if (!body || !content) return;

        const own = body.dataset.shadcnPreset || 'b0';
        const order = PRESETS.slice().sort((a, b) => (a[0] === own ? -1 : b[0] === own ? 1 : 0));
        if (!order.some(([id]) => id === own)) order.unshift([own, own]);
        const nameOf = (id) => (order.find(([p]) => p === id) || [id, id])[1];
        let current = own;

        loadSamples();

        /* --- the stage bar ------------------------------------------- */
        let stageName = null;
        if (media) {
            stageName = el('span', { class: 'lh-stagebar__name' });
            media.append(el('div', { class: 'lh-stagebar', 'aria-hidden': 'true' }, [
                stageName,
                el('span', { class: 'lh-stagebar__type', text: 'Aa' }),
                el('span', { class: 'lh-stagebar__radius' }),
            ]));
        }

        /* --- the desk ------------------------------------------------- */
        const labelId = 'lh-desk-label';
        const rail = el('div', { class: 'lh-desk__rail', role: 'radiogroup', 'aria-labelledby': labelId });
        const chips = order.map(([id, name]) => {
            const chip = el('button', {
                type: 'button',
                class: 'lh-chip',
                role: 'radio',
                'aria-checked': id === current ? 'true' : 'false',
                tabindex: id === current ? '0' : '-1',
                'aria-label': id === own ? fill(t.own, name) : name,
                title: name,
                'data-lh-preset': id,
            }, [swatch(id)]);
            rail.append(chip);
            return chip;
        });
        const nowName = el('strong');
        const live = el('p', { class: 'lh-sr', 'aria-live': 'polite' });
        const desk = el('div', { class: 'lh-desk', role: 'group', 'aria-label': t.group }, [
            el('p', { class: 'lh-desk__label', id: labelId, text: t.label }),
            rail,
            el('p', { class: 'lh-desk__now' }, [
                el('span', {}, [el('span', { class: 'lh-sr', text: t.now + ' ' }), nowName]),
                el('span', { class: 'lh-desk__dot', 'aria-hidden': 'true', text: '·' }),
                el('span', { text: t.note }),
            ]),
            live,
        ]);
        content.append(desk);

        /* --- the dock ------------------------------------------------- */
        const dockSwatch = swatch(current);
        const dockName = el('span', { class: 'lh-dock__name' });
        const prev = el('button', { type: 'button', class: 'lh-dock__btn', 'aria-label': t.prev, title: t.prev }, [icon('m15 18-6-6 6-6')]);
        const next = el('button', { type: 'button', class: 'lh-dock__btn', 'aria-label': t.next, title: t.next }, [icon('m9 18 6-6-6-6')]);
        const reset = el('button', { type: 'button', class: 'lh-dock__btn lh-dock__reset', 'aria-label': t.resetLabel, text: t.reset, hidden: true });
        const dock = el('div', { class: 'lh-dock', role: 'group', 'aria-label': t.dock, 'data-open': 'false', inert: true }, [
            prev,
            el('span', { class: 'lh-dock__now' }, [dockSwatch, dockName]),
            next,
            reset,
        ]);
        body.append(dock);

        /* --- switching ------------------------------------------------ */
        const paint = (id) => {
            current = id;
            body.dataset.shadcnPreset = id;
            const name = nameOf(id);
            chips.forEach((chip) => {
                const on = chip.dataset.lhPreset === id;
                chip.setAttribute('aria-checked', on ? 'true' : 'false');
                chip.tabIndex = on ? 0 : -1;
            });
            nowName.textContent = name;
            dockName.textContent = name;
            dockSwatch.dataset.shadcnPresetSample = id;
            if (stageName) stageName.textContent = 'preset · ' + name;
            reset.hidden = id === own;
        };

        const choose = (id, announce = true) => {
            if (id === current) return;
            const run = () => paint(id);
            if (!reduceMotion.matches && typeof document.startViewTransition === 'function') {
                document.startViewTransition(run);
            } else {
                run();
            }
            if (announce) live.textContent = fill(t.live, nameOf(id));
        };

        const step = (delta) => {
            const i = order.findIndex(([id]) => id === current);
            return order[(i + delta + order.length) % order.length][0];
        };

        rail.addEventListener('click', (e) => {
            const chip = e.target.closest('.lh-chip');
            if (chip) choose(chip.dataset.lhPreset);
        });

        /* radio group keys: arrows move and select, Home/End jump */
        rail.addEventListener('keydown', (e) => {
            let id = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') id = step(1);
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') id = step(-1);
            else if (e.key === 'Home') id = order[0][0];
            else if (e.key === 'End') id = order[order.length - 1][0];
            if (!id) return;
            e.preventDefault();
            choose(id);
            const chip = chips.find((c) => c.dataset.lhPreset === id);
            if (chip) chip.focus();
        });

        prev.addEventListener('click', () => choose(step(-1)));
        next.addEventListener('click', () => choose(step(1)));
        reset.addEventListener('click', () => {
            choose(own);
            next.focus();
        });

        paint(own);

        /* --- the dock shows while the stage is out of view, and steps
               aside for the closing banner and the footer --------------- */
        if ('IntersectionObserver' in window) {
            const seen = new Map();
            const finale = document.querySelector('.desiderio-corporate-start .cta-banner');
            const footer = document.querySelector('footer');
            const watched = [hero, finale, footer].filter(Boolean);
            const update = () => {
                const open = watched.every((node) => !seen.get(node));
                if (!open && dock.contains(document.activeElement)) {
                    return; /* never pull the dock away from under the keyboard */
                }
                dock.dataset.open = open ? 'true' : 'false';
                dock.inert = !open;
            };
            const io = new IntersectionObserver((entries) => {
                entries.forEach((entry) => seen.set(entry.target, entry.isIntersecting));
                update();
            }, { threshold: 0 });
            watched.forEach((node) => { seen.set(node, node === hero); io.observe(node); });
            dock.addEventListener('focusout', () => setTimeout(update, 0));
        }

        /* --- scene 7: the library counts become focal numerals -------- */
        document.querySelectorAll('.desiderio-corporate-start .feature-cards__grid').forEach((grid) => {
            const titles = [...grid.querySelectorAll('.feature-cards__title')];
            if (titles.length < 3 || !titles.every((h) => /\d/.test(h.textContent))) return;
            titles.forEach((h) => {
                const walker = document.createTreeWalker(h, NodeFilter.SHOW_TEXT);
                for (let node = walker.nextNode(); node; node = walker.nextNode()) {
                    const m = /\d[\d., ]*\d|\d/.exec(node.data);
                    if (!m) continue;
                    const numberNode = node.splitText(m.index);
                    numberNode.splitText(m[0].length);
                    const span = el('span', { class: 'lh-num' });
                    numberNode.replaceWith(span);
                    span.append(numberNode);
                    break;
                }
            });
            grid.dataset.lhNumbers = '';
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
