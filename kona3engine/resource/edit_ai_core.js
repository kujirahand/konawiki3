'use strict';

/*
 * DOM-independent helpers for the AI panel of the editor (issue #364).
 * Loaded before edit.js; also required from Node tests.
 */

function aiPickJsonValue(row, keys) {
    for (var i = 0; i < keys.length; i++) {
        var key = keys[i];
        if (row[key] !== undefined && row[key] !== null) {
            return '' + row[key];
        }
    }
    return '';
}

function aiNormalizeJsonRow(row) {
    if (typeof row !== 'object' || row === null) {
        return null;
    }
    var normalized = {
        ng: aiPickJsonValue(row, ['ng', 'before', 'old', 'original', 'source', 'ErrorLocation', 'error', '誤り', '修正前', '対象']),
        ok: aiPickJsonValue(row, ['ok', 'after', 'new', 'replacement', 'Correction', 'correction', '修正', '修正後', '言い換え']),
        desc: aiPickJsonValue(row, ['desc', 'description', 'Reason', 'reason', '理由', '説明'])
    };
    if (normalized.ng === '' || normalized.ok === '') {
        return null;
    }
    return normalized;
}

/*
 * Find the first occurrence of `ng` at or after `from` that does not overlap
 * any range in `claimed` ([{start, end}, ...]). Returns {start, end} or null.
 */
function aiFindRange(text, ng, from, claimed) {
    if (!ng) { return null; }
    var pos = from || 0;
    claimed = claimed || [];
    for (;;) {
        var start = text.indexOf(ng, pos);
        if (start < 0) { return null; }
        var end = start + ng.length;
        var overlap = false;
        for (var i = 0; i < claimed.length; i++) {
            if (start < claimed[i].end && claimed[i].start < end) {
                overlap = true;
                break;
            }
        }
        if (!overlap) {
            return { start: start, end: end };
        }
        pos = start + 1;
    }
}

/*
 * Apply [{ng, ok}, ...] to `text` at once. Every ng is located in the
 * original text (in item order, skipping ranges already claimed by earlier
 * items), then the ranges are replaced from the back so that positions do
 * not shift and an `ok` containing another `ng` is never replaced again.
 * Returns {text, applied: [item index], notFound: [item index]}.
 */
function aiApplyReplacements(text, items) {
    var claimed = [];
    var applied = [];
    var notFound = [];
    for (var i = 0; i < items.length; i++) {
        var ng = items[i] ? items[i].ng : '';
        var ok = items[i] ? items[i].ok : '';
        if (typeof ng !== 'string' || typeof ok !== 'string' || ng === '' || ng === ok) {
            notFound.push(i);
            continue;
        }
        var range = aiFindRange(text, ng, 0, claimed);
        if (!range) {
            notFound.push(i);
            continue;
        }
        range.index = i;
        claimed.push(range);
        applied.push(i);
    }
    var order = claimed.slice().sort(function (a, b) { return b.start - a.start; });
    var result = text;
    for (var j = 0; j < order.length; j++) {
        var r = order[j];
        result = result.substring(0, r.start) + items[r.index].ok + result.substring(r.end);
    }
    return { text: result, applied: applied, notFound: notFound };
}

/*
 * Locate `expected`: at [start, end) if it is still there, otherwise the
 * first occurrence in the text. Returns the start index, or -1.
 */
function aiLocateRange(text, start, end, expected) {
    if (text.substring(start, end) === expected) {
        return start;
    }
    if (expected === '') {
        return -1;
    }
    return text.indexOf(expected);
}

/*
 * Replace `expected` (originally at [start, end)) with `replacement`.
 * Returns the new text, or null when `expected` cannot be found.
 */
function aiReplaceAt(text, start, end, expected, replacement) {
    var pos = aiLocateRange(text, start, end, expected);
    if (pos < 0) { return null; }
    return text.substring(0, pos) + replacement + text.substring(pos + expected.length);
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        aiPickJsonValue: aiPickJsonValue,
        aiNormalizeJsonRow: aiNormalizeJsonRow,
        aiFindRange: aiFindRange,
        aiApplyReplacements: aiApplyReplacements,
        aiLocateRange: aiLocateRange,
        aiReplaceAt: aiReplaceAt
    };
}
