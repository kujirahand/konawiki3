// Behavior tests for aiBlockFind in edit.js (issue #391): pressing the
// "find" button of an AI replace candidate must select the target text
// AND scroll the editor so the selection is visible, and repeated
// presses must cycle through the occurrences.
// Run with plain Node.js, no dependencies.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const src = fs.readFileSync(path.join(__dirname, '..', '..', 'resource', 'edit.js'), 'utf8');

function extract(name) {
    const i = src.indexOf('function ' + name + '(');
    assert.ok(i >= 0, 'function not found: ' + name);
    let depth = 0;
    let j = src.indexOf('{', i);
    const start = j;
    for (; j < src.length; j++) {
        if (src[j] === '{') depth++;
        if (src[j] === '}') { depth--; if (depth === 0) break; }
    }
    return src.substring(i, j + 1);
}

// --- fake textarea -------------------------------------------------
const lines = [];
for (let i = 0; i < 40; i++) { lines.push('line ' + i + ' xxxxxxxxxx'); }
// target word appears on line 30 and line 35
lines[30] = 'line 30 TARGET here';
lines[35] = 'line 35 TARGET again';
const bodyText = lines.join('\n');
const firstPos = bodyText.indexOf('TARGET');
const secondPos = bodyText.indexOf('TARGET', firstPos + 1);

const ta = {
    value: bodyText,
    selectionStart: 0,
    selectionEnd: 0,
    scrollTop: 0,
    clientHeight: 100, // about 5 lines visible
    focused: false,
    focus() { this.focused = true; },
    setSelectionRange(s, e) { this.selectionStart = s; this.selectionEnd = e; },
};

// --- globals used by the extracted functions -----------------------
global.qs = function (sel) { return sel === '#edit_txt' ? ta : null; };
global.edit_txt = ta;
global.window = {
    kona3EditorMetrics: null,
    getComputedStyle: function () { return { lineHeight: '20px' }; },
};
global.aiBlockItems = [];
let notFoundId = null;
global.aiMarkNotFound = function (id) { notFoundId = id; };

eval(extract('scrollEditorToPos') + '\n' + extract('aiBlockFind'));

// --- 1. find scrolls the target into view --------------------------
aiBlockItems[1] = { kind: 'replace', ng: 'TARGET', ok: 'HIT', done: false };
aiBlockFind(1);
assert.equal(ta.selectionStart, firstPos, 'selection starts at the first TARGET');
assert.equal(ta.selectionEnd, firstPos + 'TARGET'.length, 'selection ends after TARGET');
assert.ok(ta.scrollTop > 0, 'editor scrolled so the target is visible (scrollTop=' + ta.scrollTop + ')');
// target line top is 30*20=600; scroll must bring it within the viewport
assert.ok(ta.scrollTop <= 600 && 600 < ta.scrollTop + ta.clientHeight + 40,
    'target line is inside the visible area');

// --- 2. pressing find again cycles to the next occurrence ----------
aiBlockFind(1);
assert.equal(ta.selectionStart, secondPos, 'second press moves to the next TARGET');

// --- 3. and wraps around to the first one ---------------------------
aiBlockFind(1);
assert.equal(ta.selectionStart, firstPos, 'third press wraps to the first TARGET');

// --- 4. missing target is reported, selection untouched -------------
ta.setSelectionRange(3, 3);
aiBlockItems[2] = { kind: 'replace', ng: 'NOT_IN_TEXT', ok: 'X', done: false };
aiBlockFind(2);
assert.equal(notFoundId, 2, 'missing target reported via aiMarkNotFound');
assert.equal(ta.selectionStart, 3, 'selection unchanged when not found');

console.log('edit_ai_find.test.js: ALL OK');
