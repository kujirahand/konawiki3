'use strict';

const assert = require('assert');
const { computeSearchMatches, findSearchTarget, buildSearchResults } = require('../../resource/edit_search_core.js');

// computeSearchMatches
assert.deepStrictEqual(computeSearchMatches('abcABCabc', 'abc'), [0, 3, 6]);
assert.deepStrictEqual(computeSearchMatches('aaaa', 'aa'), [0, 2]);
assert.deepStrictEqual(computeSearchMatches('abc', ''), []);

// findSearchTarget wraps around
assert.strictEqual(findSearchTarget([], 0, 1), -1);
assert.strictEqual(findSearchTarget([2, 5], 3, 1), 1);
assert.strictEqual(findSearchTarget([2, 5], 6, 1), 0);
assert.strictEqual(findSearchTarget([2, 5], 2, -1), 1);

// buildSearchResults: line numbers + context
{
  const text = 'first\nsecond foo here\n\nfoo again\nlast foo';
  const r = buildSearchResults(text, 'foo');
  assert.strictEqual(r.total, 3);
  assert.strictEqual(r.truncated, false);
  assert.deepStrictEqual(r.items.map(i => i.line), [2, 4, 5]);
  assert.strictEqual(r.items[0].before, 'second ');
  assert.strictEqual(r.items[0].match, 'foo');
  assert.strictEqual(r.items[0].after, ' here');
  assert.strictEqual(text.substr(r.items[1].pos, 3), 'foo');
  assert.strictEqual(r.items[2].after, '');
}

// match keeps its original case
{
  const r = buildSearchResults('Hello WORLD', 'world');
  assert.strictEqual(r.items[0].match, 'WORLD');
}

// context is cut with an ellipsis and never crosses a line break
{
  const long = 'x'.repeat(50) + 'KEY' + 'y'.repeat(50);
  const r = buildSearchResults('top\n' + long + '\nbottom', 'KEY', { context: 10 });
  assert.strictEqual(r.items[0].line, 2);
  assert.strictEqual(r.items[0].before, '…' + 'x'.repeat(10));
  assert.strictEqual(r.items[0].after, 'y'.repeat(10) + '…');
}

// CRLF text does not leak \r into the table
{
  const r = buildSearchResults('a foo b\r\nfoo c\r\n', 'foo');
  assert.strictEqual(r.items[1].line, 2);
  assert.strictEqual(r.items[0].after, ' b');
}

// the number of rows is limited, total stays accurate
{
  const r = buildSearchResults('a\n'.repeat(500), 'a', { limit: 50 });
  assert.strictEqual(r.items.length, 50);
  assert.strictEqual(r.total, 500);
  assert.strictEqual(r.truncated, true);
  assert.strictEqual(r.items[49].line, 50);
}

// multibyte text
{
  const r = buildSearchResults('あいう\nえおかきくけこ', 'かき');
  assert.strictEqual(r.items[0].line, 2);
  assert.strictEqual(r.items[0].before, 'えお');
  assert.strictEqual(r.items[0].after, 'くけこ');
}

// live search: the scan is cut off once the limit is exceeded
{
  const text = 'ab\n'.repeat(100);
  assert.strictEqual(computeSearchMatches(text, 'ab', 5).length, 5);
  assert.strictEqual(computeSearchMatches(text, 'ab').length, 100);
  const r = buildSearchResults(text, 'ab', { limit: 30, stopAtLimit: true });
  assert.strictEqual(r.items.length, 30);
  assert.strictEqual(r.total, 31); // limit + 1 means "more than limit"
  assert.strictEqual(r.truncated, true);
  const few = buildSearchResults('ab ab', 'ab', { limit: 30, stopAtLimit: true });
  assert.strictEqual(few.total, 2);
  assert.strictEqual(few.truncated, false);
}

console.log('edit_search_core.test.js: all tests passed');
