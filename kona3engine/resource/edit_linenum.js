// Line numbers for the editor textarea (issue #381).
// A gutter is placed to the left of the textarea (or of the highlight
// wrapper). Numbers are logical lines (split by "\n"); the height of each
// number follows the wrapped height of its line, measured with a hidden
// mirror element that copies the textarea's text metrics.
(function () {
  'use strict'

  var STORAGE_KEY = 'kona3_edit_linenum'
  var MAX_LINES = 20000
  var POLL_MS = 400

  var ta = null
  var editor = null
  var gutter = null
  var enabled = false
  var frame = null
  var timer = null
  var lastValue = null
  var lastWidth = -1
  var lastSig = ''
  var resizeObserver = null

  function readStorage(key) {
    try { return window.localStorage.getItem(key) } catch (e) { return null }
  }

  function writeStorage(key, value) {
    try { window.localStorage.setItem(key, value) } catch (e) { /* ignore */ }
  }

  // Hidden element that lays text out exactly like the textarea does.
  function createMirror(el) {
    var cs = window.getComputedStyle(el)
    var m = document.createElement('div')
    var s = m.style
    s.position = 'absolute'
    s.visibility = 'hidden'
    s.left = '-99999px'
    s.top = '0'
    s.boxSizing = 'border-box'
    s.width = el.clientWidth + 'px'
    s.border = '0'
    s.whiteSpace = 'pre-wrap'
    s.overflowWrap = 'break-word'
    s.wordBreak = 'normal'
    s.fontFamily = cs.fontFamily
    s.fontSize = cs.fontSize
    s.fontWeight = cs.fontWeight
    s.lineHeight = cs.lineHeight
    s.letterSpacing = cs.letterSpacing
    s.tabSize = cs.tabSize
    s.fontKerning = 'none'
    s.fontVariantLigatures = 'none'
    s.paddingTop = cs.paddingTop
    s.paddingBottom = cs.paddingBottom
    s.paddingLeft = cs.paddingLeft
    s.paddingRight = cs.paddingRight
    document.body.appendChild(m)
    return m
  }

  // Rendered height (px) of every logical line.
  function lineHeights(el) {
    var lines = el.value.split('\n')
    var m = createMirror(el)
    try {
      var frag = document.createDocumentFragment()
      for (var i = 0; i < lines.length; i++) {
        var d = document.createElement('div')
        d.textContent = lines[i] === '' ? '​' : lines[i]
        frag.appendChild(d)
      }
      m.appendChild(frag)
      var heights = []
      var kids = m.children
      for (var j = 0; j < kids.length; j++) {
        heights.push(kids[j].getBoundingClientRect().height)
      }
      return heights
    } finally {
      document.body.removeChild(m)
    }
  }

  // Vertical offset (px, from the top of the scrollable content) of the
  // line that contains the offset pos.
  function caretTop(el, pos) {
    var m = createMirror(el)
    try {
      m.textContent = el.value.substring(0, pos)
      var mark = document.createElement('span')
      mark.textContent = '​'
      m.appendChild(mark)
      return mark.offsetTop
    } finally {
      document.body.removeChild(m)
    }
  }

  // Text metrics of the textarea; they change when the syntax highlight
  // is toggled, which re-wraps the lines.
  function metricsSig(el) {
    var cs = window.getComputedStyle(el)
    return [cs.fontFamily, cs.fontSize, cs.lineHeight, cs.letterSpacing, cs.paddingLeft, cs.paddingRight].join('|')
  }

  function digitsOf(n) {
    return String(Math.max(1, n)).length
  }

  function render() {
    frame = null
    if (!enabled || !ta || !gutter) { return }
    var value = ta.value
    var lineCount = value.split('\n').length
    if (lineCount > MAX_LINES) {
      gutter.style.display = 'none'
      lastValue = value
      return
    }
    gutter.style.display = ''
    var cs = window.getComputedStyle(ta)
    gutter.style.fontFamily = cs.fontFamily
    gutter.style.fontSize = cs.fontSize
    gutter.style.lineHeight = cs.lineHeight
    gutter.style.paddingTop = cs.paddingTop
    gutter.style.paddingBottom = cs.paddingBottom
    gutter.style.width = 'calc(' + (digitsOf(lineCount) + 1) + 'ch + 14px)'
    var heights = lineHeights(ta)
    var frag = document.createDocumentFragment()
    for (var i = 0; i < heights.length; i++) {
      var d = document.createElement('div')
      d.className = 'k3ln-num'
      d.style.height = heights[i] + 'px'
      d.textContent = String(i + 1)
      frag.appendChild(d)
    }
    gutter.textContent = ''
    gutter.appendChild(frag)
    gutter.scrollTop = ta.scrollTop
    lastValue = value
    lastWidth = ta.clientWidth
    lastSig = metricsSig(ta)
  }

  function schedule() {
    if (frame !== null || !enabled) { return }
    if (typeof window.requestAnimationFrame === 'function') {
      frame = window.requestAnimationFrame(render)
    } else {
      frame = window.setTimeout(render, 16)
    }
  }

  function onScroll() {
    if (gutter) { gutter.scrollTop = ta.scrollTop }
  }

  // Programmatic changes of ta.value fire no event, so poll cheaply.
  function poll() {
    if (!enabled || !ta) { return }
    if (ta.value !== lastValue || ta.clientWidth !== lastWidth || metricsSig(ta) !== lastSig) { schedule() }
  }

  function enable() {
    if (enabled) { return }
    ta = document.getElementById('edit_txt')
    editor = ta ? ta.closest('.editor') : null
    if (!ta || !editor) { return }
    gutter = document.createElement('div')
    gutter.className = 'k3ln-gutter'
    gutter.setAttribute('aria-hidden', 'true')
    editor.insertBefore(gutter, editor.firstChild)
    editor.classList.add('k3ln-on')
    enabled = true
    ta.addEventListener('input', schedule)
    ta.addEventListener('scroll', onScroll)
    window.addEventListener('resize', schedule)
    if (typeof window.ResizeObserver === 'function') {
      resizeObserver = new window.ResizeObserver(schedule)
      resizeObserver.observe(ta)
    }
    timer = window.setInterval(poll, POLL_MS)
    render()
  }

  function disable() {
    if (!enabled) { return }
    enabled = false
    if (ta) {
      ta.removeEventListener('input', schedule)
      ta.removeEventListener('scroll', onScroll)
    }
    window.removeEventListener('resize', schedule)
    if (resizeObserver) { resizeObserver.disconnect(); resizeObserver = null }
    if (timer !== null) { window.clearInterval(timer); timer = null }
    if (gutter && gutter.parentNode) { gutter.parentNode.removeChild(gutter) }
    if (editor) { editor.classList.remove('k3ln-on') }
    gutter = null
    lastValue = null
  }

  function serverDefaultEnabled() {
    var input = document.getElementById('editor_line_number_default')
    return !input || input.value !== '0'
  }

  function init() {
    if (!document.getElementById('edit_txt')) { return }
    var preference = readStorage(STORAGE_KEY)
    if (preference === '1' || (preference === null && serverDefaultEnabled())) {
      enable()
    }
  }

  window.kona3EditLineNumber = {
    enable: function () { writeStorage(STORAGE_KEY, '1'); enable() },
    disable: function () { writeStorage(STORAGE_KEY, '0'); disable() },
    isEnabled: function () { return enabled },
    refresh: render
  }
  window.kona3EditorMetrics = { caretTop: caretTop, lineHeights: lineHeights }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true })
  } else {
    init()
  }
}())
