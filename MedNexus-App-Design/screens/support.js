/* Standalone preview runtime for the Hakim design screens.
   Renders the <x-dc> template with {{holes}}, <sc-for>, <sc-if> and the
   Component class so each screen opens directly in a browser. */
(function () {
  var inst, tpl, root, pending = false;

  window.DCLogic = class {
    constructor(props) { this.props = props || {}; this.state = {}; }
    setState(p) {
      var u = typeof p === 'function' ? p(this.state, this.props) : p;
      this.state = Object.assign({}, this.state, u);
      schedule();
    }
    forceUpdate() { schedule(); }
    renderVals() { return {}; }
  };

  function schedule() {
    if (pending) return;
    pending = true;
    Promise.resolve().then(function () { pending = false; render(); });
  }

  function lookup(path, scope) {
    path = (path || '').trim();
    if (path === 'true') return true;
    if (path === 'false') return false;
    if (path === 'null') return null;
    if (/^-?\d+(\.\d+)?$/.test(path)) return Number(path);
    if (/^'.*'$|^".*"$/.test(path)) return path.slice(1, -1);
    var cur = scope, parts = path.split('.');
    for (var i = 0; i < parts.length; i++) {
      if (cur == null) return undefined;
      cur = cur[parts[i]];
    }
    return cur;
  }

  var HOLE = /\{\{\s*([^}]+?)\s*\}\}/g;
  function interp(str, scope) {
    return str.replace(HOLE, function (m, p) { var v = lookup(p, scope); return v == null ? '' : String(v); });
  }
  function whole(str) {
    var m = (str || '').match(/^\s*\{\{\s*([^}]+?)\s*\}\}\s*$/);
    return m ? m[1] : null;
  }

  function proc(node, scope, out) {
    if (node.nodeType === 3) { out.appendChild(document.createTextNode(interp(node.nodeValue, scope))); return; }
    if (node.nodeType !== 1) return;
    var tag = node.localName;
    if (tag === 'helmet') return;
    if (tag === 'sc-for') {
      var list = lookup(whole(node.getAttribute('list')), scope) || [];
      var as = node.getAttribute('as') || 'item';
      list.forEach(function (it, i) {
        var s = Object.create(scope); s[as] = it; s.$index = i;
        node.childNodes.forEach(function (c) { proc(c, s, out); });
      });
      return;
    }
    if (tag === 'sc-if') {
      if (lookup(whole(node.getAttribute('value')), scope)) node.childNodes.forEach(function (c) { proc(c, scope, out); });
      return;
    }
    var el = document.createElementNS(node.namespaceURI, node.localName);
    var val;
    Array.prototype.forEach.call(node.attributes, function (a) {
      var name = a.name, raw = a.value, w = whole(raw);
      if (name.indexOf('hint-') === 0) return;
      if (/^on[a-z]+$/i.test(name)) {
        var fn = w ? lookup(w, scope) : null;
        if (typeof fn === 'function') {
          var ev = name.slice(2).toLowerCase();
          if (ev === 'change') {
            var t = (node.getAttribute('type') || '').toLowerCase();
            ev = (t === 'checkbox' || t === 'radio') ? 'change' : 'input';
          }
          el.addEventListener(ev, function (e) { fn(e); });
        }
        return;
      }
      if (w !== null) {
        var v = lookup(w, scope);
        if (name === 'checked' || name === 'disabled' || name === 'selected') { if (v) el.setAttribute(name, ''); el[name] = !!v; return; }
        if (name === 'value') { val = v == null ? '' : v; el.setAttribute('value', val); return; }
        if (v == null) return;
        el.setAttribute(name, String(v));
      } else if (raw.indexOf('{{') >= 0) {
        el.setAttribute(name, interp(raw, scope));
      } else {
        el.setAttribute(name, raw);
      }
    });
    node.childNodes.forEach(function (c) { proc(c, scope, el); });
    if (val !== undefined) el.value = val;
    else if (tag === 'textarea') el.value = el.textContent;
    out.appendChild(el);
  }

  function pathOf(el) {
    if (!el || !root || !root.contains(el) || el === root) return null;
    var p = [];
    while (el && el !== root) { p.unshift(Array.prototype.indexOf.call(el.parentNode.children, el)); el = el.parentNode; }
    return p;
  }
  function byPath(p) {
    var el = root;
    for (var i = 0; i < p.length && el; i++) el = el.children[p[i]];
    return el;
  }

  function render() {
    var vals = inst.renderVals() || {};
    var active = document.activeElement, ap = pathOf(active), sel = null;
    try { if (active && 'selectionStart' in active) sel = [active.selectionStart, active.selectionEnd]; } catch (e) {}
    var frag = document.createDocumentFragment();
    tpl.content.childNodes.forEach(function (c) { proc(c, vals, frag); });
    root.replaceChildren(frag);
    if (ap) {
      var n = byPath(ap);
      if (n && n.focus) { n.focus(); try { if (sel && n.setSelectionRange) n.setSelectionRange(sel[0], sel[1]); } catch (e) {} }
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var x = document.querySelector('x-dc');
    var script = document.querySelector('script[data-dc-script]');
    if (!x || !script) return;
    var props = {};
    try {
      var dp = JSON.parse(script.getAttribute('data-props') || '{}');
      Object.keys(dp).forEach(function (k) { if (k[0] !== '$' && dp[k] && 'default' in dp[k]) props[k] = dp[k]['default']; });
    } catch (e) {}
    var helmet = x.querySelector('helmet');
    if (helmet) Array.prototype.forEach.call(helmet.children, function (c) {
      var n = document.createElement(c.localName);
      Array.prototype.forEach.call(c.attributes, function (a) { n.setAttribute(a.name, a.value); });
      n.textContent = c.textContent;
      document.head.appendChild(n);
    });
    tpl = document.createElement('template');
    tpl.innerHTML = x.innerHTML;
    root = document.createElement('div');
    root.id = 'dc-root';
    x.replaceWith(root);
    var Component = new Function('DCLogic', script.textContent + '\nreturn Component;')(window.DCLogic);
    inst = new Component(props);
    render();
    if (inst.componentDidMount) inst.componentDidMount();
  });
})();
