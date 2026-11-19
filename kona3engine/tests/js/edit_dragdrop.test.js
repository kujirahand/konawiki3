// #397: drag & drop upload for the editor.
// Extracts insertFileRef / uploadOneFile / initFileDropUpload from
// kona3engine/resource/edit.js and runs them against a fake DOM.
const fs = require('fs')
const path = require('path')
const assert = require('assert')

const src = fs.readFileSync(path.join(__dirname, '..', '..', 'resource', 'edit.js'), 'utf8')

function extractFn(name) {
  const start = src.indexOf('function ' + name + '(')
  assert(start >= 0, 'function not found: ' + name)
  let i = src.indexOf('{', start)
  let depth = 0
  const from = i
  for (; i < src.length; i++) {
    if (src[i] === '{') depth++
    else if (src[i] === '}') { depth--; if (depth === 0) break }
  }
  return src.slice(start, i + 1)
}

// --- fake DOM pieces -------------------------------------------------------
function makeClassList() {
  const set = new Set()
  return {
    add: (c) => set.add(c),
    remove: (c) => set.delete(c),
    contains: (c) => set.has(c),
  }
}

const editTxt = {
  value: 'line1\nline2',
  selectionStart: 5,
  selectionEnd: 5,
  focused: false,
  classList: makeClassList(),
  focus() { this.focused = true },
  setSelectionRange(a, b) { this.selectionStart = a; this.selectionEnd = b },
}

const pageMode = { value: '' }
const editInfo = { textContent: '', text(t) { if (t !== undefined) { this.textContent = t } return this.textContent } }
const formEl = {
  listeners: {},
  addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn) },
  fire(type, ev) { (this.listeners[type] || []).forEach((fn) => fn(ev)) },
}
const fileInput = { files: [], value: 'x' }

const elements = {
  '#edit_txt': editTxt,
  '#page_mode': pageMode,
  '#file_upload_file': fileInput,
  '#wikiedit form': formEl,
  '#edit_info': editInfo,
  '#edit_token': { val: () => 'tok' },
  '#a_hash': { val: () => 'hash' },
}

global.qs = (sel) => elements[sel] || null
global.qq = (selOrEl) => {
  if (typeof selOrEl === 'string') {
    const el = elements[selOrEl]
    if (selOrEl === '#edit_info') return { text: (t) => editInfo.text(t) }
    return { val: () => (el && el.val ? el.val() : ''), attr: () => '', text: () => {} }
  }
  return { attr: () => 'index.php?FrontPage&edit' }
}
global.window = { kona3EditHighlight: null }
global.updateSearchCount = () => {}
global.handleChange = () => {}

// fetch stub: echoes the uploaded file name back as the saved name
const fetched = []
global.fetch = (url, opts) => {
  const file = opts.body.get('file')
  fetched.push(file.name)
  return Promise.resolve({ json: () => Promise.resolve({ result: 'ok', name: file.name }) })
}

// load the functions under test
eval(extractFn('insertFileRef'))
eval(extractFn('uploadOneFile'))
eval(extractFn('uploadFile'))
eval(extractFn('initFileDropUpload'))

async function main() {
  // --- insertFileRef markup (#397 formats) ---
  pageMode.value = 'Markdown'
  editTxt.value = 'line1\nline2'; editTxt.selectionStart = editTxt.selectionEnd = 5
  insertFileRef('pic.png')
  assert(editTxt.value.includes('\n![pic.png](pic.png)\n'), 'markdown markup inserted: ' + editTxt.value)

  pageMode.value = ''
  editTxt.value = 'line1\nline2'; editTxt.selectionStart = editTxt.selectionEnd = 5
  insertFileRef('doc.zip')
  assert(editTxt.value.includes('\n#ref(doc.zip,*doc.zip)\n'), 'kona markup inserted: ' + editTxt.value)

  // --- drop flow: two files upload in order and insert markup ---
  pageMode.value = 'Markdown'
  editTxt.value = 'body'; editTxt.selectionStart = editTxt.selectionEnd = 4
  initFileDropUpload()
  assert(formEl.listeners.drop && formEl.listeners.drop.length === 1, 'drop handler registered')
  assert(formEl.listeners.dragover && formEl.listeners.dragover.length === 1, 'dragover handler registered')

  let prevented = false
  const dropEv = {
    type: 'drop',
    preventDefault() { prevented = true },
    dataTransfer: { types: ['Files'], files: [new File(['x'], 'a.png'), new File(['x'], 'b.zip')] },
  }
  formEl.fire('drop', dropEv)
  assert(prevented, 'drop is preventDefaulted (browser must not open the file)')
  await new Promise((r) => setTimeout(r, 30))
  assert.deepStrictEqual(fetched, ['a.png', 'b.zip'], 'files uploaded in drop order: ' + fetched.join(','))
  assert(editTxt.value.includes('![a.png](a.png)'), 'first file markup inserted: ' + editTxt.value)
  assert(editTxt.value.includes('![b.zip](b.zip)'), 'second file markup inserted: ' + editTxt.value)

  // --- dragover shows the active class, dragleave removes it ---
  formEl.fire('dragenter', { type: 'dragenter', preventDefault() {}, dataTransfer: { types: ['Files'], files: [] } })
  assert(editTxt.classList.contains('kona3-drop-active'), 'drop-active class added on dragenter')
  formEl.fire('dragleave', { type: 'dragleave', preventDefault() {}, dataTransfer: { types: ['Files'], files: [] } })
  assert(!editTxt.classList.contains('kona3-drop-active'), 'drop-active class removed on dragleave')

  // --- dragging text (no Files) is left alone ---
  let prevented2 = false
  formEl.fire('drop', { type: 'drop', preventDefault() { prevented2 = true }, dataTransfer: { types: ['text/plain'], files: [] } })
  assert(!prevented2, 'text drags are not intercepted')

  console.log('edit_dragdrop.test.js: ALL OK')
}

main().catch((e) => { console.error(e); process.exit(1) })
