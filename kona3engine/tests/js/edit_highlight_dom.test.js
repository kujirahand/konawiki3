// DOM-layer tests for edit_highlight.js (issue #360, PR2) using a
// minimal fake DOM. Run with plain Node.js, no dependencies.
'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');

class FakeClassList {
    constructor(el) { this.el = el; }
    add(name) { this.el._classes.add(name); }
    remove(name) { this.el._classes.delete(name); }
    contains(name) { return this.el._classes.has(name); }
}

class FakeElement {
    constructor(tag) {
        this.tagName = String(tag).toUpperCase();
        this.children = [];
        this.parentNode = null;
        this.style = {};
        this._classes = new Set();
        this.classList = new FakeClassList(this);
        this._listeners = {};
        this._attrs = {};
        this.innerHTML = '';
        this.textContent = '';
        this.scrollTop = 0;
        this.scrollHeight = 0;
        this.scrollLeft = 0;
        this.offsetWidth = 600;
        this.clientWidth = 583;
    }
    set className(v) {
        this._classes = new Set(String(v).split(/\s+/).filter(Boolean));
    }
    get className() { return Array.from(this._classes).join(' '); }
    setAttribute(k, v) { this._attrs[k] = String(v); }
    getAttribute(k) { return this._attrs[k]; }
    appendChild(child) {
        if (child.parentNode) { child.parentNode.removeChild(child); }
        child.parentNode = this;
        this.children.push(child);
        return child;
    }
    insertBefore(child, ref) {
        if (child.parentNode) { child.parentNode.removeChild(child); }
        const i = this.children.indexOf(ref);
        child.parentNode = this;
        if (i < 0) { this.children.push(child); } else { this.children.splice(i, 0, child); }
        return child;
    }
    removeChild(child) {
        const i = this.children.indexOf(child);
        if (i >= 0) { this.children.splice(i, 1); child.parentNode = null; }
        return child;
    }
    remove() { if (this.parentNode) { this.parentNode.removeChild(this); } }
    addEventListener(type, fn) {
        (this._listeners[type] = this._listeners[type] || []).push(fn);
    }
    removeEventListener(type, fn) {
        const a = this._listeners[type] || [];
        const i = a.indexOf(fn);
        if (i >= 0) { a.splice(i, 1); }
    }
    dispatch(type) {
        for (const fn of (this._listeners[type] || []).slice()) { fn({ type }); }
    }
}

class FakeTextArea extends FakeElement {
    constructor() {
        super('textarea');
        this._value = '';
    }
    setRangeText(text, start, end) {
        const v = this.value;
        this.value = v.slice(0, start) + text + v.slice(end);
    }
}
Object.defineProperty(FakeTextArea.prototype, 'value', {
    configurable: true,
    get() { return this._value; },
    set(v) { this._value = String(v); },
});

// --- globals -------------------------------------------------------------
const store = {};
let rafQueue = [];
let current = null;

function makeEditor(initialText) {
    const editor = new FakeElement('div');
    const ta = new FakeTextArea();
    ta.id = 'edit_txt';
    ta._value = initialText === undefined ? '* 見出し\n本文' : initialText;
    editor.appendChild(ta);
    const modeSelect = new FakeElement('select');
    modeSelect.id = 'page_mode';
    modeSelect.value = 'KonaNotation';
    return { editor, ta, modeSelect };
}

const documentStub = new FakeElement('#document');
documentStub.readyState = 'complete';
documentStub.getElementById = function (id) {
    if (!current) { return null; }
    if (id === 'edit_txt') { return current.ta; }
    if (id === 'page_mode') { return current.modeSelect; }
    return null;
};
documentStub.createElement = function (tag) { return new FakeElement(tag); };
documentStub.fonts = { ready: Promise.resolve() };

const resourceDir = path.join(__dirname, '..', '..', 'resource');
const core = require(path.join(resourceDir, 'edit_highlight_core.js'));
global.window = {
    localStorage: {
        getItem: (k) => (k in store ? store[k] : null),
        setItem: (k, v) => { store[k] = String(v); },
    },
    requestAnimationFrame: (fn) => { rafQueue.push(fn); return rafQueue.length; },
    cancelAnimationFrame: () => {},
    setTimeout: (fn) => { fn(); return 0; },
    clearTimeout: () => {},
    getComputedStyle: () => ({ borderLeftWidth: '1px', borderRightWidth: '1px' }),
    HTMLTextAreaElement: FakeTextArea,
    k3hlRender: core.k3hlRender,
    console,
    addEventListener: () => {},
    removeEventListener: () => {},
};
global.document = documentStub;
global.window.document = documentStub;

function flushRaf() {
    const q = rafQueue;
    rafQueue = [];
    for (const fn of q) { fn(); }
}

const layerPath = require.resolve(path.join(resourceDir, 'edit_highlight.js'));
function loadLayer() {
    delete require.cache[layerPath];
    require(layerPath);
    return global.window.kona3EditHighlight;
}

// --- Phase A: on by default ------------------------------------------------
current = makeEditor();
const { editor, ta, modeSelect } = current;
const hl = loadLayer();
assert.ok(hl, 'kona3EditHighlight is published');
assert.strictEqual(hl.isEnabled(), true, 'highlight is ON by default');
const wrap = ta.parentNode;
assert.ok(wrap && wrap.classList.contains('k3hl-wrap'), 'textarea is wrapped');
assert.ok(ta.classList.contains('k3hl-on'));
const backdrop = wrap.children.find((c) => c.classList.contains('k3hl-backdrop'));
assert.ok(backdrop, 'backdrop exists inside the wrap');
const content = backdrop.children[0];
assert.ok(content.innerHTML.includes('k3hl-h1'), 'initial render highlights the heading');

// Programmatic value change goes through the hooked setter.
ta.value = '** 小見出し\n本文です';
flushRaf();
assert.ok(content.innerHTML.includes('k3hl-h2'), 'hooked value setter re-renders');

// input event re-renders.
ta._value = 'ただの本文';
ta.dispatch('input');
flushRaf();
assert.ok(!content.innerHTML.includes('k3hl-h2'), 'input event re-renders');

// Scroll sync.
ta.scrollTop = 120;
ta.scrollLeft = 4;
ta.dispatch('scroll');
assert.strictEqual(backdrop.scrollTop, 120);
assert.strictEqual(backdrop.scrollLeft, 4);

// setRangeText is hooked too.
ta.setRangeText('* 先頭見出し\n', 0, 0);
flushRaf();
assert.ok(content.innerHTML.includes('k3hl-h1'), 'setRangeText re-renders');

// Mode switch re-renders with Markdown rules.
modeSelect.value = 'Markdown';
ta.value = '# MD見出し';
modeSelect.dispatch('change');
assert.ok(content.innerHTML.includes('k3hl-h1'), 'mode change re-renders');

// Disable restores the plain textarea and persists the opt-out.
hl.disable();
assert.strictEqual(hl.isEnabled(), false);
assert.strictEqual(ta.parentNode, editor, 'textarea is unwrapped');
assert.ok(!ta.classList.contains('k3hl-on'));
assert.strictEqual(Object.getOwnPropertyDescriptor(ta, 'value'), undefined,
    'value hook is removed');
assert.strictEqual(editor.children.length, 1);
assert.strictEqual(store['kona3_edit_highlight'], '0', 'disable persists the opt-out');

// --- Phase B: the stored opt-out is respected on the next load -------------
current = makeEditor();
const hl2 = loadLayer();
assert.strictEqual(hl2.isEnabled(), false, 'stored opt-out keeps it off');
assert.strictEqual(current.ta.parentNode, current.editor, 'not wrapped when opted out');
hl2.enable();
assert.strictEqual(hl2.isEnabled(), true);
assert.strictEqual(store['kona3_edit_highlight'], '1', 'enable persists the opt-in');
hl2.disable();

// --- Phase C: IME composition keeps rendering -------------------------------
// The backdrop is a separate element and the textarea text is
// transparent, so rendering must NOT pause during composition,
// otherwise the composing glyphs would be invisible.
delete store['kona3_edit_highlight'];
current = makeEditor();
const hl3 = loadLayer();
assert.strictEqual(hl3.isEnabled(), true);
const wrap3 = current.ta.parentNode;
const content3 = wrap3.children.find((c) => c.classList.contains('k3hl-backdrop')).children[0];
current.ta.dispatch('compositionstart');
current.ta._value = '** 変換中';
current.ta.dispatch('input');
flushRaf();
assert.ok(content3.innerHTML.includes('k3hl-h2'), 'rendering continues during composition');
current.ta.dispatch('compositionend');
assert.ok(content3.innerHTML.includes('k3hl-h2'), 'compositionend keeps the render');
hl3.disable();

// --- Phase D: oversized documents auto-disable ------------------------------
delete store['kona3_edit_highlight'];
current = makeEditor();
const hl4 = loadLayer();
assert.strictEqual(hl4.isEnabled(), true);
current.ta.value = 'あ'.repeat(200001);
flushRaf();
assert.strictEqual(hl4.isEnabled(), false, 'oversized text auto-disables');
assert.strictEqual(current.ta.parentNode, current.editor, 'unwrapped after auto-disable');
assert.strictEqual(store['kona3_edit_highlight'], undefined,
    'auto-disable does not persist an opt-out');

// Boundary: exactly MAX_CHARS stays enabled.
delete store['kona3_edit_highlight'];
current = makeEditor();
const hl4b = loadLayer();
current.ta.value = 'あ'.repeat(200000);
flushRaf();
assert.strictEqual(hl4b.isEnabled(), true, 'exactly 200000 chars stays enabled');
hl4b.disable();

// Initially oversized: never wrapped in the first place.
delete store['kona3_edit_highlight'];
current = makeEditor('あ'.repeat(200001));
const hl4c = loadLayer();
assert.strictEqual(hl4c.isEnabled(), false, 'initially oversized document is not enabled');
assert.strictEqual(current.ta.parentNode, current.editor, 'never wrapped when oversized');

// --- Phase E: misalignment self-check (two consecutive failures) ------------
async function phaseE() {
    delete store['kona3_edit_highlight'];
    current = makeEditor();
    const hl5 = loadLayer();
    assert.strictEqual(hl5.isEnabled(), true);
    const wrap5 = current.ta.parentNode;
    const backdrop5 = wrap5.children.find((c) => c.classList.contains('k3hl-backdrop'));
    current.ta.scrollHeight = 1000;
    backdrop5.scrollHeight = 500;
    flushRaf();
    assert.strictEqual(hl5.isEnabled(), true, 'a single mismatch does not disable yet');
    await new Promise((resolve) => setImmediate(resolve));
    assert.strictEqual(hl5.isEnabled(), false, 'a second consecutive mismatch disables');
    assert.strictEqual(current.ta.parentNode, current.editor, 'unwrapped after self-check failure');

    // Within one line of difference: stays enabled.
    delete store['kona3_edit_highlight'];
    current = makeEditor();
    const hl6 = loadLayer();
    const wrap6 = current.ta.parentNode;
    const backdrop6 = wrap6.children.find((c) => c.classList.contains('k3hl-backdrop'));
    current.ta.scrollHeight = 1000;
    backdrop6.scrollHeight = 1010;
    flushRaf();
    await new Promise((resolve) => setImmediate(resolve));
    assert.strictEqual(hl6.isEnabled(), true, 'a within-line difference stays enabled');
    hl6.disable();
}

phaseE().then(() => {
    console.log('edit_highlight_dom.test.js: all tests passed');
}).catch((error) => {
    console.error(error);
    process.exit(1);
});
