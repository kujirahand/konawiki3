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
  const page_mode_el = qs('#page_mode')
  if (page_mode_el) {
    page_mode_el.addEventListener('change', () => {
      handleChange(true, 'page_mode')
    })
  }
  const ai_ask_btn = qs('#ai_ask_btn')
  if (ai_ask_btn) {
    qq(ai_ask_btn).click(aiAskClickHandler)
    qq('#ai_output_clear_btn').click(() => {
      qq('#ai_output').html('')
    })
    loadAITemplate()
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
      aiCompleteAtCursor()
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('ai_shortcut_spellcheck'))) {
      aiRunSpellCheckShortcut()
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('ai_shortcut_user_prompt1'))) {
      aiRunTemplateShortcut('# USER_PROMPT1')
      e.preventDefault()
    }
    if (matchesShortcut(e, getShortcutValue('ai_shortcut_user_prompt2'))) {
      aiRunTemplateShortcut('# USER_PROMPT2')
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
  // set #change-info and the topbar save state (issue #153)
  const saveState = qs('#edit_save_state')
  if (isChanged) {
    qq('#change-info').html('未保存')
    if (saveState) {
      saveState.textContent = '未保存の変更があります'
      saveState.classList.add('dirty')
    }
    use_beforeunload(true)
    setButtonsDisabled(false)
  } else {
    qq('#change-info').html('保存済み')
    if (saveState) {
      saveState.textContent = '保存済み'
      saveState.classList.remove('dirty')
    }
    use_beforeunload(false)
    setButtonsDisabled(true)
  }
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
        info.css('backgroundColor', '#ffffff')
        info.css('color', 'silver')
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

// Show only the given panel in the drawer. If it is already visible,
// hide it instead (toggle), unless forceShow is set. Returns the shown
// panel or null.
function showOnlyPanel(selector, forceShow) {
  const target = qs(selector)
  if (!target) {
    return null
  }
  const isVisible = target.style.display !== 'none'
  const panels = ['#help_div', '#tags_div', '#replace_div', '#history_div', '#misc_div', '#ai_div']
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
  openToolPanel('#ai_div')
  const selectBox = document.getElementById('ai_template_select')
  if (!selectBox) { return }
  showDisplay('#ai_div')
  selectOptionByValue(selectBox, '# 誤字脱字をチェック') // ja
  selectOptionByValue(selectBox, '# Check for Typos') // en
  // document.getElementById('ai_ask_btn')?.click();
}

function aiRunSpellCheckShortcut() {
  aiSpellCheckOnClick()
  const btn = document.getElementById('ai_ask_btn')
  const input = document.getElementById('ai_input_text')
  if (input && input.value.trim() === '') {
    input.value = getSpellCheckPrompt()
  }
  if (btn && !btn.disabled) {
    btn.click()
  }
}

function aiRunTemplateShortcut(templateName) {
  const selectBox = document.getElementById('ai_template_select')
  if (!selectBox) { return }
  showDisplay('#ai_div')
  if (!selectOptionByValue(selectBox, templateName)) {
    qq('#edit_info').html('AI template not found: ' + templateName)
    return
  }
  const btn = document.getElementById('ai_ask_btn')
  if (btn && !btn.disabled) {
    btn.click()
  }
}

function getSpellCheckPrompt() {
  return "### 指示:\n" +
    "あなたは文章校正の達人です。以下の入力に対して誤字脱字を指摘してください。\n" +
    "次のJSON形式で指摘してください。\n" +
    "```json\n" +
    "[{\"ng\":\"…ここに誤字脱字…\",\"ok\":\"…ここに修正した内容…\",\"desc\":\"…ここに間違いの理由…\"}]\n" +
    "```\n" +
    "### 入力:\n" +
    "__TEXT__\n"
}

function selectOptionByValue(selectElement, value) {
  console.log('selectOptionByValue', selectElement, value)
  const options = selectElement.options
  for (let i = 0; i < options.length; i++) {
    if (options[i].value === value) {
      selectElement.selectedIndex = i
      selectElement.dispatchEvent(new Event('change'))
      return true
    }
  }
  return false
}

var loaderTimerId = 0
var loaderCount = 0
function aiButtonEnabeld(enbaled) {
  // button
  qq('#ai_ask_btn').prop('disabled', !enbaled)
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

function aiReplaceText(text) {
  // body
  const edit_txt = qs('#edit_txt')
  let body = edit_txt.value
  // seltext
  const ai_seltext_chk = qs('#ai_seltext_chk')
  const sel_start = edit_txt.selectionStart
  const sel_end = edit_txt.selectionEnd
  let body_sel = body.substring(sel_start, sel_end) // selected text
  // replace
  body = body.replace(/\`{3}/g, '\\`\\`\\`')
  body_sel = body_sel.replace(/\`{3}/g, '\\`\\`\\`')
  text = text.replace(/__TEXT__/g, '```' + body + '```')
  text = text.replace(/__TEXT_SELECTED__/g, '```' + body_sel + '```')
  return text
}

function aiAskClickHandler() {
  // get prompt
  const ai_input_text = qs('#ai_input_text')
  let text = ai_input_text.value
  // replace
  text = aiReplaceText(text)
  // trim
  text = text.replace(/^\s/, '').replace(/\s$/, '')
  if (text == '') {
    aiInsertText('---')
    return
  }
  // test case
  if (text.substring(0, 3) === '@@@') {
    aiInsertText(text.substring(3))
    return
  }
  // get model
  const ai_model_el = qs('#ai_model')
  const ai_model = ai_model_el ? ai_model_el.value : ''

  // ajax
  aiButtonEnabeld(false)
  var action = qq('#wikiedit form').attr('action')
  qq().post(action,
    {
      'i_mode': 'ajax',
      'edit_token': qq('#edit_token').val(),
      'q': 'ai',
      'ai_input_text': text,
      'a_mode': 'ask',
      'a_hash': qq('#a_hash').val(),
      'ai_model': ai_model,
    })
    .done(function (obj) {
      aiButtonEnabeld(true)
      if (typeof obj !== 'object' || obj === null) {
        aiInsertText('Error: Invalid response from server. ' + obj)
        return
      }
      const msg = obj['message']
      console.log('@@@', obj)
      aiInsertText('' + msg)
      const token = (obj['token'] !== undefined) ? obj['token'] : 0
      qq('#ai_ask_cost').html(token + 'token')
    })
    .fail(function (error) {
      console.error(error)
      qq('#edit_info').html("Sorry AI request failed." + error)
      aiButtonEnabeld(true)
    })
}

function aiCompleteAtCursor() {
  const edit_txt = qs('#edit_txt')
  if (!edit_txt || !qs('#ai_ask_btn')) { return }
  const start = edit_txt.selectionStart
  const end = edit_txt.selectionEnd
  const before = edit_txt.value.substring(0, start)
  const after = edit_txt.value.substring(end)
  const context = before.slice(-4000)
  let prompt = "### 指示:\n"
  prompt += "以下の入力文の続きを、文体と内容に合わせて1文だけ作成してください。\n"
  prompt += "説明や前置きは不要です。続きを本文だけで出力してください。\n"
  prompt += "### 入力:\n"
  prompt += "```" + context.replace(/\`{3}/g, '\\`\\`\\`') + "```\n"
  aiAskRaw(prompt, function (msg, token) {
    const insertText = ('' + msg).replace(/^\s+|\s+$/g, '')
    edit_txt.value = before + insertText + after
    const pos = before.length + insertText.length
    edit_txt.focus()
    edit_txt.selectionStart = pos
    edit_txt.selectionEnd = pos
    handleChange(true, 'ai_complete')
    qq('#ai_ask_cost').html(token + 'token')
  })
}

function aiAskRaw(text, callback) {
  const ai_model_el = qs('#ai_model')
  const ai_model = ai_model_el ? ai_model_el.value : ''
  aiButtonEnabeld(false)
  var action = qq('#wikiedit form').attr('action')
  qq().post(action,
    {
      'i_mode': 'ajax',
      'edit_token': qq('#edit_token').val(),
      'q': 'ai',
      'ai_input_text': text,
      'a_mode': 'ask',
      'a_hash': qq('#a_hash').val(),
      'ai_model': ai_model,
    })
    .done(function (obj) {
      aiButtonEnabeld(true)
      if (typeof obj !== 'object' || obj === null) {
        aiInsertText('Error: Invalid response from server. ' + obj)
        return
      }
      if (obj['result'] !== 'ok') {
        aiInsertText('' + obj['message'])
        return
      }
      const msg = obj['message']
      const token = (obj['token'] !== undefined) ? obj['token'] : 0
      callback(msg, token)
    })
    .fail(function (error) {
      console.error(error)
      qq('#edit_info').html("Sorry AI request failed." + error)
      aiButtonEnabeld(true)
    })
}

let aiBlockId = 0
const aiBlockItems = []
function _aiInsertText(data, isJSON = false) {
  let old = qq('#ai_output').html()
  let btn = ''
  let text = ''
  if (isJSON) {
    // locale strings
    const locale_replace = lang('locale__replace')
    const lcoale_find = lang('locale__find')
    const lcoale_copy = lang('locale__copy')
    const lcoale_cancel = lang('locale__cancel')
    const originalData = data
    data = aiNormalizeJsonRow(data)
    if (!data) {
      _aiInsertText(JSON.stringify(originalData, null, 2), false)
      return
    }
    // set block items
    aiBlockItems[aiBlockId] = data
    const ng = text2html(data['ng'])
    const ok = text2html(data['ok'])
    const desc = text2html(data['desc'])
    text = `<div><span class="ai-ng">[?] ${ng}</span><br>` +
      `<span class="ai-ok">[v] ${ok}</span></div>` +
      `<div>${desc}</div>`
    btn += `<button onclick="aiBlockReplace(${aiBlockId})">${locale_replace}</button> `
    btn += `<button onclick="aiBlockRemove(${aiBlockId})">${lcoale_cancel}</button>`
    btn += ' - '
    btn += `<button onclick="aiBlockFind(${aiBlockId})">${lcoale_find}</button> `
    btn += `<button onclick="aiBlockCopy(${aiBlockId})">${lcoale_copy}</button>`
  } else {
    text = text2html('' + data)
    text = text.replace(/\n/g, '<br>')
    btn += `<button onclick="aiBlockAdd(${aiBlockId})">Add</button>`
    btn += `<button onclick="aiBlockCopy(${aiBlockId})">Copy</button>`
  }
  const div =
    `<div id="aiBlockDiv${aiBlockId}" class="ai_block">\n` +
    `<span id="aiBlock${aiBlockId}">${text}</span>\n` +
    `<div id="aiReplaceButtons">${btn}</div></div>\n`
  qq('#ai_output').html(old + div)
  aiBlockId++
}

function aiNormalizeJsonRow(row) {
  if (typeof row !== 'object' || row === null) {
    return null
  }
  const normalized = {
    ng: aiPickJsonValue(row, ['ng', 'before', 'old', 'original', 'source', 'ErrorLocation', 'error', '誤り', '修正前', '対象']),
    ok: aiPickJsonValue(row, ['ok', 'after', 'new', 'replacement', 'Correction', 'correction', '修正', '修正後', '言い換え']),
    desc: aiPickJsonValue(row, ['desc', 'description', 'Reason', 'reason', '理由', '説明'])
  }
  if (normalized.ng === '' || normalized.ok === '') {
    return null
  }
  return normalized
}

function aiPickJsonValue(row, keys) {
  for (const key of keys) {
    if (row[key] !== undefined && row[key] !== null) {
      return '' + row[key]
    }
  }
  return ''
}

function aiInsertText(text) {
  // 戻り値がJSONの場合
  if (text.indexOf('```') >= 0) {
    text = text.replace('```json', '```')
    const code = text.split('```')[1]
    try {
      const data = JSON.parse(code)
      for (let row of data) {
        _aiInsertText(row, true)
      }
      return
    } catch (err) {
      console.error('aiInsertText: JSON parse error', err)
    }
  }
  // テキストの場合
  _aiInsertText(text, false)
}

function aiBlockAdd(id) {
  const text = qq('#aiBlock' + id).text()
  const edit_txt = qs('#edit_txt')
  const start = edit_txt.selectionStart
  const end = edit_txt.selectionEnd
  edit_txt.value = edit_txt.value.substring(0, start) + text + edit_txt.value.substring(end)
  const pos = start + text.length
  edit_txt.focus()
  edit_txt.selectionStart = pos
  edit_txt.selectionEnd = pos
  handleChange(true, 'ai_block_add')
}

function aiBlockCopy(id) {
  const text = qq('#aiBlock' + id).text()
  copyToClipboard(text)
}

function aiBlockFind(id) {
  const obj = aiBlockItems[id]
  const ng = obj['ng']
  const ok = obj['ok']
  if (ng === undefined || ok === undefined) {
    console.error('aiBlockReplace: ng or ok is undefined')
    return
  }
  const edit_txt = qq('#edit_txt').val()
  const start = edit_txt.indexOf(ng)
  const end = start + ng.length
  qq('#edit_txt').focus()
  if (start >= 0) {
    qq('#edit_txt').prop('selectionStart', start).prop('selectionEnd', end)
  }
}

function aiBlockReplace(id) {
  const obj = aiBlockItems[id]
  const ng = obj['ng']
  const ok = obj['ok']
  if (ng === undefined || ok === undefined) {
    console.error('aiBlockReplace: ng or ok is undefined')
    return
  }
  let edit_txt = qq('#edit_txt').val()
  // edit_txt = edit_txt.split(ng).join(ok);
  const start = edit_txt.indexOf(ng)
  const old_text = edit_txt
  edit_txt = edit_txt.replace(ng, ok) // 一度だけ置換
  const end = start + ok.length
  qq('#edit_txt').val(edit_txt)
  qq('#edit_txt').focus()
  if (start >= 0) {
    qq('#edit_txt').prop('selectionStart', start).prop('selectionEnd', end)
  }
  if (old_text === edit_txt) {
    alert(lang('locale__not_found') + `『${ng}』`)
  } else {
    // close block
    const block = qs('#aiBlockDiv' + id)
    if (block) {
      block.remove()
    }
  }
}

function aiBlockRemove(id) {
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

function loadAITemplate() {
  const action = qq('#wikiedit form').attr('action')
  let params = {
    'i_mode': 'ajax',
    'edit_token': qq('#edit_token').val(),
    'q': 'ai',
    'a_mode': 'load_template',
    'a_hash': qq('#a_hash').val(),
  }
  qq().post(action, params)
    .done(function (obj) {
      const messageStr = obj['message']
      const selectBox = qq('#ai_template_select')
      const messages = messageStr.split("\r").join("").split("\n")
      const templateData = {}
      let key = ''
      messages.forEach(function (message) {
        if (message.substring(0, 2) == '# ') {
          key = message
          const option = document.createElement("option")
          option.text = message
          selectBox.append(option)
          templateData[key] = ''
          return
        }
        if (message.substring(0, 5) == '-----') {
          key = ''
          return
        }
        templateData[key] += message + "\n"
      })
      selectBox.change(() => {
        const key = selectBox.val()
        if (key == '') { return }
        const input = qq('#ai_input_text')
        const val = templateData[key]
        if (val) {
          input.val(val)
        }
      })
    })
    .fail(function (error) {
      qq('#edit_info').html("Sorry AI request failed." + error)
      aiButtonEnabeld(true)
    })
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
