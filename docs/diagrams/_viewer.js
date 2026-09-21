/*
 * Shared viewer for the diagram pages: tabs, lazy Mermaid rendering, zoom and drag-to-pan.
 * Classic script (not a module) so the pages also work when opened via file://.
 */
(function () {
  'use strict';

  var dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  var noMaxWidth = { useMaxWidth: false };

  mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'loose',
    theme: dark ? 'dark' : 'default',
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif',
    er: Object.assign({ layoutDirection: 'TB' }, noMaxWidth),
    flowchart: Object.assign({ curve: 'basis', padding: 12 }, noMaxWidth),
    sequence: Object.assign({ showSequenceNumbers: true }, noMaxWidth),
    state: noMaxWidth,
    class: noMaxWidth,
    gitGraph: noMaxWidth
  });

  var tabs = Array.prototype.slice.call(document.querySelectorAll('.tabs button'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('.panel'));

  function naturalWidth(svg) {
    var vb = svg.viewBox && svg.viewBox.baseVal;
    return vb && vb.width ? vb.width : svg.getBoundingClientRect().width;
  }

  function setScale(card, scale) {
    var svg = card.querySelector('.viewport svg');
    if (!svg) return;
    scale = Math.min(4, Math.max(0.15, scale));
    card.dataset.scale = String(scale);
    svg.style.width = Math.round(naturalWidth(svg) * scale) + 'px';
    var label = card.querySelector('[data-zoom-label]');
    if (label) label.textContent = Math.round(scale * 100) + '%';
  }

  // Fit to width. The first view (`readable`) never zooms below 60% so text stays legible;
  // wide diagrams can then be dragged sideways. The Fit button always shows the whole diagram.
  function fit(card, readable) {
    var svg = card.querySelector('.viewport svg');
    var viewport = card.querySelector('.viewport');
    if (!svg || !viewport) return;
    var available = viewport.clientWidth - 32;
    var scale = Math.min(1.25, available / naturalWidth(svg));
    setScale(card, readable ? Math.max(0.6, scale) : scale);
  }

  function initialFit(card) { fit(card, true); }

  function wireCard(card) {
    var viewport = card.querySelector('.viewport');

    card.querySelectorAll('[data-zoom]').forEach(function (button) {
      button.addEventListener('click', function () {
        var current = parseFloat(card.dataset.scale || '1');
        var action = button.getAttribute('data-zoom');
        if (action === 'in') setScale(card, current * 1.2);
        else if (action === 'out') setScale(card, current / 1.2);
        else if (action === 'fit') fit(card, false);
        else setScale(card, parseFloat(action));
      });
    });

    viewport.addEventListener('wheel', function (event) {
      if (!event.ctrlKey && !event.metaKey) return;
      event.preventDefault();
      var current = parseFloat(card.dataset.scale || '1');
      setScale(card, current * (event.deltaY < 0 ? 1.1 : 1 / 1.1));
    }, { passive: false });

    var start = null;
    viewport.addEventListener('mousedown', function (event) {
      if (event.button !== 0) return;
      start = { x: event.clientX, y: event.clientY, left: viewport.scrollLeft, top: viewport.scrollTop };
      viewport.classList.add('dragging');
    });
    window.addEventListener('mousemove', function (event) {
      if (!start) return;
      viewport.scrollLeft = start.left - (event.clientX - start.x);
      viewport.scrollTop = start.top - (event.clientY - start.y);
    });
    window.addEventListener('mouseup', function () {
      start = null;
      viewport.classList.remove('dragging');
    });
  }

  function renderPanel(panel) {
    if (panel.dataset.rendered) return Promise.resolve();
    panel.dataset.rendered = '1';
    var nodes = panel.querySelectorAll('pre.mermaid');
    return mermaid.run({ nodes: nodes, suppressErrors: false })
      .catch(function (error) {
        var target = panel.querySelector('.viewport');
        var box = document.createElement('div');
        box.className = 'render-error';
        box.textContent = 'Diagram failed to render:\n' + (error && error.message ? error.message : error);
        target.prepend(box);
        document.body.dataset.renderError = '1';
      })
      .then(function () {
        panel.querySelectorAll('.diagram-card').forEach(initialFit);
      });
  }

  function activate(id, push) {
    var found = false;
    tabs.forEach(function (tab) {
      var on = tab.dataset.target === id;
      found = found || on;
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (!found) return activate(tabs[0].dataset.target, false);
    panels.forEach(function (panel) { panel.hidden = panel.id !== 'panel-' + id; });
    if (push) history.replaceState(null, '', '#' + id);
    return renderPanel(document.getElementById('panel-' + id));
  }

  document.querySelectorAll('.diagram-card').forEach(wireCard);
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () { activate(tab.dataset.target, true); });
  });
  window.addEventListener('resize', function () {
    panels.forEach(function (panel) {
      if (!panel.hidden) panel.querySelectorAll('.diagram-card').forEach(initialFit);
    });
  });

  // `?all` renders every panel up front (used by the headless render check).
  var renderAll = /[?&]all\b/.test(location.search);
  var first = activate((location.hash || '').slice(1) || tabs[0].dataset.target, false);
  if (renderAll) {
    first.then(function () {
      return panels.reduce(function (chain, panel) {
        return chain.then(function () {
          panel.hidden = false;
          return renderPanel(panel);
        });
      }, Promise.resolve());
    }).then(function () { document.body.dataset.renderDone = '1'; });
  }
})();
