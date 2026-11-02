// -----------------------------------------------
// konawiki3 - edit.js
// -----------------------------------------------

// const
const LS_KEY_AUTOSAVE = 'kona3:edit:autosave'
const TIMER_INTERVAL = 1000 * 60 // 1min
// session keepalive (issue #64): while the edit page is open, contact the
// server periodically so the login session and the edit token stay alive.
// When the session is lost, poll faster so a re-login in another tab is
// picked up quickly and the pending text can be saved again.
const KEEPALIVE_INTERVAL = 1000 * 60 * 5 // 5min
const KEEPALIVE_TICK = 1000 * 15 // 15sec
const KEEPALIVE_RETRY_INTERVAL = 1000 * 20 // 20sec while session is lost

// var
var isChanged = false
var timerIdAutosave = 0
var timerIdKeepalive = 0
var lastKeepaliveTime = 0
var isSessionLost = false // セッション切れを検知したかどうか (issue #64)
var sessionRetryCount = 0 // keepalive復旧時の自動再保存の試行回数
const SESSION_RETRY_MAX = 3 // 自動再保存は最大3回まで (繰り返し失敗時のループ防止)
var isProcessingAjax = false // Ajaxの待ち合わせ中
var isDebug = false // このファイルのJSをデバッグする時はtrueにする
var postId = 1
var replaceUndoBuffer = ''

// init
qq(edit_init)

// detect storage key
var href = location.href
href = href.replace(/\?.*$/, '')
href = href.replace('index.php', '')
href = href.replace(/(http|https)\:\/\//, '')
var STORAGE_KEY = 'kona3:' + href

function elog(message) {
  if (isDebug) {
    console.log(message)
  }
}

function edit_init() {
  // editor key event
  const edit_txt = qs('#edit_txt')
  edit_txt.addEventListener('keydown', editorKeydownHandler, false)
  edit_txt.addEventListener('input', () => {
    handleChange(true, 'input')
  })

  // set button event
  qq('#temporarily_save_btn').click(clickTempSaveButton)
  const git_save_btn = qs('#git_save_btn')
  if (git_save_btn) {
    qq(git_save_btn).click(git_save)
  }
  qq('#ls_load_btn').click(loadTextFromLS)
  qq('#autosave').click(autoSaveClickHandler)
  const k3hl_toggle = qs('#k3hl_toggle')
  if (k3hl_toggle) {
    const hl_api = window.kona3EditHighlight
    k3hl_toggle.checked = !!(hl_api && hl_api.isEnabled())
    k3hl_toggle.addEventListener('change', () => {
      const api = window.kona3EditHighlight
      if (!api) {
        return
      }
      if (k3hl_toggle.checked) {
        api.enable()
      } else {
        api.disable()
      }
    })
  }
  const page_mode_el = qs('#page_mode')
  if (page_mode_el) {
    page_mode_el.addEventListener('change', () => {
      handleChange(true, 'page_mode')
    })
  }
  const search_key_el = qs('#search_key')
  if (search_key_el) {
    search_key_el.addEventListener('input', updateSearchCount)
    search_key_el.addEventListener('keydown', (e) => {
      // Enter that commits an IME composition must not trigger a search
      if (e.isComposing || e.keyCode === 229) { return }
      if (e.key === 'Enter') {
        e.preventDefault()
        if (e.shiftKey) { searchPrev() } else { searchNext() }
      }
    })
  }
  // Keep the match count in sync while the text is edited (issue #153)
  edit_txt.addEventListener('input', updateSearchCount)
  // Keep an open conflict diff in sync while the text is edited (#153)
  edit_txt.addEventListener('input', refreshConflictDiffIfOpen)
  // Ctrl+F / Cmd+F opens the drawer search panel (issue #153). Guards
  // (review follow-up for PR #345): no Shift/Alt combos, no IME
  // composition, Cmd only on Mac (Ctrl+F is forward-char there) and
  // Ctrl only elsewhere, and never steal a key the user bound to an
  // editor/AI shortcut.
  document.addEventListener('keydown', (e) => {
    if (e.isComposing || e.shiftKey || e.altKey) { return }
    if (e.key !== 'f' && e.key !== 'F') { return }
    const isMac = /Mac|iPhone|iPad/.test(navigator.platform || '')
    const modOk = isMac ? (e.metaKey && !e.ctrlKey) : (e.ctrlKey && !e.metaKey)
    if (!modOk) { return }
    const shortcutIds = ['editor_shortcut_temp_save', 'editor_shortcut_new',
      'ai_shortcut_complete', 'ai_shortcut_spellcheck',
      'ai_shortcut_user_prompt1', 'ai_shortcut_user_prompt2']
    for (const id of shortcutIds) {
      if (matchesShortcut(e, getShortcutValue(id))) { return }
    }
    e.preventDefault()
    searchOnClick(true)
  })
  if (qs('#ai_actions')) {
    aiInitPanel()
  }
  loadAutoSave()
  startKeepalive()

  // Esc closes the tool drawer (issue #153). Ignore Esc while an IME
  // composition is in progress, and do nothing when it is already closed.
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || e.isComposing) { return }
    const drawer = qs('#edit_drawer')
    if (drawer && drawer.classList.contains('open')) { closeEditDrawer() }
  })
  // Heading sync between the editor and the preview (issue #153).
  initHeadingSync()
  // Keep the preview layout in sync with the window width (issue #153):
  // split on wide screens, full-width preview on narrow screens.
  window.addEventListener('resize', () => {
    if (previewMode === 'off') { return }
    const desired = window.innerWidth < 800 ? 'full' : 'split'
    if (desired !== previewMode) { setPreviewMode(desired) }
  })

  // フォーム送信時にも本文を localStorage へ退避する (issue #64)。
  // サーバー側の退避 (セッション) と合わせて、送信に失敗しても
  // 「一時保存を読込」から本文を取り戻せるようにする。
  const editForm = qs('#wikiedit form')
  if (editForm) {
    editForm.addEventListener('submit', () => { saveTextToLS() })
  }

  // shortcut
  qq(window).keydown(function (e) {
    if (matchesShortcut(e, getShortcutValue('editor_shortcut_temp_save'))) {
      if (isChanged) {
        ajaxSave('Shortcut:' + getShortcutValue('editor_shortcut_temp_save'))
      }
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('editor_shortcut_new'))) {
      const url = document.getElementById('new_btn_url').href
      console.log('open new page:', url)
      window.open(url)
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('ai_shortcut_complete'))) {
      aiRunAction('append', { direct: true })
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('ai_shortcut_spellcheck'))) {
      aiSpellCheckOnClick()
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('ai_shortcut_user_prompt1'))) {
      aiRunUserPrompt('USER_PROMPT1')
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('ai_shortcut_user_prompt2'))) {
      aiRunUserPrompt('USER_PROMPT2')
      e.preventDefault()
    }
  })
}

function getShortcutValue(id) {
  const el = document.getElementById(id)
  return el ? el.value : ''
}

function matchesShortcut(e, shortcut) {
  if (!shortcut) { return false }
  const parts = shortcut.toLowerCase().split('+').map((s) => s.trim()).filter((s) => s !== '')
  const keyParts = parts.filter((s) => !['ctrl', 'control', 'cmd', 'meta', 'alt', 'option', 'shift'].includes(s))
  if (keyParts.length !== 1) { return false }
  const wantsCtrl = parts.includes('ctrl') || parts.includes('control')
  const wantsMeta = parts.includes('cmd') || parts.includes('meta')
  const wantsAlt = parts.includes('alt') || parts.includes('option')
  const wantsShift = parts.includes('shift')
  if (wantsCtrl && wantsMeta) { return false }
  if (wantsCtrl) {
    if (!e.ctrlKey && !e.metaKey) { return false }
    if (e.ctrlKey && e.metaKey) { return false }
  } else if (wantsMeta) {
    if (!e.metaKey || e.ctrlKey) { return false }
  } else if (e.ctrlKey || e.metaKey) {
    return false
  }
  if (Boolean(e.altKey) !== wantsAlt) { return false }
  if (Boolean(e.shiftKey) !== wantsShift) { return false }
  const key = normalizeShortcutKey(keyParts[0])
  const eventKey = normalizeShortcutKey(e.key || '')
  return key === eventKey
}

function normalizeShortcutKey(key) {
  key = (key || '').toLowerCase()
  if (key === ' ') { return 'space' }
  if (key === 'esc') { return 'escape' }
  return key
}

// handle editor change event
function handleChange(changed, reason) {
  elog(`changed: ${changed} - ${reason}`)
  isChanged = changed
  // set the topbar save state (issue #153)
  const saveState = qs('#edit_save_state')
  if (isChanged) {
    if (saveState) {
      saveState.textContent = '未保存の変更があります'
      saveState.classList.add('dirty')
    }
    use_beforeunload(true)
    setButtonsDisabled(false)
  } else {
    if (saveState) {
      saveState.textContent = '保存済み'
      saveState.classList.remove('dirty')
    }
    use_beforeunload(false)
    setButtonsDisabled(true)
  }
  schedulePreview()
}

// edit_txt.onkeydown
function editorKeydownHandler(event) {
  const c = event.keyCode
  if (37 <= c && c <= 40) { // arrow key
    return
  }
  if (c == 13) { // ENTER
    saveTextToLS()
  }
}

// auto save setting
function loadAutoSave() {
  const autosaveUI = qq('#autosave')
  // default is true
  autosaveUI.prop('checked', true)
  // load from localStorage
  const ls = localStorage[LS_KEY_AUTOSAVE]
  if (ls) {
    // console.log('localStorage.'+LS_KEY_AUTOSAVE+'=', ls);
    switch (ls) {
      case 'yes': autosaveUI.prop('checked', true); break
      case 'no': autosaveUI.prop('checked', false); break
    }
  }
  const autosave = autosaveUI.prop('checked')
  console.log('autosave=', autosave)
  // timer
  if (autosave) {
    if (timerIdAutosave > 0) { clearInterval(timerIdAutosave) }
    timerIdAutosave = setInterval(timerAutoSaveOnTime, TIMER_INTERVAL)
  }
}
function saveAutoSave(enabled) {
  localStorage[LS_KEY_AUTOSAVE] = (enabled) ? 'yes' : 'no'
}
function autoSaveClickHandler() {
  const autosave = qq('#autosave').prop('checked')
  saveAutoSave(autosave)
  loadAutoSave()
}

var use_unload_flag = false
function use_beforeunload(b) {
  if (use_unload_flag == b) return
  if (b) {
    qq(window).on('beforeunload', function () {
      return "Finish editing?"
    })
    qq('form').on('submit', function () {
      syncTagsToHidden() // タグを同期
      qq(window).off('beforeunload')
    })
  } else {
    qq(window).off('beforeunload')
  }
  use_unload_flag = b
}

function saveTextToLS() {
  const edit_txt = qs('#edit_txt')
  localStorage[STORAGE_KEY] = edit_txt.value
}

function loadTextFromLS() {
  if (!localStorage[STORAGE_KEY]) return
  if (!confirm('OK?')) return
  const edit_txt = qs('#edit_txt')
  edit_txt.value = localStorage[STORAGE_KEY]
}

function clickTempSaveButton() {
  elog('@clickTempSaveButton')
  sessionRetryCount = 0 // 手動保存は自動再保存の試行回数をリセットする
  saveTextToLS()
  ajaxSave('clickTempSaveButton')
}

// save to server by ajax
function ajaxSave(source) {
  // While an edit conflict is unresolved, the timer autosave would
  // only fail again with the same stale hash; skip it until the user
  // resolves the conflict (PR #349 review follow-up).
  if (source === 'timerAutoSaveOnTime' && conflictState &&
      qq('#a_hash').val() !== conflictState.serverHash) {
    return
  }
  ajaxProc('trywrite', source)
}

function git_save() {
  qq('#git_save_btn').prop('disabled', true)
  ajaxProc('trygit')
}

// Timer
function timerAutoSaveOnTime() {
  if (isChanged && !isProcessingAjax) {
    ajaxSave('timerAutoSaveOnTime')
  }
}

// -----------------------------------------------
// session keepalive (issue #64, 案3)
// -----------------------------------------------
function startKeepalive() {
  if (!qs('#edit_token')) return
  lastKeepaliveTime = Date.now()
  if (timerIdKeepalive > 0) { clearInterval(timerIdKeepalive) }
  timerIdKeepalive = setInterval(keepaliveTick, KEEPALIVE_TICK)
}

function keepaliveTick() {
  const interval = isSessionLost ? KEEPALIVE_RETRY_INTERVAL : KEEPALIVE_INTERVAL
  if (Date.now() - lastKeepaliveTime < interval) return
  lastKeepaliveTime = Date.now()
  keepalive()
}

// Contact the server to keep the session alive and to exchange the
// edit token. When the server answers with a (possibly renewed) token,
// replace the form's token so the open form never expires.
// This request itself re-logs in automatically when a "remember me"
// cookie is still valid (server side), and it also succeeds again
// after the user logs in again in another tab.
function keepalive() {
  const actionUrl = qq('#wikiedit form').attr('action')
  const params = new FormData()
  params.append('i_mode', 'ajax')
  params.append('q', 'keepalive')
  params.append('edit_token', qq('#edit_token').val())
  qq().post(actionUrl, params)
    .done(msg => {
      if (!msg || msg['result'] != 'ok') {
        handleSessionLost(msg && msg['reason'] ? msg['reason'] : '')
        return
      }
      // exchange the edit token
      const token = msg['edit_token']
      if (token && qq('#edit_token').val() !== token) {
        qq('#edit_token').val(token)
        elog('keepalive: edit token exchanged')
      }
      if (isSessionLost) {
        isSessionLost = false
        qq('#edit_info').html('[session restored]')
        qq('#edit_info').css('color', 'green')
        // save the pending text now that the session is back
        // (自動再保存は最大 SESSION_RETRY_MAX 回まで。失敗が続く場合は
        //  ループにせず、手動保存を促す)
        if (isChanged && !isProcessingAjax) {
          if (sessionRetryCount < SESSION_RETRY_MAX) {
            sessionRetryCount++
            ajaxSave('keepalive-restored')
          } else {
            qq('#edit_info').html('[session restored] Automatic re-save was stopped after several failures. Please press "Save temp" to save manually.')
            qq('#edit_info').css('color', 'red')
            setButtonsDisabled(false)
          }
        }
      }
    })
    .fail(function (error) {
      elog('keepalive failed: ' + error)
    })
}

// The session expired (or the edit token no longer matches).
// Keep the text in localStorage and tell the user how to recover:
// log in again (a link that opens in a new tab, so this page and its
// text stay open), then keepalive picks the new session up and the
// pending text can be saved again.
// Escape a string before embedding it into HTML (for #edit_info messages)
function escapeHtmlText(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function handleSessionLost(reason) {
  saveTextToLS()
  const wasLost = isSessionLost
  isSessionLost = true
  if (wasLost) return
  const loginUrl = getLoginUrl()
  let html = '[error] Session expired. Your text is kept in this page and in the temporary save.'
  if (reason) { html += ' (' + escapeHtmlText(reason) + ')' }
  if (loginUrl) {
    html += ' <a href="' + escapeHtmlText(loginUrl) + '" target="_blank" rel="noopener">Login again</a>, then press "Save temp" here.'
  }
  qq('#edit_info').html(html)
  qq('#edit_info').css('color', 'red')
  setButtonsDisabled(false)
}

function getLoginUrl() {
  const el = qs('#login_url')
  return el ? el.value : ''
}

// Ajax procedure for save
function ajaxProc(a_mode, source) {
  if (isProcessingAjax) {
    elog("- skip go_ajax:" + a_mode)
    return
  }
  // タグを同期
  syncTagsToHidden()
  
  qq('#temporarily_save_btn').prop('disabled', true)

  isProcessingAjax = true
  const text = qq('#edit_txt').val()
  const actionUrl = qq('#wikiedit form').attr('action')
  const pid = postId++
  const params = new FormData()
  params.append('i_mode', 'ajax')
  params.append('a_mode', a_mode)
  params.append('a_hash', qq('#a_hash').val())
  params.append('edit_txt', text)
  params.append('edit_ext', qq('#edit_ext').val())
  params.append('edit_token', qq('#edit_token').val())
  params.append('tags', qq('#tags').val())
  const page_mode_sel = qs('#page_mode')
  if (page_mode_sel) {
    params.append('page_mode', page_mode_sel.value)
  }
  params.append('postId', pid)
  // save a local backup too, so the text survives even if this save fails
  saveTextToLS()
  // post
  elog(`@@ajaxProc::${pid}::${a_mode}::${source}`)
  qq().post(actionUrl, params)
    .done(msg => {
      elog(`@@done: ${pid}`)
      // check result
      const result = msg["result"]
      if (result != 'ok') {
        console.error(`ajaxProc::error::${pid}`, msg)
        // 失敗したら Ajax 待ち合わせを解除する。これをしないと
        // 以後の自動保存が全て止まってしまう。
        isProcessingAjax = false
        const code = msg['code']
        const reason = msg['reason'] || ''
        if (code == 'nologin' || code == 'invalid_token' || reason.indexOf('Invalid edit token') >= 0) {
          // セッション切れ (issue #64)。本文を退避し、再ログイン後に
          // 保存し直せるようにする。復旧は keepalive が担う。
          handleSessionLost(reason)
          return
        }
        if (code == 'conflict') {
          // 編集の競合 (issue #153)。ドロワーの競合パネルで解決する。
          handleEditConflict(msg, source)
          return
        }
        qq('#edit_info').html("[error] " + reason)
        qq('#edit_info').css("color", "red")
        setButtonsDisabled(false)
        return
      }
      // ok
      isSessionLost = false
      sessionRetryCount = 0
      handleChange(false, `ajaxProc::done::${pid}`)
      isProcessingAjax = false
      // count
      countText()
      // set hash
      qq('#a_hash').val(msg["a_hash"])
      clearConflictUI()
      if (msg["edit_ext"]) {
        qq('#edit_ext').val(msg["edit_ext"])
      }
      qq('#edit_info').html('[saved]')
      use_beforeunload(false)
      // effect - flash info field
      const info = qq('#edit_info')
      // const oldColor = info.css('backgroundColor');
      info.css('backgroundColor', '#ffffc0')
      info.css('color', 'green')
      setTimeout(function () {
        // reset to the stylesheet values (an inline white would break
        // the dark theme, agy review on PR #341)
        info.css('backgroundColor', '')
        info.css('color', '')
      }, 700)
    })
    .fail(function (error) {
      console.error('[ajax::fail]', pid, error)
      isProcessingAjax = false
      qq('#edit_info').html("Sorry request failed." + error)
      setButtonsDisabled(false)
    })
}

function setButtonsDisabled(stat) {
  qq('#git_save_btn').prop('disabled', stat)
  qq('#temporarily_save_btn').prop('disabled', stat)
}

function countText() {
  let s = ''
  let txt = qq('#edit_txt').val()
  const counterTag = [['{{{#count', '}}}'], ['```#count', '```'], [':::count', ':::'], [':::#count', ':::'], [':::summary', ':::'], ['{{{#summary', '}}}']]
  // total
  s += 'total(' + txt.length.toLocaleString() + ') '
  // id
  while (txt) {
    let i = -1
    let closeTag = ''
    for (let tagNo = 0; tagNo < counterTag.length; tagNo++) {
      const ctag = counterTag[tagNo]
      const openTag = ctag[0]
      const closeTag2 = ctag[1]
      i = txt.indexOf(openTag)
      if (i >= 0) {
        closeTag = closeTag2
        break
      }
    }
    if (i === -1) { break }
    // trim left side
    txt = txt.substr(i)
    txt = txt.replace(/^[\{`:]+\#?(countbox|count|summary)/, '')
    // count
    var id = '*'
    var ts = ''
    var ti = txt.indexOf('(id=')
    var ei = 0
    if (ti == 0) { // with id
      txt = txt.substr(ti)
      var m = txt.match(/id=(.+)\)/)
      if (m) id = m[1]
      ei = txt.indexOf(closeTag)
      ts = txt.substr(0, ei)
      ts = ts.split("\n").slice(1).join("")
      txt = txt.substr(ei + closeTag.length)
    } else {
      ei = txt.indexOf(closeTag)
      ts = txt.substr(0, ei)
      ts = ts.split("\n").join("")
    }
    s += id + '(' + ts.length + ') '
  }
  qq('#edit_counter').html(s)
}

function edit_recover() {
  var r = confirm('Really recover text?')
  if (!r) return false
  var edit_txt = qs('#edit_txt')
  edit_txt.value = localStorage[STORAGE_KEY]
}

function text2html(s) {
  s = s.replace(/\&/g, '&amp;')
  s = s.replace(/\</g, '&lt;')
  s = s.replace(/\>/g, '&gt;')
  return s
}

function jump(url, windowName) {
  if (windowName !== undefined) {
    window.open(url, windowName)
    return
  }
  window.location.href = url
}

function toggleDisplay(query) {
  const e = qs(query)
  if (e.style.display == 'none') {
    e.style.display = 'block'
  } else {
    e.style.display = 'none'
  }
}

function showDisplay(query) {
  const e = qs(query)
  if (e.style.display == 'none') {
    e.style.display = 'block'
  }
}

// edit drawer (issue #153): all tool panels live in one drawer and
// only one panel is open at a time.
function toggleEditDrawer() {
  const drawer = qs('#edit_drawer')
  if (drawer && drawer.classList.contains('open')) {
    closeEditDrawer()
  } else {
    openEditDrawer()
  }
}

function openEditDrawer() {
  qq('#edit_drawer').addClass('open')
  qq('#edit_drawer_scrim').addClass('open')
}

function closeEditDrawer() {
  qq('#edit_drawer').removeClass('open')
  qq('#edit_drawer_scrim').removeClass('open')
}

// Live preview (issue #153): 'off' | 'split' (side by side) | 'full'
// (preview replaces the editor on narrow screens)
let previewMode = 'off'
let previewTimer = null
let previewSeq = 0

function togglePreview() {
  closeEditDrawer()
  if (previewMode !== 'off') {
    setPreviewMode('off')
    return
  }
  setPreviewMode(window.innerWidth < 800 ? 'full' : 'split')
}

function setPreviewMode(mode) {
  previewMode = mode
  const main = qs('#edit_main')
  const pane = qs('#edit_preview')
  if (!main || !pane) {
    return
  }
  main.classList.remove('preview-split')
  main.classList.remove('preview-full')
  if (mode === 'off') {
    pane.style.display = 'none'
    // drop any pending/in-flight preview update
    previewSeq++
    if (previewTimer) {
      clearTimeout(previewTimer)
      previewTimer = null
    }
    return
  }
  pane.style.display = ''
  main.classList.add(mode === 'full' ? 'preview-full' : 'preview-split')
  refreshPreview()
}

function refreshPreview() {
  if (previewMode === 'off') {
    return
  }
  const body = qs('#edit_preview .edit_preview_body')
  if (!body) {
    return
  }
  const seq = ++previewSeq
  const action = qq('#wikiedit form').attr('action')
  const sentText = edit_txt.value
  qq().post(action, {
    'i_mode': 'ajax',
    'q': 'preview',
    'edit_token': qq('#edit_token').val(),
    'edit_txt': sentText,
    'page_mode': qs('#page_mode') ? qs('#page_mode').value : '',
  }).done(function (obj) {
    // ignore stale responses and responses arriving after the preview
    // was turned off (review follow-up for PR #341)
    if (seq !== previewSeq || previewMode === 'off') {
      return
    }
    if (obj && obj['result'] === 'ok') {
      // Replacing the content can clamp the pane and fire scroll
      // events; guard so the editor is not pulled while typing.
      guardHeadingSync()
      body.innerHTML = obj['html']
      buildHeadingSync(body, sentText)
      return
    }
    headingPairs = []
    const msg = (obj && (obj['reason'] || obj['message'])) || 'プレビューを更新できませんでした'
    body.textContent = msg
  }).fail(function () {
    if (seq !== previewSeq || previewMode === 'off') {
      return
    }
    headingPairs = []
    body.textContent = 'プレビューを更新できませんでした'
  })
}

function schedulePreview() {
  if (previewMode === 'off') {
    return
  }
  if (previewTimer) {
    clearTimeout(previewTimer)
  }
  previewTimer = setTimeout(refreshPreview, 700)
}

// --- Heading sync between the editor and the preview (issue #153) ---
// Pairs are built by matching heading texts, and each heading's pixel
// offset in the textarea is measured with a mirror element, so wrapped
// lines are accounted for. Scrolling either side moves the other to the
// current heading (split mode only); clicking a preview heading jumps
// the editor to that line.
let headingPairs = [] // {line, level, srcIndex, el, top}
let headingSyncGuard = false
let headingGuardTimer = 0
let headingPendingDir = '' // a scroll dropped while the guard was on
let headingSyncRaf = 0
let lastCurrentPair = null
let headingMirror = null
let headingResizeTimer = 0

function findSourceHeadings(text, mode) {
  const out = []
  const lines = String(text).split('\n')
  let inFence = false
  for (let i = 0; i < lines.length; i++) {
    const raw = lines[i]
    if (mode === 'Markdown') {
      if (/^\s*(```|~~~)/.test(raw)) { inFence = !inFence; continue }
      if (inFence) { continue }
      const m = raw.match(/^(#{1,6})\s+(.+?)\s*$/)
      if (m) {
        out.push({ line: i, level: m[1].length, text: m[2].replace(/\s+#+\s*$/, '') })
      }
    } else {
      // KonaNotation (parser: line parser). A run of '*' at the line
      // start is a heading; the symbol headings are single glyphs.
      const stars = raw.match(/^(\*+)(?=\s|[^*]|$)/)
      if (stars && stars[1].length <= 6 && /^\*+\s*\S/.test(raw)) {
        const t = raw.replace(/^\*+\s*/, '')
        if (t) { out.push({ line: i, level: stars[1].length, text: t }) }
        continue
      }
      const sym = raw.match(/^([■□●○▲△▼▽])\s*(.+)$/)
      if (sym) {
        const level = ('■□'.indexOf(sym[1]) >= 0) ? 1 :
          (('●○'.indexOf(sym[1]) >= 0) ? 2 :
            (('▲△'.indexOf(sym[1]) >= 0) ? 3 : 4))
        out.push({ line: i, level: level, text: sym[2] })
      }
    }
  }
  return out
}

function headingTextKey(s) {
  s = String(s).trim()
  // Links keep only their label: [text](url), [[text>url]], [[text|url]]
  s = s.replace(/\[([^\]]+)\]\([^)]*\)/g, '$1')
  s = s.replace(/\[\[([^\]>|]+)[^\]]*\]\]/g, '$1')
  s = s.replace(/\[\[|\]\]/g, '')
  // Inline emphasis markers
  s = s.replace(/\*\*([^*]+)\*\*/g, '$1')
  s = s.replace(/__([^_]+)__/g, '$1')
  s = s.replace(/\*([^*]+)\*/g, '$1')
  s = s.replace(/`([^`]+)`/g, '$1')
  s = s.replace(/''/g, '')
  s = s.replace(/\s+/g, ' ').trim()
  // The rendered heading ends with the anchor '*' (KonaNotation)
  s = s.replace(/\*$/, '').trim()
  return s
}

function headingTextsMatch(a, b) {
  const ka = headingTextKey(a)
  const kb = headingTextKey(b)
  if (!ka || !kb) { return false }
  return ka === kb || ka.indexOf(kb) === 0 || kb.indexOf(ka) === 0
}

function pairHeadings(sourceHeadings, previewHeadings) {
  // In-order matching. An exact key match later in the source wins over
  // a prefix match earlier, so a source-only heading (e.g. produced by a
  // plugin from a shorter title) cannot steal a pair.
  const pairs = []
  const used = {}
  let si = 0
  for (const ph of previewHeadings) {
    let found = -1
    for (let j = si; j < sourceHeadings.length; j++) {
      if (!used[j] &&
        headingTextKey(sourceHeadings[j].text) === headingTextKey(ph.text)) {
        found = j; break
      }
    }
    if (found < 0) {
      for (let j = si; j < sourceHeadings.length; j++) {
        if (!used[j] && headingTextsMatch(sourceHeadings[j].text, ph.text)) {
          found = j; break
        }
      }
    }
    if (found >= 0) {
      used[found] = true
      si = found + 1
      pairs.push({
        line: sourceHeadings[found].line,
        level: sourceHeadings[found].level,
        srcIndex: found,
        el: ph.el,
      })
    }
  }
  return pairs
}

function escapeHeadingHtml(s) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

// Measure the pixel offset of every source heading inside the textarea
// with a mirror element that copies its layout (font, padding, width
// and soft wrapping). Returns an array indexed like srcHeadings, or
// null when measurement is not possible.
function measureHeadingTops(ta, text, srcHeadings) {
  try {
    if (!headingMirror) {
      headingMirror = document.createElement('div')
      headingMirror.setAttribute('aria-hidden', 'true')
    }
    const m = headingMirror
    const cs = getComputedStyle(ta)
    const st = m.style
    st.position = 'absolute'
    st.visibility = 'hidden'
    st.top = '0'
    st.left = '-9999px'
    st.whiteSpace = 'pre-wrap'
    st.overflowWrap = 'break-word'
    st.fontFamily = cs.fontFamily
    st.fontSize = cs.fontSize
    st.fontWeight = cs.fontWeight
    st.fontStyle = cs.fontStyle
    st.lineHeight = cs.lineHeight
    st.letterSpacing = cs.letterSpacing
    if (cs.fontKerning) { st.fontKerning = cs.fontKerning }
    if (cs.fontVariantLigatures) { st.fontVariantLigatures = cs.fontVariantLigatures }
    st.padding = cs.padding
    st.borderWidth = cs.borderWidth
    st.borderStyle = 'solid'
    st.borderColor = 'transparent'
    st.boxSizing = 'border-box'
    st.width = ta.clientWidth + 'px'
    const byLine = {}
    srcHeadings.forEach(function (h, idx) { byLine[h.line] = idx })
    const lines = String(text).split('\n')
    let html = ''
    for (let i = 0; i < lines.length; i++) {
      if (byLine[i] !== undefined) {
        html += '<span data-h="' + byLine[i] + '"></span>'
      }
      html += escapeHeadingHtml(lines[i]) + '\n'
    }
    m.innerHTML = html
    if (!m.parentNode) { document.body.appendChild(m) }
    const tops = []
    const spans = m.querySelectorAll('span[data-h]')
    for (let i = 0; i < spans.length; i++) {
      tops[Number(spans[i].getAttribute('data-h'))] = spans[i].offsetTop
    }
    for (let i = 0; i < srcHeadings.length; i++) {
      if (typeof tops[i] !== 'number') { return null }
    }
    return tops
  } catch (e) {
    return null
  }
}

function buildHeadingSync(body, text) {
  headingPairs = []
  lastCurrentPair = null
  if (!body) { return }
  const ta = qs('#edit_txt')
  if (!ta) { return }
  const els = body.querySelectorAll('h1, h2, h3, h4, h5, h6')
  if (!els.length) { return }
  const modeEl = qs('#page_mode')
  const mode = modeEl ? modeEl.value : ''
  const srcText = (typeof text === 'string') ? text : ta.value
  const src = findSourceHeadings(srcText, mode)
  if (!src.length) { return }
  const phs = []
  els.forEach(function (el) { phs.push({ text: el.textContent, el: el }) })
  headingPairs = pairHeadings(src, phs)
  const tops = measureHeadingTops(ta, srcText, src)
  const cs = getComputedStyle(ta)
  const lh = parseFloat(cs.lineHeight)
  const lineHeight = isNaN(lh) ? 27.2 : lh
  const padTop = parseFloat(cs.paddingTop) || 0
  headingPairs.forEach(function (p) {
    p.top = (tops && typeof tops[p.srcIndex] === 'number')
      ? tops[p.srcIndex]
      : p.line * lineHeight + padTop
    p.el.classList.add('sync-linked')
  })
}

function guardHeadingSync() {
  // While the guard is on, incoming scroll events are remembered (not
  // lost): the latest direction is replayed once when it clears, so a
  // continuous scroll still settles both sides at the final position.
  headingSyncGuard = true
  if (headingGuardTimer) { clearTimeout(headingGuardTimer) }
  headingGuardTimer = setTimeout(function () {
    headingSyncGuard = false
    headingGuardTimer = 0
    if (headingPendingDir) {
      const dir = headingPendingDir
      headingPendingDir = ''
      if (dir === 'editor') { syncPreviewFromEditor() } else { syncEditorFromPreview() }
    }
  }, 120)
}

function onHeadingSyncScroll(which) {
  if (previewMode !== 'split') { return }
  if (headingSyncGuard) { headingPendingDir = which; return }
  if (headingSyncRaf) { return }
  headingSyncRaf = requestAnimationFrame(function () {
    headingSyncRaf = 0
    if (which === 'editor') { syncPreviewFromEditor() } else { syncEditorFromPreview() }
  })
}

function setCurrentHeading(pair) {
  if (lastCurrentPair === pair) { return }
  if (lastCurrentPair) { lastCurrentPair.el.classList.remove('sync-current') }
  lastCurrentPair = pair
  if (pair) { pair.el.classList.add('sync-current') }
}

function syncPreviewFromEditor() {
  if (previewMode !== 'split' || !headingPairs.length) { return }
  if (headingSyncGuard) { headingPendingDir = 'editor'; return }
  const ta = qs('#edit_txt')
  const pane = qs('#edit_preview')
  if (!ta || !pane) { return }
  let current = null
  if (ta.scrollTop + ta.clientHeight >= ta.scrollHeight - 2) {
    current = headingPairs[headingPairs.length - 1]
  } else {
    for (const p of headingPairs) {
      if (p.top <= ta.scrollTop + 12) { current = p } else { break }
    }
  }
  if (!current) {
    // Above the first heading: bring the preview back to the very top.
    if (pane.scrollTop !== 0) {
      guardHeadingSync()
      pane.scrollTop = 0
    }
    setCurrentHeading(null)
    return
  }
  const paneRect = pane.getBoundingClientRect()
  const elRect = current.el.getBoundingClientRect()
  const target = pane.scrollTop + (elRect.top - paneRect.top) - 8
  if (Math.abs(target - pane.scrollTop) > 2) {
    guardHeadingSync()
    pane.scrollTop = target
  }
  setCurrentHeading(current)
}

function syncEditorFromPreview() {
  if (previewMode !== 'split' || !headingPairs.length) { return }
  if (headingSyncGuard) { headingPendingDir = 'preview'; return }
  const ta = qs('#edit_txt')
  const pane = qs('#edit_preview')
  if (!ta || !pane) { return }
  let current = null
  if (pane.scrollTop + pane.clientHeight >= pane.scrollHeight - 2) {
    current = headingPairs[headingPairs.length - 1]
  } else {
    const paneRect = pane.getBoundingClientRect()
    for (const p of headingPairs) {
      const rel = p.el.getBoundingClientRect().top - paneRect.top
      if (rel <= 12) { current = p } else { break }
    }
  }
  if (!current) {
    if (ta.scrollTop !== 0) {
      guardHeadingSync()
      ta.scrollTop = 0
    }
    setCurrentHeading(null)
    return
  }
  const maxTop = Math.max(0, ta.scrollHeight - ta.clientHeight)
  const target = Math.min(Math.max(0, current.top - 8), maxTop)
  if (Math.abs(target - ta.scrollTop) > 2) {
    guardHeadingSync()
    ta.scrollTop = target
  }
  setCurrentHeading(current)
}

function jumpToHeading(pair) {
  if (previewMode === 'full') { setPreviewMode('split') }
  if (previewMode !== 'split') { return }
  const ta = qs('#edit_txt')
  if (!ta) { return }
  const lines = ta.value.split('\n')
  let pos = 0
  for (let i = 0; i < pair.line && i < lines.length; i++) {
    pos += lines[i].length + 1
  }
  try {
    ta.focus({ preventScroll: true })
  } catch (e) {
    ta.focus()
  }
  ta.setSelectionRange(pos, pos)
  guardHeadingSync()
  ta.scrollTop = Math.max(0, pair.top - 8)
  setCurrentHeading(pair)
  // Align the preview after the editor settles (the scroll handler's
  // pending replay does this once the guard clears).
  headingPendingDir = 'editor'
}

function initHeadingSync() {
  const ta = qs('#edit_txt')
  const pane = qs('#edit_preview')
  const body = qs('#edit_preview .edit_preview_body')
  if (!ta || !pane || !body) { return }
  ta.addEventListener('scroll', function () { onHeadingSyncScroll('editor') })
  pane.addEventListener('scroll', function () { onHeadingSyncScroll('preview') })
  body.addEventListener('click', function (e) {
    const sel = window.getSelection ? String(window.getSelection()) : ''
    if (sel) { return } // text selection in progress: do not jump
    const h = e.target.closest ? e.target.closest('h1, h2, h3, h4, h5, h6') : null
    if (!h) { return }
    const pair = headingPairs.find(function (p) { return p.el === h })
    if (!pair) { return }
    // A link inside the heading (the KonaNotation anchor '*', or an
    // inline link) must not navigate away from the editor.
    if (e.target.closest && e.target.closest('a')) { e.preventDefault() }
    jumpToHeading(pair)
  })
  window.addEventListener('resize', function () {
    // The wrap points change with the width: re-measure the offsets.
    if (headingResizeTimer) { clearTimeout(headingResizeTimer) }
    headingResizeTimer = setTimeout(function () {
      headingResizeTimer = 0
      const b = qs('#edit_preview .edit_preview_body')
      if (b && headingPairs.length) { buildHeadingSync(b) }
    }, 200)
  })
}

// Show only the given panel in the drawer. If it is already visible,
// hide it instead (toggle), unless forceShow is set. Returns the shown
// panel or null.
function showOnlyPanel(selector, forceShow) {
  const target = qs(selector)
  if (!target) {
    return null
  }
  const isVisible = target.style.display !== 'none'
  const panels = ['#help_div', '#tags_div', '#replace_div', '#history_div', '#misc_div', '#ai_div', '#search_div', '#conflict_div', '#settings_div']
  panels.forEach(function (panelSelector) {
    if (qs(panelSelector)) {
      qq(panelSelector).hide()
    }
  })
  if (isVisible && !forceShow) {
    return null
  }
  qq(selector).show()
  return target
}

// Open the drawer and show the given tool panel. When the drawer was
// closed, the panel is always shown: a panel left visible inside the
// closed drawer must not be toggled off (Copilot review on PR #341).
// In-editor text search (issue #153)
let searchIndex = -1
let searchQuery = ''
let searchText = null

// All match offsets of query in text (case-insensitive, non-overlapping).
function computeSearchMatches(text, query) {
  const matches = []
  if (!query) {
    return matches
  }
  let hay = text.toLowerCase()
  let needle = query.toLowerCase()
  // toLowerCase() can change the string length for a few characters
  // (e.g. 'İ'), which would shift every offset. Fall back to a
  // case-sensitive search in that case (review follow-up for PR #345).
  if (hay.length !== text.length || needle.length !== query.length) {
    hay = text
    needle = query
  }
  let pos = 0
  while (true) {
    const i = hay.indexOf(needle, pos)
    if (i < 0) {
      break
    }
    matches.push(i)
    pos = i + Math.max(1, needle.length)
  }
  return matches
}

// Reset the remembered position when the query or the text changed, so
// navigation always starts from the current caret/selection.
function searchSyncState(query) {
  if (query !== searchQuery || edit_txt.value !== searchText) {
    searchQuery = query
    searchText = edit_txt.value
    searchIndex = -1
  }
}

// Index of the match to jump to from currentPos: the first match at or
// after currentPos for dir=1, the last match before currentPos for
// dir=-1, wrapping around. -1 when there are no matches.
function findSearchTarget(matches, currentPos, dir) {
  if (matches.length === 0) {
    return -1
  }
  if (dir > 0) {
    for (let i = 0; i < matches.length; i++) {
      if (matches[i] >= currentPos) {
        return i
      }
    }
    return 0
  }
  for (let i = matches.length - 1; i >= 0; i--) {
    if (matches[i] < currentPos) {
      return i
    }
  }
  return matches.length - 1
}

function updateSearchCount() {
  const input = qs('#search_key')
  const countEl = qs('#search_count')
  if (!input || !countEl) {
    return
  }
  const query = input.value
  searchSyncState(query)
  const matches = computeSearchMatches(edit_txt.value, query)
  if (!query) {
    countEl.textContent = ''
  } else if (matches.length === 0) {
    countEl.textContent = '見つかりません'
  } else if (searchIndex >= 0) {
    countEl.textContent = (searchIndex + 1) + ' / ' + matches.length
  } else {
    countEl.textContent = matches.length + ' 件'
  }
}

function searchMove(dir) {
  const input = qs('#search_key')
  const query = input ? input.value : ''
  const matches = computeSearchMatches(edit_txt.value, query)
  searchSyncState(query)
  if (searchIndex >= matches.length) {
    searchIndex = matches.length - 1
  }
  // Base position: continue from the last match only while the current
  // selection still is that match; otherwise start from the selection
  // the user actually has (review follow-up for PR #345).
  const selStart = edit_txt.selectionStart || 0
  const selEnd = edit_txt.selectionEnd || 0
  const onMatch = searchIndex >= 0 && searchIndex < matches.length &&
    matches[searchIndex] === selStart && selEnd === selStart + query.length
  const from = dir > 0
    ? (onMatch ? matches[searchIndex] + 1 : selEnd)
    : (onMatch ? matches[searchIndex] : selStart)
  const idx = findSearchTarget(matches, from, dir)
  if (idx < 0) {
    updateSearchCount()
    return
  }
  searchIndex = idx
  const start = matches[idx]
  edit_txt.setSelectionRange(start, start + query.length)
  scrollEditorToPos(start)
  updateSearchCount()
  // Keep the focus on the search input: moving it to the textarea would
  // make the next Enter insert a newline over the matched text, and
  // would break repeated Enter navigation (review follow-up, PR #345).
  if (input) {
    input.focus()
  }
}

function searchNext() {
  searchMove(1)
}

function searchPrev() {
  searchMove(-1)
}

// Scroll the textarea so that the given offset is roughly in view.
function scrollEditorToPos(pos) {
  const before = edit_txt.value.substring(0, pos)
  const lines = before.split('\n').length - 1
  const style = window.getComputedStyle(edit_txt)
  const lineHeight = parseInt(style.lineHeight, 10) || 20
  edit_txt.scrollTop = Math.max(0, lines * lineHeight - edit_txt.clientHeight / 3)
}

function searchOnClick(forceShow) {
  if (forceShow) {
    // The Ctrl+F shortcut must always open the panel, never toggle it
    // shut (review follow-up for PR #345).
    openEditDrawer()
    showOnlyPanel('#search_div', true)
  } else {
    openToolPanel('#search_div')
  }
  const input = qs('#search_key')
  if (input) {
    input.focus()
    input.select()
  }
}

// Edit conflict handling (issue #153, phase 4). The server answers a
// conflicting save with code 'conflict' plus the server-side text and
// its hash; the user resolves it in the drawer panel. The editor text
// is never rewritten behind the user's back.
let conflictState = null

function handleEditConflict(msg, source) {
  // On a repeated conflict, keep the backup of the user's own text
  // taken during the previous round, and re-sync the panel UI with
  // the fresh state (PR #349 review follow-up).
  const prevBackup = conflictState ? conflictState.myBackup : null
  conflictState = {
    serverText: (msg && msg['server_text']) || '',
    serverHash: (msg && msg['server_hash']) || '',
    myBackup: prevBackup
  }
  const nav = qs('#conflict_nav')
  if (nav) { nav.style.display = '' }
  const restoreBtn = qs('#conflict_restore_btn')
  if (restoreBtn) { restoreBtn.style.display = prevBackup !== null ? '' : 'none' }
  setConflictNote('')
  refreshConflictDiffIfOpen()
  qq('#edit_info').html('⚠ 競合が発生しました')
  qq('#edit_info').css('color', 'red')
  setButtonsDisabled(false)
  // Background saves (autosave timer, keepalive restore) must not yank
  // the drawer open while the user is typing; the warning button in
  // the drawer nav opens the panel instead.
  if (source !== 'timerAutoSaveOnTime' && source !== 'keepalive-restored') {
    openEditDrawer()
    showOnlyPanel('#conflict_div', true)
  }
}

function clearConflictUI() {
  if (!conflictState) { return }
  conflictState = null
  const nav = qs('#conflict_nav')
  if (nav) { nav.style.display = 'none' }
  const restoreBtn = qs('#conflict_restore_btn')
  if (restoreBtn) { restoreBtn.style.display = 'none' }
  const box = qs('#conflict_diff_box')
  if (box) { box.style.display = 'none' }
  // Do not leave a stale, inert conflict panel open in the drawer
  // after the conflict was resolved by a successful save.
  const panel = qs('#conflict_div')
  if (panel) { panel.style.display = 'none' }
  setConflictNote('')
}

function conflictOnClick() {
  openToolPanel('#conflict_div')
}

function setConflictNote(text) {
  const note = qs('#conflict_note')
  if (note) { note.textContent = text }
}

// Line-based diff (LCS) between two texts. Returns rows of
// {type: 'same'|'mine'|'server', text}, or null when the texts are
// too large for the DP table.
function computeLineDiff(myText, serverText) {
  // The server text is the raw file content and may use CRLF, while
  // the textarea value is normalized to LF; compare on LF so a CRLF
  // page does not diff as "every line changed" (PR #349 review).
  const mine = myText.replace(/\r\n?/g, '\n').split('\n')
  const server = serverText.replace(/\r\n?/g, '\n').split('\n')
  if ((mine.length + 1) * (server.length + 1) > 1000000) {
    return null
  }
  const cols = server.length + 1
  const lcs = []
  for (let i = 0; i <= mine.length; i++) {
    lcs.push(new Uint32Array(cols))
  }
  for (let i = mine.length - 1; i >= 0; i--) {
    for (let j = server.length - 1; j >= 0; j--) {
      lcs[i][j] = mine[i] === server[j]
        ? lcs[i + 1][j + 1] + 1
        : Math.max(lcs[i + 1][j], lcs[i][j + 1])
    }
  }
  const rows = []
  let i = 0
  let j = 0
  while (i < mine.length && j < server.length) {
    if (mine[i] === server[j]) {
      rows.push({ type: 'same', text: mine[i] })
      i++
      j++
    } else if (lcs[i + 1][j] >= lcs[i][j + 1]) {
      rows.push({ type: 'mine', text: mine[i] })
      i++
    } else {
      rows.push({ type: 'server', text: server[j] })
      j++
    }
  }
  while (i < mine.length) {
    rows.push({ type: 'mine', text: mine[i] })
    i++
  }
  while (j < server.length) {
    rows.push({ type: 'server', text: server[j] })
    j++
  }
  return rows
}

function toggleConflictDiff() {
  const box = qs('#conflict_diff_box')
  if (!box) { return }
  if (box.style.display !== 'none') {
    box.style.display = 'none'
    return
  }
  if (!conflictState) { return }
  renderConflictDiff()
  box.style.display = ''
}

// Re-render the diff while it is open, so it never shows a stale
// comparison after the text or the server version changed.
function refreshConflictDiffIfOpen() {
  const box = qs('#conflict_diff_box')
  if (!box || box.style.display === 'none' || !conflictState) { return }
  renderConflictDiff()
}

function renderConflictDiff() {
  const wrap = qs('#conflict_diff')
  if (!wrap || !conflictState) { return }
  wrap.textContent = ''
  const rows = computeLineDiff(edit_txt.value, conflictState.serverText)
  if (rows === null) {
    // Too large to diff line by line: show both versions in full.
    const mineHead = document.createElement('b')
    mineHead.textContent = 'あなたの内容'
    wrap.appendChild(mineHead)
    const minePre = document.createElement('pre')
    minePre.className = 'conflict_full_text'
    minePre.textContent = edit_txt.value
    wrap.appendChild(minePre)
    const serverHead = document.createElement('b')
    serverHead.textContent = 'サーバー側の内容'
    wrap.appendChild(serverHead)
    const serverPre = document.createElement('pre')
    serverPre.className = 'conflict_full_text'
    serverPre.textContent = conflictState.serverText
    wrap.appendChild(serverPre)
  } else {
    let shown = 0
    for (const row of rows) {
      if (row.type === 'same') { continue }
      const el = document.createElement('div')
      el.className = 'conflict_line conflict_line_' + row.type
      el.textContent = row.text === '' ? ' ' : row.text
      wrap.appendChild(el)
      shown++
    }
    if (shown === 0) {
      const el = document.createElement('div')
      el.className = 'info'
      el.textContent = '行単位の差分はありません（末尾の改行などの差異のみです）'
      wrap.appendChild(el)
    }
  }
}

function conflictLoadServer() {
  if (!conflictState) { return }
  // Back up the user's text only once: a second click would otherwise
  // back up the server text itself and lose the original for good
  // (PR #349 review follow-up, found by both reviewers).
  if (conflictState.myBackup === null) {
    conflictState.myBackup = edit_txt.value
  }
  edit_txt.value = conflictState.serverText
  qq('#a_hash').val(conflictState.serverHash)
  handleChange(false, 'conflictLoadServer')
  countText()
  const restoreBtn = qs('#conflict_restore_btn')
  if (restoreBtn) { restoreBtn.style.display = '' }
  setConflictNote('サーバーの内容を読み込みました。あなたの内容は「自分の内容に戻す」で復元できます。')
  refreshConflictDiffIfOpen()
}

function conflictRestoreMine() {
  if (!conflictState || conflictState.myBackup === null) { return }
  edit_txt.value = conflictState.myBackup
  conflictState.myBackup = null
  handleChange(true, 'conflictRestoreMine')
  countText()
  const restoreBtn = qs('#conflict_restore_btn')
  if (restoreBtn) { restoreBtn.style.display = 'none' }
  setConflictNote('あなたの内容に戻しました。保存するとサーバー側の内容を上書きします。')
  refreshConflictDiffIfOpen()
}

function conflictOverwrite() {
  if (!conflictState) { return }
  if (edit_txt.value === conflictState.serverText) {
    // The editor already holds the server text (e.g. after loading
    // it): there is nothing to overwrite, the conflict is resolved.
    clearConflictUI()
    closeEditDrawer()
    return
  }
  qq('#a_hash').val(conflictState.serverHash)
  closeEditDrawer()
  ajaxSave('conflict-overwrite')
}

function openToolPanel(selector) {
  const drawer = qs('#edit_drawer')
  const wasOpen = drawer && drawer.classList.contains('open')
  openEditDrawer()
  showOnlyPanel(selector, !wasOpen)
}

function historyOnClick() {
  openToolPanel('#history_div')
}

function helpOnClick() {
  openToolPanel('#help_div')
}

function tagsOnClick() {
  openToolPanel('#tags_div')
}

function aiOnClick() {
  openToolPanel('#ai_div')
}

function aiSpellCheckOnClick() {
  if (!qs('#ai_actions')) { return }
  // aiPost() force-shows the AI panel; opening it here first with the
  // toggling openToolPanel() would hide it again on a second press.
  aiRunAction('proofread')
}

var loaderTimerId = 0
var loaderCount = 0
var aiBusy = false
function aiButtonEnabeld(enbaled) {
  aiBusy = !enbaled
  // buttons (prevent double execution)
  document.querySelectorAll('#ai_actions button, #ai_user_actions button').forEach(function (b) {
    b.disabled = !enbaled
  })
  // loader
  if (!enbaled) {
    qq('#ai_loader').show()
    if (loaderTimerId > 0) {
      clearInterval(loaderTimerId)
    }
    loaderCount = 0
    loaderTimerId = setInterval(() => {
      loaderCount++
      const tmp = '=========='
      let s = tmp.substring(0, loaderCount % 10) + 'abcdeABCDE'
      s = s.substring(0, 10)
      qq('#ai_loader').text(s)
    }, 100)
  } else {
    qq('#ai_loader').hide()
    if (loaderTimerId > 0) {
      clearInterval(loaderTimerId)
    }
  }
}

// ---------------------------------------------------------------
// AI panel (issue #364)
// The prompts are built on the server; the client only sends the
// action id and the text. DOM-independent helpers (aiApplyReplacements,
// aiReplaceAt, ...) live in edit_ai_core.js.
// ---------------------------------------------------------------
const LS_KEY_AI_REPHRASE = 'kona3_ai_rephrase_instruction'
let aiUndoBuffer = null

function aiInfo(html) {
  qq('#edit_info').html(html)
}

function aiLangN(id, n) {
  return lang(id).replace('{n}', '' + n)
}

function aiInitPanel() {
  const clearBtn = qs('#ai_output_clear_btn')
  if (clearBtn) {
    qq(clearBtn).click(() => {
      qq('#ai_output').html('')
    })
  }
  const undoBtn = qs('#ai_undo_btn')
  if (undoBtn) {
    undoBtn.addEventListener('click', aiUndo)
  }
  const instruction = qs('#ai_rephrase_instruction')
  if (instruction) {
    try {
      instruction.value = localStorage.getItem(LS_KEY_AI_REPHRASE) || ''
    } catch (e) { /* storage unavailable */ }
    instruction.addEventListener('change', () => {
      try {
        localStorage.setItem(LS_KEY_AI_REPHRASE, instruction.value)
      } catch (e) { /* storage unavailable */ }
    })
  }
}

// Run a standard AI action. opts.direct (append only): insert the result
// at the cursor without showing it in the result area (Ctrl+Shift+H).
function aiRunAction(action, opts) {
  opts = opts || {}
  const edit_txt = qs('#edit_txt')
  if (!edit_txt || !qs('#ai_actions') || aiBusy) { return }
  const body = edit_txt.value
  const selStart = edit_txt.selectionStart
  const selEnd = edit_txt.selectionEnd
  const selText = body.substring(selStart, selEnd)
  const params = { 'ai_action': action }
  if (action === 'rephrase') {
    if (selText === '') {
      aiInfo(lang('locale__select_range'))
      return
    }
    params['ai_text'] = selText
    const instruction = qs('#ai_rephrase_instruction')
    params['ai_instruction'] = instruction ? instruction.value : ''
  } else if (action === 'append') {
    params['ai_before'] = body.substring(0, selEnd).slice(-4000)
  } else {
    params['ai_text'] = body
  }
  const btn = document.querySelector('#ai_actions button[data-ai-action="' + action + '"]')
  const ctx = {
    action: action,
    label: btn ? btn.textContent : action,
    selStart: selStart,
    selEnd: selEnd,
    selText: selText,
    direct: !!opts.direct
  }
  aiPost(params, ctx)
}

// Run a user defined template (data/ai_prompt.md) by name.
function aiRunUserPrompt(name) {
  const edit_txt = qs('#edit_txt')
  if (!edit_txt || !qs('#ai_actions') || aiBusy) { return }
  const body = edit_txt.value
  const selStart = edit_txt.selectionStart
  const selEnd = edit_txt.selectionEnd
  const selText = body.substring(selStart, selEnd)
  aiPost({
    'ai_action': 'user',
    'ai_user_prompt': name,
    'ai_text': body,
    'ai_selected': selText
  }, {
    action: 'user',
    label: name,
    selStart: selStart,
    selEnd: selEnd,
    selText: selText,
    direct: false
  })
}

function aiPost(params, ctx) {
  const ai_model_el = qs('#ai_model')
  const form = qs('#wikiedit form')
  if (!form) { return }
  const post = {
    'i_mode': 'ajax',
    'edit_token': qq('#edit_token').val(),
    'q': 'ai',
    'a_mode': 'run',
    'a_hash': qq('#a_hash').val(),
    'ai_model': ai_model_el ? ai_model_el.value : ''
  }
  Object.keys(params).forEach(function (k) { post[k] = params[k] })
  // Make the result area visible even when the panel is not open yet.
  // Force-show the panel: openToolPanel() would toggle it off when it
  // is already visible, hiding the result the user is waiting for.
  if (!ctx.direct) {
    openEditDrawer()
    showOnlyPanel('#ai_div', true)
  }
  aiButtonEnabeld(false)
  qq().post(qq(form).attr('action'), post)
    .done(function (obj) {
      aiButtonEnabeld(true)
      if (typeof obj !== 'object' || obj === null) {
        aiRenderResult({ result: 'ng', message: 'Error: Invalid response from server.' }, ctx)
        return
      }
      const token = (obj['token'] !== undefined) ? obj['token'] : 0
      qq('#ai_ask_cost').html(token + 'token')
      if (ctx.direct && obj['result'] === 'ok' && obj['type'] === 'text') {
        aiInsertDirect(obj['text'], ctx)
        return
      }
      aiRenderResult(obj, ctx)
    })
    .fail(function (error) {
      console.error(error)
      aiInfo("Sorry AI request failed." + error)
      aiButtonEnabeld(true)
    })
}

// Insert generated text right after the captured selection / cursor.
function aiInsertDirect(text, ctx) {
  const edit_txt = qs('#edit_txt')
  const body = edit_txt.value
  const insertText = ('' + text).replace(/^\s+|\s+$/g, '')
  let pos = aiLocateRange(body, ctx.selStart, ctx.selEnd, ctx.selText)
  if (pos < 0) {
    pos = edit_txt.selectionEnd
  } else {
    pos += ctx.selText.length
  }
  const newText = body.substring(0, pos) + insertText + body.substring(pos)
  const caret = pos + insertText.length
  aiSetEditorText(newText, 'ai_append', caret, caret)
  ctx.selStart = ctx.selEnd = caret
  ctx.selText = ''
}

// The only way the AI changes the editor body. Keeps one undo step
// (assigning textarea.value drops the browser's own undo history) and
// notifies the highlight layer / preview / unsaved state.
function aiSetEditorText(newText, reason, selStart, selEnd) {
  const edit_txt = qs('#edit_txt')
  if (!edit_txt) { return }
  aiUndoBuffer = edit_txt.value
  const undoBtn = qs('#ai_undo_btn')
  if (undoBtn) { undoBtn.disabled = false }
  aiWriteEditor(newText, reason, selStart, selEnd)
}

function aiWriteEditor(newText, reason, selStart, selEnd) {
  const edit_txt = qs('#edit_txt')
  edit_txt.value = newText
  edit_txt.focus()
  if (selStart !== undefined) {
    edit_txt.setSelectionRange(selStart, selEnd === undefined ? selStart : selEnd)
  }
  const api = window.kona3EditHighlight
  if (api && api.renderNow) { api.renderNow() }
  updateSearchCount()
  handleChange(true, reason)
}

function aiUndo() {
  if (aiUndoBuffer === null) { return }
  const edit_txt = qs('#edit_txt')
  const current = edit_txt.value
  const prev = aiUndoBuffer
  aiUndoBuffer = current // allows redo by pressing again
  aiWriteEditor(prev, 'ai_undo')
}

let aiGroupId = 0
let aiBlockId = 0
const aiBlockItems = []

// Render one AI response as a result group at the top of #ai_output.
function aiRenderResult(res, ctx) {
  const out = qs('#ai_output')
  if (!out) { return }
  const gid = aiGroupId++
  const group = document.createElement('div')
  group.className = 'ai_result'
  group.id = 'aiGroup' + gid
  group.dataset.group = '' + gid
  ctx.group = gid
  const now = new Date()
  const hh = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2)
  let head = '<span class="ai_result_title">' + text2html('' + ctx.label) + '</span>' +
    '<span>' + hh + '</span>'
  let body = ''
  let applyAll = false
  if (!res || res['result'] !== 'ok') {
    body = aiTextBlock('' + (res && res['message'] ? res['message'] : 'Error'))
  } else {
    if (res['token'] !== undefined) {
      head += '<span>' + text2html(res['token'] + 'token') + '</span>'
    }
    const type = res['type']
    if (type === 'replace_list' || type === 'suggestions') {
      const items = res['items'] || []
      let n = 0
      items.forEach(function (row) {
        const html = aiItemBlock(row, type, ctx)
        if (html) {
          body += html
          n++
        }
      })
      if (n === 0) { body = aiTextBlock(type === 'replace_list' ? '---' : '') }
      applyAll = (type === 'replace_list' && n >= 2)
    } else if (type === 'candidates') {
      const items = res['items'] || []
      items.forEach(function (row) {
        body += aiCandidateBlock(row, ctx)
      })
      if (!body) { body = aiTextBlock('---') }
    } else if (type === 'quality') {
      body = aiQualityBlock(res['quality'] || {})
    } else {
      body = aiInsertBlock('' + (res['text'] !== undefined ? res['text'] : res['message']), ctx)
    }
  }
  if (applyAll) {
    head += '<button type="button" class="ai_apply_all" onclick="aiApplyAll(' + gid + ')">' +
      text2html(lang('locale__apply_all')) + '</button>'
  }
  group.innerHTML = '<div class="ai_result_head">' + head + '</div>' + body
  out.insertBefore(group, out.firstChild)
}

function aiTextBlock(text) {
  return '<div class="ai_block"><span>' + text2html(text).replace(/\n/g, '<br>') + '</span></div>\n'
}

function aiButton(fn, id, label) {
  return '<button type="button" onclick="' + fn + '(' + id + ')">' + label + '</button> '
}

// replace_list / suggestions row
function aiItemBlock(row, type, ctx) {
  let ng = ''
  let ok = ''
  let html = ''
  let btn = ''
  const id = aiBlockId++
  if (type === 'suggestions') {
    ng = row && row['ng'] ? '' + row['ng'] : ''
    ok = row && row['ok'] ? '' + row['ok'] : ''
    html = '<div><b>' + text2html('' + (row && row['point'] ? row['point'] : '')) + '</b></div>' +
      '<div>' + text2html('' + (row && row['detail'] ? row['detail'] : '')).replace(/\n/g, '<br>') + '</div>'
    if (ng !== '' && ok !== '' && ng !== ok) {
      html += '<div><span class="ai-ng">[?] ' + text2html(ng) + '</span><br>' +
        '<span class="ai-ok">[v] ' + text2html(ok) + '</span></div>'
    }
  } else {
    const data = aiNormalizeJsonRow(row)
    if (!data) { return '' }
    ng = data['ng']
    ok = data['ok']
    html = '<div><span class="ai-ng">[?] ' + text2html(ng) + '</span><br>' +
      '<span class="ai-ok">[v] ' + text2html(ok) + '</span></div>' +
      '<div>' + text2html(data['desc']) + '</div>'
  }
  aiBlockItems[id] = { kind: 'replace', ng: ng, ok: ok, group: ctx.group, done: false }
  if (ng !== '' && ok !== '' && ng !== ok) {
    btn += aiButton('aiBlockReplace', id, lang('locale__replace'))
    btn += aiButton('aiBlockRemove', id, lang('locale__cancel'))
    btn += ' - '
    btn += aiButton('aiBlockFind', id, lang('locale__find'))
    btn += aiButton('aiBlockCopy', id, lang('locale__copy'))
  } else {
    aiBlockItems[id].done = true
  }
  return '<div id="aiBlockDiv' + id + '" class="ai_block" data-id="' + id + '">\n' +
    '<span id="aiBlock' + id + '">' + html + '</span>\n' +
    (btn ? '<div class="aiReplaceButtons">' + btn + '</div>' : '') + '</div>\n'
}

// rephrase candidate: replaces the captured selection
function aiCandidateBlock(row, ctx) {
  const data = aiNormalizeJsonRow({ ng: ctx.selText, ok: row && row['ok'], desc: row && row['desc'] })
  if (!data) { return '' }
  const id = aiBlockId++
  aiBlockItems[id] = { kind: 'candidate', ng: ctx.selText, ok: data['ok'], ctx: ctx, group: ctx.group, done: false }
  let btn = aiButton('aiBlockReplace', id, lang('locale__replace'))
  btn += aiButton('aiBlockCopy', id, lang('locale__copy'))
  return '<div id="aiBlockDiv' + id + '" class="ai_block" data-id="' + id + '">\n' +
    '<div id="aiBlock' + id + '"><span class="ai-ok">' + text2html(data['ok']).replace(/\n/g, '<br>') + '</span></div>' +
    '<div>' + text2html(data['desc']) + '</div>\n' +
    '<div class="aiReplaceButtons">' + btn + '</div></div>\n'
}

// append result: insert right after the captured selection
function aiInsertBlock(text, ctx) {
  const id = aiBlockId++
  const t = text.replace(/^\s+|\s+$/g, '')
  aiBlockItems[id] = { kind: 'insert', ok: t, ctx: ctx, group: ctx.group, done: false }
  let btn = ''
  if (ctx.action === 'append') {
    btn += aiButton('aiBlockInsert', id, lang('locale__insert'))
  } else {
    btn += aiButton('aiBlockAdd', id, lang('locale__insert'))
  }
  btn += aiButton('aiBlockCopy', id, lang('locale__copy'))
  return '<div id="aiBlockDiv' + id + '" class="ai_block" data-id="' + id + '">\n' +
    '<span id="aiBlock' + id + '">' + text2html(t).replace(/\n/g, '<br>') + '</span>\n' +
    '<div class="aiReplaceButtons">' + btn + '</div></div>\n'
}

function aiQualityBlock(q) {
  const score = parseInt(q['score'], 10)
  const list = function (arr) {
    if (!Array.isArray(arr) || arr.length === 0) { return '' }
    return '<ul class="ai_quality_list">' + arr.map(function (s) {
      return '<li>' + text2html('' + s) + '</li>'
    }).join('') + '</ul>'
  }
  let html = '<div class="ai_block">'
  html += '<div class="ai_quality_score">' + (isNaN(score) ? '-' : score) + ' / 100</div>'
  html += '<div>' + text2html('' + (q['summary'] || '')).replace(/\n/g, '<br>') + '</div>'
  const good = list(q['good'])
  const bad = list(q['bad'])
  if (good) { html += '<div><b>' + text2html(lang('locale__good_points')) + '</b>' + good + '</div>' }
  if (bad) { html += '<div><b>' + text2html(lang('locale__bad_points')) + '</b>' + bad + '</div>' }
  return html + '</div>\n'
}

// Apply every remaining candidate of a result group at once.
function aiApplyAll(gid) {
  const targets = []
  aiBlockItems.forEach(function (it, id) {
    if (it && it.kind === 'replace' && it.group === gid && !it.done) {
      targets.push(id)
    }
  })
  if (targets.length === 0) { return }
  const edit_txt = qs('#edit_txt')
  const body = edit_txt.value
  const items = targets.map(function (id) { return aiBlockItems[id] })
  const r = aiApplyReplacements(body, items)
  if (r.applied.length > 0) {
    aiSetEditorText(r.text, 'ai_apply_all')
  }
  r.applied.forEach(function (i) {
    const id = targets[i]
    aiBlockItems[id].done = true
    const block = qs('#aiBlockDiv' + id)
    if (block) { block.remove() }
  })
  r.notFound.forEach(function (i) {
    const block = qs('#aiBlockDiv' + targets[i])
    if (block) { block.classList.add('not_found') }
  })
  aiInfo(aiLangN('locale__applied_n', r.applied.length) + ' / ' +
    aiLangN('locale__not_found_n', r.notFound.length))
  if (r.notFound.length === 0) {
    const btn = document.querySelector('#aiGroup' + gid + ' .ai_apply_all')
    if (btn) { btn.remove() }
  }
}

function aiBlockAdd(id) {
  const it = aiBlockItems[id]
  if (!it) { return }
  const edit_txt = qs('#edit_txt')
  const body = edit_txt.value
  const start = edit_txt.selectionStart
  const end = edit_txt.selectionEnd
  const pos = start + it.ok.length
  aiSetEditorText(body.substring(0, start) + it.ok + body.substring(end), 'ai_block_add', pos, pos)
}

function aiBlockInsert(id) {
  const it = aiBlockItems[id]
  if (!it) { return }
  aiInsertDirect(it.ok, it.ctx)
}

function aiBlockCopy(id) {
  const it = aiBlockItems[id]
  copyToClipboard(it ? it.ok : qq('#aiBlock' + id).text())
}

function aiBlockFind(id) {
  const it = aiBlockItems[id]
  if (!it || !it.ng) { return }
  const edit_txt = qs('#edit_txt')
  const start = edit_txt.value.indexOf(it.ng)
  edit_txt.focus()
  if (start >= 0) {
    edit_txt.setSelectionRange(start, start + it.ng.length)
  } else {
    aiMarkNotFound(id)
  }
}

function aiMarkNotFound(id) {
  const block = qs('#aiBlockDiv' + id)
  if (block) { block.classList.add('not_found') }
  aiInfo(lang('locale__not_found') + ' ' + text2html(aiBlockItems[id].ng))
}

function aiBlockReplace(id) {
  const it = aiBlockItems[id]
  if (!it || it.ok === undefined) { return }
  const edit_txt = qs('#edit_txt')
  const body = edit_txt.value
  if (it.kind === 'candidate') {
    // the captured selection (kept up to date so candidates can be swapped)
    const c = it.ctx
    const newText = aiReplaceAt(body, c.selStart, c.selEnd, c.selText, it.ok)
    if (newText === null) { aiMarkNotFound(id); return }
    const pos = aiLocateRange(body, c.selStart, c.selEnd, c.selText)
    aiSetEditorText(newText, 'ai_block_replace', pos, pos + it.ok.length)
    c.selStart = pos
    c.selEnd = pos + it.ok.length
    c.selText = it.ok
    return
  }
  const start = body.indexOf(it.ng)
  if (start < 0) { aiMarkNotFound(id); return }
  const newText = body.substring(0, start) + it.ok + body.substring(start + it.ng.length)
  aiSetEditorText(newText, 'ai_block_replace', start, start + it.ok.length)
  it.done = true
  const block = qs('#aiBlockDiv' + id)
  if (block) { block.remove() }
}

function aiBlockRemove(id) {
  if (aiBlockItems[id]) { aiBlockItems[id].done = true }
  const block = qs('#aiBlockDiv' + id)
  if (block) {
    block.remove()
  }
}

function lang(id) {
  const e = document.getElementById(id)
  if (e) {
    return e.innerHTML
  }
  return id
}

function copyToClipboard(text) {
  if (navigator.clipboard) {
    return navigator.clipboard.writeText(text).then(function () {
      console.log('copied')
    })
  } else {
    alert('Sorry, not supported')
  }
}

function replaceOnClick() {
  openToolPanel('#replace_div')
}

function miscOnClick() {
  openToolPanel('#misc_div')
}

function settingsOnClick() {
  openToolPanel('#settings_div')
  // The layer may have auto-disabled itself after an error; keep the
  // toggle in sync with the real state whenever the panel opens.
  const toggle = qs('#k3hl_toggle')
  const api = window.kona3EditHighlight
  if (toggle && api) {
    toggle.checked = api.isEnabled()
  }
}

function convertToMarkdownClick() {
  if (typeof(convertToMarkdown) === undefined) {
    alert('please include resource/edit_wiki.js')
    return
  }
  const edit_txt = qq('#edit_txt')
  edit_txt.val(convertToMarkdown(edit_txt.val()))
}

function replace1() {
  const useRegExp = qq('#replace_regexp').val()
  const key = qq('#replace_key').val()
  const val = qq('#replace_val').val()
  const selstart = qq('#edit_txt').prop('selectionStart')
  if (key === '') { return }

  let index = selstart
  let text = qq('#edit_txt').val()
  replaceUndoBuffer = text
  let textPre = text.substring(0, selstart)
  text = text.substring(selstart)

  // try to replace
  let success = false
  if (useRegExp) {
    // regexp
    const re = new RegExp(key, '')
    const lines = text.split("\n")
    const result_lines = []
    let ii = 0
    for (let line of lines) {
      if (success) { // already replaced
        result_lines.push(line)
        continue
      }
      const m = line.match(re)
      if (m) {
        success = true
        line = line.replace(re, val)
        index += (ii + m.index + val.length)
        console.log("@", m)
        result_lines.push(line)
      } else {
        ii += line.length + 1 // line + LF
        result_lines.push(line)
      }
    }
    text = result_lines.join("\n")
  } else {
    // normal replace
    const i = text.indexOf(key)
    if (i >= 0) {
      success = true
      index += (i + val.length)
      text = text.replace(key, val)
    }
  }
  if (!success) {
    // move cursor to top
    qq('#edit_txt').prop('selectionStart', 0).prop('selectionEnd', 0).focus()
    console.log('replace1.failed', key, val, selstart)
    return
  }
  qq('#edit_txt').val(textPre + text)
  qq('#edit_txt').prop('selectionStart', index).prop('selectionEnd', index).focus()
  console.log('replace1.success', useRegExp, key, val, selstart)
}

function replaceAll() {
  const useRegExp = qq('#replace_regexp').val()
  const key = qq('#replace_key').val()
  const val = qq('#replace_val').val()
  let text = qq('#edit_txt').val()
  replaceUndoBuffer = text
  let selstart = qq('#edit_txt').prop('selectionStart')
  if (useRegExp) {
    const re = new RegExp(key, 'g')
    const lines = text.split("\n")
    const result_lines = []
    for (let line of lines) {
      line = line.replace(re, val)
      result_lines.push(line)
    }
    text = result_lines.join("\n")
    // find cursor pos
    const m = text.match(new RegExp(key))
    if (m && m.index > 0) {
      selstart = m.index + val.length
    }
  } else {
    text = text.split(key).join(val)
    // find cursor pos
    const i = text.indexOf(key)
    if (i > 0) {
      selstart = i + val.length - 1
    }
  }
  qq('#edit_txt').val(text)
  // recover cursor pos
  setTimeout(() => {
    qq('#edit_txt').prop('selectionStart', selstart).prop('selectionEnd', selstart).focus()
  }, 100)
}

function replaceUndo() {
  if (replaceUndoBuffer != '') {
    let text = qq('#edit_txt').val()
    qq('#edit_txt').val(replaceUndoBuffer)
    replaceUndoBuffer = text
  }
}

// Sync tags from visible input to hidden input
function syncTagsToHidden() {
  const tagsVisible = document.getElementById('tags_visible')
  const tagsHidden = document.getElementById('tags')
  if (tagsVisible && tagsHidden) {
    tagsHidden.value = tagsVisible.value
  }
}
