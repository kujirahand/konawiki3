'use strict';

const assert = require('assert');
const {
  replaceFindMatches, replaceExpand, buildReplaceResults,
  replaceAllText, replaceNextText, replaceAtText
} = require('../../resource/edit_replace_core.js');

// --- plain matching is case-sensitive, with line numbers ---
{
  const r = replaceFindMatches('foo Foo\nfoo', 'foo', false, 0);
  assert.deepStrictEqual(r.matches.map(m => [m.pos, m.line]), [[0, 1], [8, 2]]);
  assert.strictEqual(r.more, false);
}

// --- the scan stops at max and reports that more exist ---
{
  const r = replaceFindMatches('a\n'.repeat(100), 'a', false, 5);
  assert.strictEqual(r.matches.length, 5);
  assert.strictEqual(r.more, true);
  const exact = replaceFindMatches('a a a', 'a', false, 3);
  assert.strictEqual(exact.matches.length, 3);
  assert.strictEqual(exact.more, false);
}

// --- RegExp: per line, offsets, invalid pattern ---
{
  const r = replaceFindMatches('ab12\ncd345\n', '\\d+', true, 0);
  assert.deepStrictEqual(r.matches.map(m => [m.pos, m.len, m.line]), [[2, 2, 1], [7, 3, 2]]);
  assert.ok(replaceFindMatches('x', '(', true, 0).error);
  // ^ and $ work per line; a pattern does not span lines
  assert.strictEqual(replaceAllText('a\na', '^a$', 'X', true).text, 'X\nX');
  assert.strictEqual(replaceFindMatches('a\nb', 'a\\sb', true, 0).matches.length, 0);
  // empty matches are skipped, not looped on
  assert.strictEqual(replaceFindMatches('abc', 'x*', true, 0).matches.length, 0);
}

// --- replacement expansion in RegExp mode only ---
{
  const m = replaceFindMatches('2026-11-03', '(\\d+)-(\\d+)-(\\d+)', true, 0).matches[0];
  assert.strictEqual(replaceExpand('$3/$2/$1', m, true), '03/11/2026');
  assert.strictEqual(replaceExpand('[$&]$$', m, true), '[2026-11-03]$');
  assert.strictEqual(replaceExpand('$9', m, true), '$9');
  assert.strictEqual(replaceExpand('$1', m, false), '$1'); // plain mode: literal
  const named = replaceFindMatches('ab', '(?<x>a)', true, 0).matches[0];
  assert.strictEqual(replaceExpand('<$<x>>', named, true), '<a>');
}

// --- replace all ---
{
  const r = replaceAllText('foo bar foo', 'foo', 'X', false);
  assert.strictEqual(r.text, 'X bar X');
  assert.strictEqual(r.count, 2);
  // the replacement may contain the keyword; no recursion
  assert.strictEqual(replaceAllText('a', 'a', 'aa', false).text, 'aa');
  assert.strictEqual(replaceAllText('abc', 'x', 'y', false).count, 0);
  assert.strictEqual(replaceAllText('abc', '', 'y', false).count, 0);
  assert.strictEqual(replaceAllText('a1 b22', '(\\w)(\\d+)', '$2$1', true).text, '1a 22b');
  assert.ok(replaceAllText('abc', '(', 'y', true).error);
  // plain mode: "$1" is literal, no special meaning
  assert.strictEqual(replaceAllText('a', 'a', '$&', false).text, '$&');
}

// --- replace next: from the caret, wrapping around ---
{
  const t = 'foo a foo b foo';
  let r = replaceNextText(t, 'foo', 'X', false, 3);
  assert.strictEqual(r.text, 'foo a X b foo');
  assert.strictEqual(r.pos, 6);
  assert.strictEqual(r.end, 7);
  r = replaceNextText(t, 'foo', 'X', false, 13); // after the last match: wrap
  assert.strictEqual(r.text, 'X a foo b foo');
  r = replaceNextText(t, 'nope', 'X', false, 0);
  assert.strictEqual(r.count, 0);
}

// --- replace at: only that match, and never a stale one ---
{
  const t = 'foo foo foo';
  const r = replaceAtText(t, 'foo', 'X', false, 4, 3);
  assert.strictEqual(r.text, 'foo X foo');
  assert.strictEqual(r.count, 1);
  // the text was edited: offset 4 no longer starts a match
  const stale = replaceAtText('xfoo foo foo', 'foo', 'X', false, 4, 3);
  assert.strictEqual(stale.count, 0);
  assert.strictEqual(stale.text, 'xfoo foo foo');
}

// --- preview rows: context, replacement, same result as the real replace ---
{
  const text = 'top\nsay foo here\nfoo again';
  const r = buildReplaceResults(text, 'foo', 'BAR', false);
  assert.strictEqual(r.total, 2);
  assert.deepStrictEqual(r.items.map(i => i.line), [2, 3]);
  assert.strictEqual(r.items[0].before, 'say ');
  assert.strictEqual(r.items[0].match, 'foo');
  assert.strictEqual(r.items[0].repl, 'BAR');
  assert.strictEqual(r.items[0].after, ' here');
  // preview and the actual replacement agree
  const re = buildReplaceResults('2026-11', '(\\d+)-(\\d+)', '$2/$1', true);
  assert.strictEqual(re.items[0].repl, '11/2026');
  assert.strictEqual(replaceAllText('2026-11', '(\\d+)-(\\d+)', '$2/$1', true).text, '11/2026');
}

// --- live search style cut-off ---
{
  const r = buildReplaceResults('ab\n'.repeat(100), 'ab', 'X', false, { limit: 30, stopAtLimit: true });
  assert.strictEqual(r.items.length, 30);
  assert.strictEqual(r.truncated, true);
  const few = buildReplaceResults('ab ab', 'ab', 'X', false, { limit: 30, stopAtLimit: true });
  assert.strictEqual(few.truncated, false);
  assert.strictEqual(few.total, 2);
}

// --- invalid RegExp is reported, not thrown ---
{
  const r = buildReplaceResults('abc', '[', 'x', true);
  assert.ok(r.error);
  assert.strictEqual(r.items.length, 0);
}

// --- multibyte / CRLF ---
{
  const r = buildReplaceResults('あいう\r\nえおかき', 'おか', 'OK', false);
  assert.strictEqual(r.items[0].line, 2);
  assert.strictEqual(r.items[0].before, 'え');
  assert.strictEqual(r.items[0].after, 'き');
  assert.strictEqual(replaceAllText('a\r\nb', 'b', 'c', false).text, 'a\r\nc');
}

console.log('edit_replace_core.test.js: all tests passed');
