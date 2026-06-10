/*!
 * improveme — drop-in feedback & bug-report widget.
 * Self-contained, dependency-free (html2canvas is lazy-loaded on demand).
 * All per-page config comes from window.__IMPROVEME__ (rendered inline by the
 * Blade snippet); this file is static and cacheable.
 */
(function () {
  'use strict';

  var CFG = window.__IMPROVEME__;
  if (!CFG || !CFG.endpoint) return;
  if (window.__IMPROVEME_BOOTED__) return; // guard against double-injection
  window.__IMPROVEME_BOOTED__ = true;

  var L = Object.assign({
    title: 'Send feedback',
    placeholder: 'Describe the bug or your idea…',
    type_bug: 'Bug',
    type_idea: 'Idea',
    pick: 'Pick an element',
    picking: 'Click a block · click again to go behind · Esc to finish',
    send: 'Send',
    sent: 'Thanks! Your feedback was sent.',
    error: 'Could not send — please try again.'
  }, CFG.labels || {});

  var Z = CFG.zIndex || 2147483000;
  var ACCENT = CFG.accent || '#ff5a36';
  var HOVER = CFG.hoverColor || '#3b82f6';
  var SELECTED = CFG.selectedColor || '#22c55e';
  var PAD = typeof CFG.screenshotPadding === 'number' ? CFG.screenshotPadding : 15;
  var MAX_HTML = CFG.maxHtml || 20000;

  // ---- state -------------------------------------------------------------
  var type = 'bug';
  var picking = false;
  var pickedEl = null;      // currently selected DOM element
  var hoverEl = null;       // element under the cursor while picking
  var seen = new Set();     // elements drilled past in the current chain
  var capture = null;       // { selector, tag, id, classes, html, rect }
  var screenshot = null;    // data URL

  // ---- UI: shadow-root host so host-page CSS can't bleed in --------------
  var host = document.createElement('div');
  host.setAttribute('data-improveme', 'host');
  host.style.cssText = 'all:initial;position:fixed;z-index:' + Z + ';';
  document.documentElement.appendChild(host);
  var root = host.attachShadow({ mode: 'open' });

  var pos = corner(CFG.position);
  root.innerHTML = styleTag() +
    '<button class="im-fab" title="' + L.title + '" aria-label="' + L.title + '">' + bugIcon() + '</button>' +
    panelHtml();

  var fab = root.querySelector('.im-fab');
  var panel = root.querySelector('.im-panel');
  var ta = root.querySelector('.im-text');
  var sendBtn = root.querySelector('.im-send');
  var pickBtn = root.querySelector('.im-pick');
  var chip = root.querySelector('.im-chip');
  var thumb = root.querySelector('.im-thumb');
  var status = root.querySelector('.im-status');
  var typeBtns = root.querySelectorAll('.im-type');

  fab.addEventListener('click', function () { togglePanel(); });
  root.querySelector('.im-close').addEventListener('click', function () { closePanel(); });
  pickBtn.addEventListener('click', function () { startPicking(); });
  root.querySelector('.im-chip-clear').addEventListener('click', function (e) { e.stopPropagation(); clearSelection(); });
  sendBtn.addEventListener('click', function () { send(); });
  typeBtns.forEach(function (b) {
    b.addEventListener('click', function () { setType(b.getAttribute('data-type')); });
  });

  // ---- picker overlay (plain body nodes, inline-styled, ignored by us) ---
  var hoverBox = overlayBox(HOVER);
  var selBox = overlayBox(SELECTED);
  var banner = document.createElement('div');
  banner.setAttribute('data-improveme', 'banner');
  banner.style.cssText = boxBase() + ';left:50%;top:18px;transform:translateX(-50%);' +
    'padding:10px 14px;border-radius:10px;background:#111827;color:#fff;font:600 13px/1.3 system-ui,sans-serif;' +
    'box-shadow:0 8px 30px rgba(0,0,0,.35);pointer-events:auto;display:none;gap:10px;align-items:center;';
  banner.innerHTML = '<span class="im-bn-text"></span>' +
    '<button class="im-bn-done" style="cursor:pointer;border:0;border-radius:7px;padding:5px 10px;background:' + SELECTED + ';color:#fff;font:inherit;">Done</button>' +
    '<button class="im-bn-cancel" style="cursor:pointer;border:0;border-radius:7px;padding:5px 10px;background:#374151;color:#fff;font:inherit;">Cancel</button>';
  document.documentElement.appendChild(banner);
  banner.querySelector('.im-bn-done').addEventListener('click', function () { finishPicking(true); });
  banner.querySelector('.im-bn-cancel').addEventListener('click', function () { finishPicking(false); });

  // ======================================================================
  // Panel behaviour
  // ======================================================================
  function togglePanel() { panel.classList.contains('open') ? closePanel() : openPanel(); }
  function openPanel() { panel.classList.add('open'); setStatus(''); ta.focus(); }
  function closePanel() { panel.classList.remove('open'); }
  function setType(t) {
    type = (t === 'idea') ? 'idea' : 'bug';
    typeBtns.forEach(function (b) { b.classList.toggle('on', b.getAttribute('data-type') === type); });
  }

  function clearSelection() {
    pickedEl = null; capture = null; screenshot = null; seen.clear();
    chip.style.display = 'none';
    thumb.style.display = 'none';
    thumb.removeAttribute('src');
  }

  function renderSelection() {
    if (!capture) { chip.style.display = 'none'; return; }
    chip.style.display = 'flex';
    root.querySelector('.im-chip-text').textContent = capture.selector;
    if (screenshot) { thumb.src = screenshot; thumb.style.display = 'block'; }
  }

  // ======================================================================
  // Element picker
  // ======================================================================
  function startPicking() {
    if (picking) return;
    picking = true;
    seen.clear();
    closePanel();
    host.style.display = 'none';            // hide the fab while picking
    banner.querySelector('.im-bn-text').textContent = L.picking;
    banner.style.display = 'flex';
    document.documentElement.style.cursor = 'crosshair';
    document.addEventListener('mousemove', onMove, true);
    document.addEventListener('click', onClick, true);
    document.addEventListener('keydown', onKey, true);
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition, true);
  }

  function finishPicking(keep) {
    if (!picking) return;
    picking = false;
    document.removeEventListener('mousemove', onMove, true);
    document.removeEventListener('click', onClick, true);
    document.removeEventListener('keydown', onKey, true);
    window.removeEventListener('scroll', reposition, true);
    window.removeEventListener('resize', reposition, true);
    document.documentElement.style.cursor = '';
    hoverBox.style.display = 'none';
    banner.style.display = 'none';
    host.style.display = '';

    if (!keep || !pickedEl) {
      selBox.style.display = 'none';
      openPanel();
      return;
    }

    capture = describe(pickedEl);
    renderSelection();
    selBox.style.display = 'none';
    openPanel();
    setStatus('…');
    grabScreenshot(pickedEl).then(function (url) {
      screenshot = url;
      renderSelection();
      setStatus('');
    });
  }

  // True when an event originates from our own picker UI (the banner and its
  // Done/Cancel buttons). Those must keep working normally while picking — the
  // cursor over them should not preview/select the page block behind them.
  function onOwnUi(e) {
    var t = e.target;
    return !!(t && t.closest && t.closest('[data-improveme]'));
  }

  function onMove(e) {
    if (onOwnUi(e)) { hoverEl = null; hoverBox.style.display = 'none'; return; }
    var t = targetAt(e.clientX, e.clientY);
    hoverEl = t;
    if (t && t !== pickedEl) { positionBox(hoverBox, t); hoverBox.style.display = 'block'; }
    else { hoverBox.style.display = 'none'; }
  }

  function onClick(e) {
    // Let clicks on the banner (Done/Cancel) through to their own handlers —
    // don't pick the block behind it and don't swallow the click.
    if (onOwnUi(e)) return;
    e.preventDefault();
    e.stopPropagation();
    var t = hoverEl || targetAt(e.clientX, e.clientY);
    if (!t || t === pickedEl) return;
    if (pickedEl) seen.add(pickedEl); // remember chain so next click goes further behind
    pickedEl = t;
    seen.add(t);
    positionBox(selBox, t);
    selBox.style.display = 'block';
    hoverBox.style.display = 'none';
  }

  function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); finishPicking(true); }
  }

  // Topmost relevant element at a point — or, when hovering the already-picked
  // element, the block *behind* it (drill-down). Our own nodes are skipped.
  function targetAt(x, y) {
    var stack = document.elementsFromPoint(x, y).filter(function (el) {
      return el && el.nodeType === 1 &&
        !el.closest('[data-improveme]') &&
        el !== document.documentElement && el !== document.body;
    });
    if (!stack.length) return null;

    if (pickedEl && pointIn(pickedEl.getBoundingClientRect(), x, y)) {
      var i = stack.indexOf(pickedEl);
      var behind = i >= 0 ? stack.slice(i + 1) : stack;
      for (var k = 0; k < behind.length; k++) {
        if (!seen.has(behind[k])) return behind[k];
      }
      return pickedEl; // nothing deeper to reveal
    }

    seen.clear(); // moved to a fresh area
    return stack[0];
  }

  function reposition() {
    if (pickedEl) positionBox(selBox, pickedEl);
    if (hoverEl && hoverEl !== pickedEl) positionBox(hoverBox, hoverEl);
  }

  // ======================================================================
  // Capture: selector, html, rect, screenshot
  // ======================================================================
  function describe(el) {
    var r = el.getBoundingClientRect();
    var html = el.outerHTML || '';
    if (html.length > MAX_HTML) html = html.slice(0, MAX_HTML) + '\n<!-- …truncated… -->';
    return {
      selector: cssSelector(el),
      tag: el.tagName.toLowerCase(),
      id: el.id || null,
      classes: el.className && typeof el.className === 'string' ? el.className : null,
      html: html,
      rect: { top: Math.round(r.top), left: Math.round(r.left), width: Math.round(r.width), height: Math.round(r.height) }
    };
  }

  function cssSelector(el) {
    if (!(el instanceof Element)) return '';
    if (el.id && unique('#' + esc(el.id))) return '#' + esc(el.id);
    var parts = [];
    var node = el;
    while (node && node.nodeType === 1 && node !== document.body && node !== document.documentElement) {
      if (node.id && unique('#' + esc(node.id))) { parts.unshift('#' + esc(node.id)); break; }
      var sel = node.nodeName.toLowerCase();
      var nth = 1, sib = node;
      while ((sib = sib.previousElementSibling)) { if (sib.nodeName === node.nodeName) nth++; }
      parts.unshift(sel + ':nth-of-type(' + nth + ')');
      node = node.parentElement;
    }
    return parts.join(' > ');
  }

  function grabScreenshot(el) {
    return loadH2C().then(function (h2c) {
      if (!h2c) return null;
      var r = el.getBoundingClientRect();
      var x = Math.max(0, window.scrollX + r.left - PAD);
      var y = Math.max(0, window.scrollY + r.top - PAD);
      return h2c(document.documentElement, {
        x: x,
        y: y,
        width: r.width + PAD * 2,
        height: r.height + PAD * 2,
        scale: Math.min(window.devicePixelRatio || 1, 2),
        useCORS: true,
        logging: false,
        backgroundColor: null,
        ignoreElements: function (n) { return n.nodeType === 1 && n.hasAttribute('data-improveme'); }
      }).then(function (canvas) {
        try { return canvas.toDataURL('image/png'); } catch (e) { return null; }
      }).catch(function () { return null; });
    }).catch(function () { return null; });
  }

  var h2cPromise = null;
  function loadH2C() {
    if (window.html2canvas) return Promise.resolve(window.html2canvas);
    if (!CFG.html2canvasUrl) return Promise.resolve(null);
    if (h2cPromise) return h2cPromise;
    h2cPromise = new Promise(function (res) {
      var s = document.createElement('script');
      s.src = CFG.html2canvasUrl;
      s.onload = function () { res(window.html2canvas || null); };
      s.onerror = function () { res(null); };
      document.head.appendChild(s);
    });
    return h2cPromise;
  }

  // ======================================================================
  // Send
  // ======================================================================
  function send() {
    var msg = (ta.value || '').trim();
    if (!msg) { ta.focus(); shake(ta); return; }
    sendBtn.disabled = true;
    setStatus('…');

    var payload = {
      type: type,
      message: msg,
      page: { url: location.href, title: document.title, referrer: document.referrer || null },
      viewport: { w: window.innerWidth, h: window.innerHeight, dpr: window.devicePixelRatio || 1 },
      userAgent: navigator.userAgent,
      element: capture,
      consoleErrors: (window.__IMPROVEME_ERRLOG__ || []).slice(-50),
      screenshot: screenshot
    };

    fetch(CFG.endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': CFG.token || ''
      },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) throw new Error('http ' + r.status);
      return r.json();
    }).then(function () {
      ta.value = '';
      clearSelection();
      setStatus(L.sent, 'ok');
      setTimeout(function () { closePanel(); setStatus(''); }, 1600);
    }).catch(function () {
      setStatus(L.error, 'err');
    }).then(function () {
      sendBtn.disabled = false;
    });
  }

  // ======================================================================
  // Helpers
  // ======================================================================
  function setStatus(txt, kind) {
    status.textContent = txt || '';
    status.className = 'im-status' + (kind ? ' ' + kind : '');
  }
  function shake(el) { el.style.animation = 'none'; void el.offsetWidth; el.style.animation = 'im-shake .3s'; }
  function unique(sel) { try { return document.querySelectorAll(sel).length === 1; } catch (e) { return false; } }
  function esc(s) { return window.CSS && CSS.escape ? CSS.escape(s) : String(s).replace(/([^a-zA-Z0-9_-])/g, '\\$1'); }
  function pointIn(r, x, y) { return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom; }

  function positionBox(box, el) {
    var r = el.getBoundingClientRect();
    box.style.left = r.left + 'px';
    box.style.top = r.top + 'px';
    box.style.width = r.width + 'px';
    box.style.height = r.height + 'px';
  }

  function overlayBox(color) {
    var d = document.createElement('div');
    d.setAttribute('data-improveme', 'box');
    d.style.cssText = boxBase() + ';display:none;border:2px solid ' + color + ';' +
      'background:' + hexA(color, 0.12) + ';border-radius:3px;box-shadow:0 0 0 1px rgba(255,255,255,.4) inset;';
    document.documentElement.appendChild(d);
    return d;
  }
  function boxBase() {
    return 'all:initial;position:fixed;pointer-events:none;z-index:' + (Z + 1) + ';box-sizing:border-box';
  }

  function corner(p) {
    p = p || 'bottom-right';
    var v = p.indexOf('top') === 0 ? 'top:8px' : 'bottom:8px';
    var h = p.indexOf('left') >= 0 ? 'left:8px' : 'right:8px';
    var pv = p.indexOf('top') === 0 ? 'top:42px' : 'bottom:42px';
    return { fab: v + ';' + h, panelV: pv, panelH: h };
  }

  function styleTag() {
    return '<style>' +
      ':host{all:initial}' +
      '*{box-sizing:border-box;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif}' +
      '.im-fab{position:fixed;' + pos.fab + ';width:24px;height:24px;border-radius:50%;border:0;cursor:pointer;' +
      'background:' + ACCENT + ';color:#fff;display:flex;align-items:center;justify-content:center;' +
      'box-shadow:0 3px 10px rgba(0,0,0,.25);transition:transform .15s}' +
      '.im-fab:hover{transform:scale(1.08)}' +
      '.im-fab svg{width:14px;height:14px}' +
      '.im-panel{position:fixed;' + pos.panelV + ';' + pos.panelH + ';width:320px;max-width:calc(100vw - 32px);' +
      'background:#fff;color:#111827;border-radius:14px;box-shadow:0 16px 48px rgba(0,0,0,.28);' +
      'opacity:0;transform:translateY(10px) scale(.98);pointer-events:none;transition:.16s;overflow:hidden}' +
      '.im-panel.open{opacity:1;transform:none;pointer-events:auto}' +
      '.im-head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #eef0f3}' +
      '.im-head b{font-size:15px}' +
      '.im-close{border:0;background:transparent;cursor:pointer;font-size:20px;line-height:1;color:#9ca3af;padding:2px 6px}' +
      '.im-body{padding:14px 16px;display:flex;flex-direction:column;gap:12px}' +
      '.im-types{display:flex;gap:8px}' +
      '.im-type{flex:1;padding:8px;border:1px solid #e5e7eb;background:#f9fafb;border-radius:9px;cursor:pointer;font-size:13px;font-weight:600;color:#6b7280}' +
      '.im-type.on{border-color:' + ACCENT + ';color:' + ACCENT + ';background:' + hexA(ACCENT, 0.08) + '}' +
      '.im-text{width:100%;min-height:84px;resize:vertical;padding:10px;border:1px solid #e5e7eb;border-radius:9px;font-size:13px;outline:none}' +
      '.im-text:focus{border-color:' + ACCENT + '}' +
      '.im-pick{display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px dashed #cbd5e1;background:#f8fafc;border-radius:9px;cursor:pointer;font-size:13px;font-weight:600;color:#334155}' +
      '.im-pick:hover{border-color:' + HOVER + ';color:' + HOVER + '}' +
      '.im-chip{display:none;align-items:center;gap:8px;background:' + hexA(SELECTED, 0.12) + ';border:1px solid ' + hexA(SELECTED, 0.5) + ';border-radius:9px;padding:7px 10px;font-size:12px}' +
      '.im-chip-text{flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:ui-monospace,Menlo,monospace;color:#166534}' +
      '.im-chip-clear{border:0;background:transparent;cursor:pointer;color:#16a34a;font-size:16px;line-height:1}' +
      '.im-thumb{display:none;width:100%;max-height:140px;object-fit:contain;border:1px solid #e5e7eb;border-radius:9px;background:#f8fafc}' +
      '.im-foot{display:flex;align-items:center;gap:10px;padding:0 16px 16px}' +
      '.im-send{flex:1;padding:10px;border:0;border-radius:9px;background:' + ACCENT + ';color:#fff;font-weight:700;font-size:14px;cursor:pointer}' +
      '.im-send:disabled{opacity:.6;cursor:default}' +
      '.im-status{font-size:12px;color:#6b7280;min-height:14px}' +
      '.im-status.ok{color:#16a34a}.im-status.err{color:#dc2626}' +
      '@keyframes im-shake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}' +
      '</style>';
  }

  function panelHtml() {
    return '<div class="im-panel">' +
      '<div class="im-head"><b>' + L.title + '</b><button class="im-close" aria-label="Close">×</button></div>' +
      '<div class="im-body">' +
      '<div class="im-types">' +
      '<button class="im-type on" data-type="bug">🐞 ' + L.type_bug + '</button>' +
      '<button class="im-type" data-type="idea">💡 ' + L.type_idea + '</button>' +
      '</div>' +
      '<textarea class="im-text" placeholder="' + L.placeholder + '"></textarea>' +
      '<button class="im-pick">🎯 ' + L.pick + '</button>' +
      '<div class="im-chip"><span class="im-chip-text"></span><button class="im-chip-clear" title="Remove">×</button></div>' +
      '<img class="im-thumb" alt="selection preview"/>' +
      '</div>' +
      '<div class="im-foot"><button class="im-send">' + L.send + '</button><span class="im-status"></span></div>' +
      '</div>';
  }

  function bugIcon() {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/>' +
      '<path d="M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"/>' +
      '<path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6"/>' +
      '<path d="M12 20v-9"/><path d="M6.53 9C4.6 8.8 3 7.1 3 5"/><path d="M6 13H2"/>' +
      '<path d="M3 21c0-2.1 1.7-3.9 3.8-4"/><path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"/>' +
      '<path d="M22 13h-4"/><path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"/></svg>';
  }

  function hexA(hex, a) {
    var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    if (!m) return hex;
    return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + a + ')';
  }
})();
