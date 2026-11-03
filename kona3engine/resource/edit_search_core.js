// DOM-independent helpers of the editor text search (issue #153, #381).
// Loaded before edit.js; also required from the Node tests.

// All match offsets of query in text (case-insensitive, non-overlapping).
// With max > 0 the scan stops as soon as max matches have been found.
function computeSearchMatches(text, query, max) {
  var matches = []
  if (!query) {
    return matches
  }
  var hay = text.toLowerCase()
  var needle = query.toLowerCase()
  // toLowerCase() can change the string length for a few characters
  // (e.g. 'İ'), which would shift every offset. Fall back to a
  // case-sensitive search in that case (review follow-up for PR #345).
  if (hay.length !== text.length || needle.length !== query.length) {
    hay = text
    needle = query
  }
  var pos = 0
  while (true) {
    var i = hay.indexOf(needle, pos)
    if (i < 0) {
      break
    }
    matches.push(i)
    if (max > 0 && matches.length >= max) {
      break
    }
    pos = i + Math.max(1, needle.length)
  }
  return matches
}

// Index of the match to jump to from currentPos: the first match at or
// after currentPos for dir=1, the last match before currentPos for
// dir=-1, wrapping around. -1 when there are no matches.
function findSearchTarget(matches, currentPos, dir) {
  if (matches.length === 0) {
    return -1
  }
  var i
  if (dir > 0) {
    for (i = 0; i < matches.length; i++) {
      if (matches[i] >= currentPos) {
        return i
      }
    }
    return 0
  }
  for (i = matches.length - 1; i >= 0; i--) {
    if (matches[i] < currentPos) {
      return i
    }
  }
  return matches.length - 1
}

// Build the rows of the search result table (issue #381).
// Returns { total, truncated, items: [{pos, len, line, before, match,
// after}] }. `line` is the 1-based logical line number; before/after are
// the text around the match on the same line, at most opts.context
// characters each (default 24), cut with an ellipsis.
// With opts.stopAtLimit the scan is cut off once more than opts.limit
// matches exist (live search): total is then limit + 1 and truncated is
// true, which means "more than limit".
function buildSearchResults(text, query, opts) {
  opts = opts || {}
  var context = opts.context > 0 ? opts.context : 24
  var limit = opts.limit > 0 ? opts.limit : 200
  var matches = computeSearchMatches(text, query, opts.stopAtLimit ? limit + 1 : 0)
  var items = []
  var line = 1
  var scanned = 0
  for (var k = 0; k < matches.length && items.length < limit; k++) {
    var pos = matches[k]
    for (var j = text.indexOf('\n', scanned); j >= 0 && j < pos; j = text.indexOf('\n', j + 1)) {
      line++
      scanned = j + 1
    }
    var lineStart = text.lastIndexOf('\n', pos - 1) + 1
    var lineEnd = text.indexOf('\n', pos)
    if (lineEnd < 0) { lineEnd = text.length }
    var end = Math.min(pos + query.length, lineEnd)
    var from = Math.max(lineStart, pos - context)
    var to = Math.min(lineEnd, end + context)
    var before = text.substring(from, pos)
    var after = text.substring(end, to)
    if (from > lineStart) { before = '…' + before }
    if (to < lineEnd) { after = after + '…' }
    items.push({
      pos: pos,
      len: query.length,
      line: line,
      before: before.replace(/\r/g, ''),
      match: text.substring(pos, end).replace(/\r/g, ''),
      after: after.replace(/\r/g, '')
    })
  }
  return { total: matches.length, truncated: matches.length > items.length, items: items }
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    computeSearchMatches: computeSearchMatches,
    findSearchTarget: findSearchTarget,
    buildSearchResults: buildSearchResults
  }
}
