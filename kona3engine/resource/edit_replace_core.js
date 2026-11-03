// DOM-independent helpers of the editor text replace (issue #381).
// Loaded before edit.js; also required from the Node tests.
// The preview table and the real replacement use the same match list, so
// what the user sees is exactly what is replaced.
//
// Matching rules (same as the former replace form):
//  - case-sensitive
//  - RegExp mode is applied line by line, so ^ and $ match per line and
//    a pattern never spans a line break

// Compile the keyword. Returns { re } or { error } (invalid RegExp).
function replaceCompile(query, useRegExp) {
  if (!query) {
    return { empty: true }
  }
  if (!useRegExp) {
    return { plain: query }
  }
  try {
    return { re: new RegExp(query, 'g') }
  } catch (e) {
    return { error: String(e && e.message ? e.message : e) }
  }
}

// Match offsets of query in text, at most max (0: unlimited).
// Returns { error } for an invalid RegExp, else
// { matches: [{pos, len, line, groups}], more }.
// `more` is true when the scan stopped because max was reached and the
// text may hold further matches. `groups` is the exec() result (RegExp
// mode only), needed to expand $1 etc. in the replacement.
function replaceFindMatches(text, query, useRegExp, max) {
  var c = replaceCompile(query, useRegExp)
  if (c.error) { return { error: c.error } }
  var matches = []
  if (c.empty) { return { matches: matches, more: false } }
  var more = false
  var line = 1

  if (c.plain !== undefined) {
    var pos = 0
    var scanned = 0
    while (true) {
      var i = text.indexOf(c.plain, pos)
      if (i < 0) { break }
      if (max > 0 && matches.length >= max) { more = true; break }
      for (var j = text.indexOf('\n', scanned); j >= 0 && j < i; j = text.indexOf('\n', j + 1)) {
        line++
        scanned = j + 1
      }
      matches.push({ pos: i, len: c.plain.length, line: line, groups: null })
      pos = i + c.plain.length
    }
    return { matches: matches, more: more }
  }

  var lines = text.split('\n')
  var offset = 0
  for (var n = 0; n < lines.length && !more; n++) {
    var re = new RegExp(c.re.source, 'g')
    var s = lines[n]
    var m
    while ((m = re.exec(s)) !== null) {
      if (max > 0 && matches.length >= max) { more = true; break }
      matches.push({ pos: offset + m.index, len: m[0].length, line: n + 1, groups: m })
      if (m[0].length === 0) {
        // A zero-length match (^, $, x* ...) is a real match, like in
        // String.prototype.replace: keep it and step over it so the scan
        // moves on.
        re.lastIndex++
        if (re.lastIndex > s.length) { break }
      }
    }
    offset += s.length + 1
  }
  return { matches: matches, more: more }
}

// Replacement text of one match. In RegExp mode $$, $&, $`, $', $n, $nn
// and $<name> are expanded exactly like String.prototype.replace does
// (the former replace form used it), including its rules for digits:
// "$10" is group 10 only when there are 10 groups, otherwise group 1
// followed by "0"; "$0" and out-of-range groups stay literal. $` and $'
// refer to the line, because RegExp mode works line by line.
function replaceExpand(val, match, useRegExp) {
  if (!useRegExp || !match.groups) { return val }
  var m = match.groups
  var count = m.length - 1
  return val.replace(/\$(\$|&|`|'|<[^>]*>|\d{1,2})/g, function (all, t) {
    if (t === '$') { return '$' }
    if (t === '&') { return m[0] }
    if (t === '`') { return m.input.substring(0, m.index) }
    if (t === "'") { return m.input.substring(m.index + m[0].length) }
    if (t.charAt(0) === '<') {
      // without named groups "$<" is literal text
      if (m.groups === undefined) { return all }
      var g = m.groups[t.slice(1, -1)]
      return g === undefined ? '' : g
    }
    var group = function (i) { return m[i] === undefined ? '' : m[i] }
    if (t.length === 2) {
      var nn = parseInt(t, 10)
      if (nn >= 1 && nn <= count) { return group(nn) }
      var d = parseInt(t.charAt(0), 10)
      if (d >= 1 && d <= count) { return group(d) + t.charAt(1) }
      return all
    }
    var n = parseInt(t, 10)
    return n >= 1 && n <= count ? group(n) : all
  })
}

// Rows of the preview table: the text around each match, with the match
// and what it becomes. Same shape as buildSearchResults plus `repl`.
// opts: context (24), limit (200), stopAtLimit (cut the scan after limit+1)
function buildReplaceResults(text, query, val, useRegExp, opts) {
  opts = opts || {}
  var context = opts.context > 0 ? opts.context : 24
  var limit = opts.limit > 0 ? opts.limit : 200
  var found = replaceFindMatches(text, query, useRegExp, opts.stopAtLimit ? limit + 1 : 0)
  if (found.error) { return { error: found.error, items: [], total: 0, truncated: false } }
  var items = []
  for (var k = 0; k < found.matches.length && items.length < limit; k++) {
    var mt = found.matches[k]
    var lineStart = text.lastIndexOf('\n', mt.pos - 1) + 1
    var lineEnd = text.indexOf('\n', mt.pos)
    if (lineEnd < 0) { lineEnd = text.length }
    var end = mt.pos + mt.len
    var from = Math.max(lineStart, mt.pos - context)
    var to = Math.min(lineEnd, end + context)
    var before = text.substring(from, mt.pos)
    var after = text.substring(end, to)
    if (from > lineStart) { before = '…' + before }
    if (to < lineEnd) { after = after + '…' }
    items.push({
      pos: mt.pos,
      len: mt.len,
      line: mt.line,
      before: before.replace(/\r/g, ''),
      match: text.substring(mt.pos, end).replace(/\r/g, ''),
      repl: replaceExpand(val, mt, useRegExp),
      after: after.replace(/\r/g, '')
    })
  }
  return {
    total: found.matches.length,
    truncated: found.more || found.matches.length > items.length,
    items: items
  }
}

// Apply the replacement to the given matches (ascending, non-overlapping).
function replaceApply(text, matches, val, useRegExp) {
  var out = ''
  var last = 0
  for (var i = 0; i < matches.length; i++) {
    out += text.substring(last, matches[i].pos) + replaceExpand(val, matches[i], useRegExp)
    last = matches[i].pos + matches[i].len
  }
  return out + text.substring(last)
}

// Replace every match. Returns { text, count, error }.
function replaceAllText(text, query, val, useRegExp) {
  var found = replaceFindMatches(text, query, useRegExp, 0)
  if (found.error) { return { error: found.error, text: text, count: 0 } }
  return { text: replaceApply(text, found.matches, val, useRegExp), count: found.matches.length }
}

// Replace the first match at or after `from`, wrapping around to the
// top. Returns { text, count, pos, end, error }; pos/end span the
// replacement in the new text, so the caret can be placed after it.
function replaceNextText(text, query, val, useRegExp, from) {
  var found = replaceFindMatches(text, query, useRegExp, 0)
  if (found.error) { return { error: found.error, text: text, count: 0 } }
  if (found.matches.length === 0) { return { text: text, count: 0 } }
  var target = found.matches[0]
  for (var i = 0; i < found.matches.length; i++) {
    if (found.matches[i].pos >= from) { target = found.matches[i]; break }
  }
  return replaceOneMatch(text, target, val, useRegExp)
}

// Replace only the match that starts at pos with length len (a row of the
// preview table). Returns count 0 when the text no longer has that match
// there, so a stale row never replaces the wrong text.
function replaceAtText(text, query, val, useRegExp, pos, len) {
  var found = replaceFindMatches(text, query, useRegExp, 0)
  if (found.error) { return { error: found.error, text: text, count: 0 } }
  for (var i = 0; i < found.matches.length; i++) {
    if (found.matches[i].pos === pos && found.matches[i].len === len) {
      return replaceOneMatch(text, found.matches[i], val, useRegExp)
    }
  }
  return { text: text, count: 0 }
}

function replaceOneMatch(text, mt, val, useRegExp) {
  var r = replaceExpand(val, mt, useRegExp)
  return {
    text: text.substring(0, mt.pos) + r + text.substring(mt.pos + mt.len),
    count: 1,
    pos: mt.pos,
    end: mt.pos + r.length
  }
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    replaceCompile: replaceCompile,
    replaceFindMatches: replaceFindMatches,
    replaceExpand: replaceExpand,
    buildReplaceResults: buildReplaceResults,
    replaceAllText: replaceAllText,
    replaceNextText: replaceNextText,
    replaceAtText: replaceAtText
  }
}
