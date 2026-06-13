/*
 * Skillflow skill detail enhancements (page 1066):
 *  - copy the skill ID to the clipboard
 *  - build an "On this page" table of contents from the rendered markdown
 *  - scrollspy: highlight the current section in the TOC
 * Loaded as an external module so it stays within the site CSP (no inline JS).
 */
(function () {
  'use strict';

  function initCopy() {
    document.querySelectorAll('[data-sf-copy]').forEach(function (box) {
      var btn = box.querySelector('[data-sf-copy-btn]');
      var value = box.getAttribute('data-sf-copy') || '';
      if (!btn || !value) {
        return;
      }
      btn.addEventListener('click', function () {
        var done = function () {
          var label = btn.querySelector('.sf-copy__btnText') || btn;
          var original = label.textContent;
          label.textContent = 'Copied';
          btn.classList.add('is-copied');
          window.setTimeout(function () {
            label.textContent = original;
            btn.classList.remove('is-copied');
          }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(value).then(done, function () {});
        } else {
          var ta = document.createElement('textarea');
          ta.value = value;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); done(); } catch (e) { /* noop */ }
          ta.remove();
        }
      });
    });
  }

  function slugify(text, used) {
    var base = text.toLowerCase().trim()
      .replace(/[^\w\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-') || 'section';
    var slug = base;
    var i = 2;
    while (used[slug]) { slug = base + '-' + i++; }
    used[slug] = true;
    return slug;
  }

  function initToc() {
    var body = document.querySelector('[data-sf-body]');
    var toc = document.querySelector('[data-sf-toc]');
    var list = document.querySelector('[data-sf-toc-list]');
    if (!body || !toc || !list) {
      return;
    }
    var headings = body.querySelectorAll('h2, h3');
    if (headings.length < 2) {
      return;
    }
    var used = {};
    var links = [];
    headings.forEach(function (h) {
      if (!h.id) { h.id = slugify(h.textContent || '', used); }
      var li = document.createElement('li');
      li.className = 'lvl-' + (h.tagName === 'H3' ? '3' : '2');
      var a = document.createElement('a');
      a.href = '#' + h.id;
      a.textContent = h.textContent;
      li.appendChild(a);
      list.appendChild(li);
      links.push({ id: h.id, a: a });
    });
    toc.hidden = false;

    if ('IntersectionObserver' in window) {
      var byId = {};
      links.forEach(function (l) { byId[l.id] = l.a; });
      var current = null;
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            if (current) { current.classList.remove('is-active'); }
            current = byId[entry.target.id];
            if (current) { current.classList.add('is-active'); }
          }
        });
      }, { rootMargin: '0px 0px -75% 0px', threshold: 0 });
      headings.forEach(function (h) { observer.observe(h); });
    }
  }

  function init() { initCopy(); initToc(); }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
