// -----------------------------------------------
// konawiki3 - edit.js
// -----------------------------------------------
// const
const LS_KEY_AUTOSAVE = 'kona3:edit:autosave';
const TIMER_INTERVAL = 1000 * 60; // 1min
// var
var outline_mode = false;
var outline_lines = [];
var isChanged = false;
var timerIdAutosave = 0;
var isWaiting = false; // Ajaxの待ち合わせ中

// 簡単なDOM操作の関数群
function qs(id) { return document.querySelector(id); }
function qsa(id) { return document.querySelectorAll(id); }
function prop(id, key) {
  const e = qs(id);
  if (!e) { return ''; }
  return e.getAttribute(key);
}
function setEnabled(id, enabled) {
  let e = id;
  if (typeof(id) === 'string') { e = qs(id); }
  if (!e) { return false; }
  e.disabled = !enabled;
}
function getEnabled(id) {
  let e = id;
  if (typeof(id) === 'string') { e = qs(id); }
  if (!e) { return false; }
  return !e.disabled;
}
function qq(id) {
  let e = id;
  if (typeof(id) === 'string') {
    e = document.querySelector(id);
  }
  if (!e) {
    console.warn('qq: not found', id);
    return null;
  }
  const obj = {}
  obj.click = (f) => {
    e.addEventListener('click', f);
  };
  obj.attr = (key, val) => {
    if (val !== undefined) {
      return e.getAttribute(key);
    }
    e.setAttribute(key, val);
  };
  obj.prop = (key, val) => {
    if (val !== undefined) {
      e[key] = val;
    }
    return e[key];
  };
  obj.html = (val) => {
    if (val !== undefined) {
      e.innerHTML = val;
    }
    return e.innerHTML;
  };
  obj.val = (val) => {
    if (val !== undefined) {
      e.value = val;
    }
    return e.value;
  };
  obj.css = (styleName, val) => {
    if (val !== undefined) {
      e.style[styleName] = val;
    }
    return e.style[styleName];
  };
  obj.on = (event, f) => {
    e.addEventListener(event, f);
  };
  obj.enabled = (val) => {
    if (val !== undefined) {
      e.disabled = !val;
    }
    return !e.disabled;
  };
  return obj;
}

// init
window.addEventListener('load', edit_init, false);

// detect storage key
var href = location.href;
href = href.replace(/\?.*$/, '');
href = href.replace('index.php', '');
href = href.replace(/(http|https)\:\/\//, '');
var STORAGE_KEY = 'kona3:' + href;

function edit_init() {
  // editor key event
  const edit_txt = qs('#edit_txt');
  edit_txt.addEventListener('keydown', editorKeydownHandler, false);

  // set button event
  qq('#temporarily_save_btn').click(clickTempSaveButton);
  // qq('#outline_btn').click(change_outline);
  const git_save_btn = qs('#git_save_btn');
  if (git_save_btn) {
    qq(git_save_btn).click(git_save);
  }
  qq('#ls_load_btn').click(loadTextFromLS);
  qq('#autosave').click(autoSaveClickHandler);
  const ai_ask_btn = qs('#ai_ask_btn');
  if (ai_ask_btn) {
    qq(ai_ask_btn).click(aiAskClickHandler);
    qq('#ai_output_clear_btn').click(() => {
      qq('#ai_output').html('');
    });
    loadAITemplate();
  }
  loadAutoSave();
  
  // shortcut
  $(window).keydown(function(e) {
    // shortcut Ctrl+S
    if ((e.metaKey || e.ctrlKey) && e.keyCode == 83) {
      clickTempSaveButton();
      e.preventDefault();
    }
    // shortcut Ctrl+Alt+N
    if ((e.metaKey || e.ctrlKey) && e.altKey && e.keyCode == 78) {
      $url = document.getElementById('new_btn_url').href;
      console.log('open new page:', $url);
      window.open($url);
      e.preventDefault();
    }
  });
}

// edit_txt.onkeydown
function editorKeydownHandler(event) {
  const c = event.keyCode;
  if (37 <= c && c <= 40) { // arrow key
    return;
  }
  if (c == 13) { // ENTER
    saveTextToLS();
  }
  if (!isChanged) {
    isChanged = true;
    use_beforeunload(true);
    setButtonsDisabled(false);
  }
}

// auto save setting
function loadAutoSave() {
  const autosaveUI = qq('#autosave');
  // default is true
  autosaveUI.prop('checked', true);
  // load from localStorage
  const ls = localStorage[LS_KEY_AUTOSAVE];
  if (ls) {
    console.log('localStorage.'+LS_KEY_AUTOSAVE+'=', ls);
    switch (ls) {
      case 'yes': autosaveUI.prop('checked', true); break;
      case 'no':  autosaveUI.prop('checked', false); break;
    }
  }
  const autosave = autosaveUI.prop('checked');
  console.log('autosave=', autosave);
  // timer
  if (autosave) {
    if (timerIdAutosave > 0) { clearInterval(timerIdAutosave); }
    timerIdAutosave = setInterval(timerAutoSaveOnTime, TIMER_INTERVAL);
  }
}
function saveAutoSave(enabled) {
  localStorage[LS_KEY_AUTOSAVE] = (enabled) ? 'yes': 'no';
}
function autoSaveClickHandler() {
  const autosave = qq('#autosave').prop('checked');
  saveAutoSave(autosave);
  loadAutoSave();
}

var use_unload_flag = false;
function use_beforeunload(b) {
  if (use_unload_flag == b) return;
  if (b) {
    $(window).on('beforeunload', function() {
      return "Finish editing?";
    });
    qq('form').on('submit', function() {
      $(window).off('beforeunload');
    });
  } else {
    $(window).off('beforeunload');
  }
  use_unload_flag = b;
}

function saveTextToLS() {
  const edit_txt = qs('#edit_txt');
  localStorage[STORAGE_KEY] = edit_txt.value;
}

function loadTextFromLS() {
  if (!localStorage[STORAGE_KEY]) return;
  if (!confirm('OK?')) return;
  const edit_txt = qs('#edit_txt');
  edit_txt.value = localStorage[STORAGE_KEY];
}

function clickTempSaveButton() {
  if (isWaiting) {
    console.log('save - waiting')
    return
  }
  console.log('save')
  saveTextToLS();
  save_ajax();
}

function save_ajax() {
  qq('#temporarily_save_btn').prop('disabled', true);
  go_ajax('trywrite');
}
function git_save() {
  qq('#git_save_btn').prop('disabled', true);
  go_ajax('trygit');
}

// Timer
function timerAutoSaveOnTime() {
  if (isChanged && !isWaiting) {
    if (!qq('#temporarily_save_btn').prop('disabled')) {
      clickTempSaveButton();
    }
  }
}

function go_ajax(a_mode) {
  if (isWaiting) {
    console.log("- skip go_ajax:" + a_mode)
    return;
  }
  isWaiting = true;
  var action = qq('#wikiedit form').attr('action');
  var text = qq('#edit_txt').val();
  $.post(action,
  {
      'i_mode': 'ajax',
      'a_mode': a_mode,
      'a_hash': qq('#a_hash').val(),
      'edit_txt': text,
      'edit_ext': qq('#edit_ext').val(),
      'edit_token': qq('#edit_token').val(),
      'tags': qq('#tags').val()
  })
  .done(function(msg) {
    isChanged = false;
    isWaiting = false;
    // parse to json
    if (typeof(msg) == 'string') {
      try {
        msg = JSON.parse(msg);
      } catch (e) {
        msg = {"result":false, "reason":msg};
      }
    }
    // check result
    var result = msg["result"];
    if (result != 'ok') {
      console.log(msg);
      const code = msg['code'];
      if (code == 'nologin') {
        console.log('try to login!!')
        // auto login?
        kona3tryAutologin(false);
        setTimeout(() => {
          save_ajax();
        }, 1000);
      }
      qq('#edit_info').html("[error] " + msg['reason']);
      qq('#edit_info').css("color", "red");
      setButtonsDisabled(false);
      return;
    }
    // count
    countText();
    // set hash
    qq('#a_hash').val(msg["a_hash"]);
    qq('#edit_info').html('[saved]');
    use_beforeunload(false);
    // effect - flash info field
    const info = qq('#edit_info');
    // const oldColor = info.css('backgroundColor');
    info.css('backgroundColor', '#ffffc0');
    info.css('color', 'green');
    setTimeout(function() {
      info.css('backgroundColor', '#f0f0ff');
      info.css('color', 'silver');
    }, 700);
  })
  .fail(function(xhr, status, error) {
    isWaiting = false;
    qq('#edit_info').html("Sorry request failed." + error);
    setButtonsDisabled(false);
  });
}

function setButtonsDisabled(stat) {
  qq('#git_save_btn').prop('disabled', stat);
  qq('#temporarily_save_btn').prop('disabled', stat);
}

function countText() {
  let s = ''
  let txt = qq('#edit_txt').val()
  const counterTag = [['{{{#count', '}}}'], ['```#count', '```'], [':::count', ':::'], [':::#count', ':::']]
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
    txt = txt.replace(/^[\{`:]+\#?(countbox|count)/, '')
    // count
    var id = '*'
    var ts = ''
    var ti = txt.indexOf('(id=')
    var ei = 0
    if (ti == 0) { // with id
      txt = txt.substr(ti);
      var m = txt.match(/id=(.+)\)/);
      if (m) id = m[1];
      ei = txt.indexOf(closeTag)
      ts = txt.substr(0, ei);
      ts = ts.split("\n").slice(1).join("")
      txt = txt.substr(ei + closeTag.length);
    } else {
      ei = txt.indexOf(closeTag)
      ts = txt.substr(0, ei);
      ts = ts.split("\n").join("")
    }
    s += id + '(' + ts.length + ') '
  }
  qq('#edit_counter').html(s);
}

function edit_recover() {
  var r = confirm('Really recover text?');
  if (!r) return false;
  var edit_txt = qs('#edit_txt');
  edit_txt.value = localStorage[STORAGE_KEY];
}

function change_outline() {
  outline_mode = !outline_mode;
  if (outline_mode == false) {
    outline_to_text();
    qq('#edit_txt').show();
    qq('#outline_btn').val('Outline');
    qq('#outline_div').html('');
  } else {
    qq('#edit_txt').hide();
    qq('#outline_btn').val('Text');
    outline_build();
  }
}

function outline_build() {
  // alert('実装中のテスト機能です。');
  outline_lines = [];
  var txt = qq('#edit_txt').val();
  var lines = txt.split("\n");
  var root = document.createElement('div');
  for (var i = 0; i < lines.length; i++) {
    var line = document.createElement('div');
    var cmd = lines[i];
    line.innerHTML = text2html(lines[i]);
    line.contentEditable = true;
    var ch = cmd.substr(0, 1);
    if (ch == "-") {
      var l = cmd.match(/^\-+/);
      var px = l[0].length * 20;
      line.style.marginLeft= px + "px";
    }
    root.appendChild(document.createElement('a'));
    root.appendChild(line);
    outline_lines.push(line);
  }
  qs('#outline_div').appendChild(root);
}

function outline_to_text() {
  if (outline_lines.length == 0) return;
  var text = [];
  for (var i = 0; i < outline_lines.length; i++) {
    text.push(html2text(outline_lines[i].innerHTML));
  }
  qq('#edit_txt').val(text.join("\n"));
  outline_lines = [];
}

function text2html(s) {
  s = s.replace(/\&/g, '&amp;');
  s = s.replace(/\</g, '&lt;');
  s = s.replace(/\>/g, '&gt;');
  return s;
}
function html2text(s) {
  s = s.replace(/\&gt\;/g, '>');
  s = s.replace(/\&lt\;/g, '<');
  s = s.replace(/\&amp\;/g, '&');
  return s;
}

function jump(url, windowName) {
  if (windowName !== undefined) {
    window.open(url, windowName);
    return;
  }
  window.location.href = url;
}

function toggleDisplay(query) {
  const e = qs(query);
  if (e.style.display == 'none') {
    e.style.display = 'block';
  } else {
    e.style.display = 'none';
  }
}

function historyOnClick() {
  toggleDisplay('#history_div');
}
function helpOnClick() {
  toggleDisplay('#help_div');
}
function tagsOnClick() {
  toggleDisplay('#tags_div');
}
function aiOnClick() {
  toggleDisplay('#ai_div');
}

var loaderTimerId = 0;
var loaderCount = 0;
function aiButtonEnabeld(enbaled) {
  // button
  qq('#ai_ask_btn').prop('disabled', !enbaled);
  // loader
  if (!enbaled) {
    qq('#ai_loader').show();
    if (loaderTimerId > 0) {
      clearInterval(loaderTimerId);
    }
    loaderCount = 0;
    loaderTimerId = setInterval(() => {
      loaderCount++;
      const tmp = '=====';
      let s = tmp.substring(0, loaderCount % 5) + 'abcde'
      s = s.substring(0, 5);
      qq('#ai_loader').text(s);
    }, 100);
  } else {
    qq('#ai_loader').hide();
    if (loaderTimerId > 0) {
      clearInterval(loaderTimerId);
    }
  }
}

function aiReplaceText(text) {
  // body
  const edit_txt = qs('#edit_txt');
  let body = edit_txt.value;
  // seltext
  const ai_seltext_chk = qs('#ai_seltext_chk');
  const sel_start = edit_txt.selectionStart;
  const sel_end = edit_txt.selectionEnd;
  let body_sel = body.substring(sel_start, sel_end); // selected text
  // replace
  body = body.replace(/\`{3}/g, '\\`\\`\\`');
  body_sel = body_sel.replace(/\`{3}/g, '\\`\\`\\`');
  text = text.replace(/__TEXT__/g, '```' + body + '```');
  text = text.replace(/__TEXT_SELECTED__/g, '```' + body_sel + '```');
  return text;
}

function aiAskClickHandler() {
  // get prompt
  const ai_input_text = qs('#ai_input_text');
  let text = ai_input_text.value;
  // replace
  text = aiReplaceText(text);
  // trim
  text = text.replace(/^\s/, '').replace(/\s$/, '');
  if (text == '') {
    aiInsertText('---');
    return;
  }
  console.log('@@@aiAskClickHandler:', text)
  // test case
  if (text.substring(0, 3) === '@@@') {
    aiInsertText(text.substring(3));
    return;
  }
  // ajax
  aiButtonEnabeld(false);
  var action = qq('#wikiedit form').attr('action');
  $.post(action,
    {
      'i_mode': 'ajax',
      'edit_token': qq('#edit_token').val(),
      'q': 'ai',
      'ai_input_text': text,
      'a_mode': 'ask',
      'a_hash': qq('#a_hash').val(),
    })
    .done(function (obj) {
      aiButtonEnabeld(true);
      const msg = obj['message'];
      aiInsertText('' + msg);
    })
    .fail(function (xhr, status, error) {
      qq('#edit_info').html("Sorry AI request failed." + error);
      aiButtonEnabeld(true);
    });
}

let aiBlockId = 1000
function aiInsertText(text) {
  let old = qq('#ai_output').html();
  text = text2html(text);
  text = text.replace(/\n/g, '<br>');
  let btn = ''
  if (text.indexOf('ErrorLocation') >= 0) {
    btn += `<button onclick="aiBlockReplace(${aiBlockId})">Replace</button>`;
  } else {
    btn += `<button onclick="aiBlockAdd(${aiBlockId})">Add</button>`
    btn += `<button onclick="aiBlockCopy(${aiBlockId})">Copy</button>`
  }
  const div = 
    `<div id="aiBlockDiv${aiBlockId}" class="ai_block">` +
    `<span id="aiBlock${aiBlockId}">${text}</span>` +
    `<div style="text-align:right;">${btn}</div></div>`
  qq('#ai_output').html(div + old);
  aiBlockId++;
}
function aiBlockAdd(id) {
  const text = qq('#aiBlock' + id).text();
  const edit_txt = qs('#edit_txt');
  edit_txt.value += "\n" + text;
}
function aiBlockCopy(id) {
  const text = qq('#aiBlock' + id).text();
  copyToClipboard(text);
}

function aiBlockReplace(id) {
  console.log('@aiBlockReplace', id)
  // extract JSON block
  let block = qq('#aiBlock' + id).text();
  let edit_txt = qq('#edit_txt').text();
  if (block.indexOf('```json') >= 0) {
    block = block.match(/```json(.+)```/s)[1];
  }
  try {
    const replace_list = JSON.parse(block);
    for (let row of replace_list) {
      if (!row['ErrorLocation']) { continue; }
      const loc = row['ErrorLocation'];
      const cor = row['Correction'];
      console.log('@replace', loc, cor)
      edit_txt = edit_txt.replace(loc, cor);
    }
    qq('#edit_txt').val(edit_txt);
    qq('#aiBlockDiv' + id).remove();
  } catch (e) {
    console.log('aiBlockReplace: JSON parse error', e);
    alert('JSON parse error');
    return;
  }
}

function loadAITemplate() {
  const action = qq('#wikiedit form').attr('action');
  console.log('@', action)
  let params = {
    'i_mode': 'ajax',
    'edit_token': qq('#edit_token').val(),
    'q': 'ai',
    'a_mode': 'load_template',
    'a_hash': qq('#a_hash').val(),
  }
  $.post(action, params)
  .done(function (obj) {
    const messageStr = obj['message'];
    const selectBox = qq('#ai_template_select');
    const messages = messageStr.split("\n");
    const templateData = {};
    let key = '';
    messages.forEach(function (message) {
      if (message.substring(0, 2) == '# ') {
        key = message
        const option = document.createElement("option");
        option.text = message;
        selectBox.append(option);
        templateData[key] = ''
        return;
      }
      if (message.substring(0, 5) == '-----') {
        key = '';
        return;
      }
      templateData[key] += message + "\n";
    });
    selectBox.change(()=>{
      const key = selectBox.val();
      if (key == '') { return; }
      const input = qq('#ai_input_text');
      if (templateData[key]) {
        input.val(templateData[key]);
      }
    })
  })
  .fail(function (xhr, status, error) {
    qq('#edit_info').html("Sorry AI request failed." + error);
    aiButtonEnabeld(true);
  });
}

function copyToClipboard(text) {
  if (navigator.clipboard) {
    return navigator.clipboard.writeText(text).then(function () {
      console.log('copied')
    })
  } else {
    alert('Sorry, not supported');
  }
}