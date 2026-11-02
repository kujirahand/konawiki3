'use strict';

const assert = require('assert');
const {
    aiNormalizeJsonRow,
    aiFindRange,
    aiApplyReplacements,
    aiReplaceAt
} = require('../../resource/edit_ai_core.js');

// --- aiApplyReplacements: 一括置換 ---
{
    const r = aiApplyReplacements('あいうえお、かきくけこ、さしすせそ', [
        { ng: 'あい', ok: 'AI' },
        { ng: 'かき', ok: 'KK' },
        { ng: 'さし', ok: 'SS' }
    ]);
    assert.strictEqual(r.text, 'AIうえお、KKくけこ、SSすせそ');
    assert.deepStrictEqual(r.applied, [0, 1, 2]);
    assert.deepStrictEqual(r.notFound, []);
}

// 文書順と逆順でも結果は同じ
{
    const items = [
        { ng: 'さし', ok: 'SS' },
        { ng: 'かき', ok: 'KK' },
        { ng: 'あい', ok: 'AI' }
    ];
    const r = aiApplyReplacements('あいうえお、かきくけこ、さしすせそ', items);
    assert.strictEqual(r.text, 'AIうえお、KKくけこ、SSすせそ');
    assert.deepStrictEqual(r.applied, [0, 1, 2]);
}

// 長さが変わる置換でも位置がずれない
{
    const r = aiApplyReplacements('abc-def-ghi', [
        { ng: 'abc', ok: 'a' },
        { ng: 'ghi', ok: 'GGGGGGG' }
    ]);
    assert.strictEqual(r.text, 'a-def-GGGGGGG');
}

// 同じ ng が2件: 1件目・2件目の出現をそれぞれ置換
{
    const r = aiApplyReplacements('ほげ と ほげ', [
        { ng: 'ほげ', ok: 'A' },
        { ng: 'ほげ', ok: 'B' }
    ]);
    assert.strictEqual(r.text, 'A と B');
    assert.deepStrictEqual(r.applied, [0, 1]);
}

// 本文に1回しか無ければ2件目は notFound
{
    const r = aiApplyReplacements('ほげ だけ', [
        { ng: 'ほげ', ok: 'A' },
        { ng: 'ほげ', ok: 'B' }
    ]);
    assert.strictEqual(r.text, 'A だけ');
    assert.deepStrictEqual(r.applied, [0]);
    assert.deepStrictEqual(r.notFound, [1]);
}

// ok が別 item の ng を含んでも連鎖置換しない
{
    const r = aiApplyReplacements('か と かな', [
        { ng: 'か', ok: 'かな' },
        { ng: 'かな', ok: 'X' }
    ]);
    // 1件目は先頭の「か」、2件目は「かな」(後ろの出現)
    assert.strictEqual(r.text, 'かな と X');
    assert.deepStrictEqual(r.applied, [0, 1]);
}
{
    const r = aiApplyReplacements('か', [
        { ng: 'か', ok: 'かな' },
        { ng: 'かな', ok: 'X' }
    ]);
    assert.strictEqual(r.text, 'かな');
    assert.deepStrictEqual(r.applied, [0]);
    assert.deepStrictEqual(r.notFound, [1]);
}

// 範囲が重なる2件は先の item を優先し、後は notFound
{
    const r = aiApplyReplacements('abcdef', [
        { ng: 'abcd', ok: 'X' },
        { ng: 'cdef', ok: 'Y' }
    ]);
    assert.strictEqual(r.text, 'Xef');
    assert.deepStrictEqual(r.applied, [0]);
    assert.deepStrictEqual(r.notFound, [1]);
}

// 見つからない ng は notFound、本文不変
{
    const r = aiApplyReplacements('こんにちは', [{ ng: 'さようなら', ok: 'x' }]);
    assert.strictEqual(r.text, 'こんにちは');
    assert.deepStrictEqual(r.applied, []);
    assert.deepStrictEqual(r.notFound, [0]);
}

// 空 ng / ng===ok はスキップ
{
    const r = aiApplyReplacements('abc', [
        { ng: '', ok: 'x' },
        { ng: 'a', ok: 'a' },
        { ng: 'b', ok: 'B' }
    ]);
    assert.strictEqual(r.text, 'aBc');
    assert.deepStrictEqual(r.applied, [2]);
    assert.deepStrictEqual(r.notFound, [0, 1]);
}

// --- aiFindRange ---
assert.deepStrictEqual(aiFindRange('abcabc', 'bc', 0, []), { start: 1, end: 3 });
assert.deepStrictEqual(aiFindRange('abcabc', 'bc', 0, [{ start: 1, end: 3 }]), { start: 4, end: 6 });
assert.deepStrictEqual(aiFindRange('abcabc', 'bc', 2, []), { start: 4, end: 6 });
assert.strictEqual(aiFindRange('abc', 'x', 0, []), null);
assert.strictEqual(aiFindRange('abc', '', 0, []), null);

// --- aiReplaceAt ---
assert.strictEqual(aiReplaceAt('0123456789', 3, 6, '345', 'X'), '012X6789');
// 位置がずれていたら indexOf にフォールバック
assert.strictEqual(aiReplaceAt('ZZ0123456789', 3, 6, '345', 'X'), 'ZZ012X6789');
// 見つからなければ null
assert.strictEqual(aiReplaceAt('0123456789', 3, 6, 'abc', 'X'), null);
// 範囲内が空(カーソル挿入)
assert.strictEqual(aiReplaceAt('abcd', 2, 2, '', 'XY'), 'abXYcd');

// --- aiNormalizeJsonRow ---
assert.deepStrictEqual(
    aiNormalizeJsonRow({ ng: 'a', ok: 'b', desc: 'c' }),
    { ng: 'a', ok: 'b', desc: 'c' }
);
assert.deepStrictEqual(
    aiNormalizeJsonRow({ ErrorLocation: 'a', Correction: 'b', Reason: 'c' }),
    { ng: 'a', ok: 'b', desc: 'c' }
);
assert.deepStrictEqual(
    aiNormalizeJsonRow({ 誤り: 'a', 修正: 'b', 理由: 'c' }),
    { ng: 'a', ok: 'b', desc: 'c' }
);
assert.strictEqual(aiNormalizeJsonRow({ ng: '', ok: 'b' }), null);
assert.strictEqual(aiNormalizeJsonRow('str'), null);
assert.strictEqual(aiNormalizeJsonRow(null), null);

console.log('edit_ai_core.test.js: all tests passed');
