// edit.js
// global
var outline_mode = false;
var outline_lines = [];

window.addEventListener('load', edit_init, false);
function qs(id) { return document.querySelector(id); }

// detect storage key
var href = location.href;
href = href.replace(/\?.*$/, '');
href = href.replace('index.php', '');
href = href.replace(/(http|https)\:\/\//, '');
var STORAGE_KEY = 'kona3:' + href;

function edit_init() {
  // edit event
  var edit_txt = qs('#edit_txt');
  edit_txt.addEventListener('keydown', function(e) {
    var c = e.keyCode;
    if (37 <= c && c <= 40) {
      return;
    }
    if (c == 13) { // ENTER
      ls_save();
    }
    use_beforeunload(true);
    // console.log(e.keyCode);
  }, false);

  // set button event
  $('#temporarily_save_btn').click(temporarily_save);
  // $('#outline_btn').click(change_outline);
  $('#git_save_btn').click(git_save);
  $('#ls_load_btn').click(ls_load);
  
  // shortcut
  $(window).keydown(function(e) {
    // shortcut Ctrl+S
    if ((e.metaKey || e.ctrlKey) && e.keyCode == 83) {
      temporarily_save();
      e.preventDefault();
    }
  });
}

var use_unload_flag = false;
function use_beforeunload(b) {
  if (use_unload_flag == b) return;
  if (b) {
    $(window).on('beforeunload', function() {
      return "Finish editing?";
    });
    $('form').on('submit', function() {
      $(window).off('beforeunload');
    });
  } else {
    $(window).off('beforeunload');
  }
  use_unload_flag = b;
}

function ls_save() {
  const edit_txt = qs('#edit_txt');
  localStorage[STORAGE_KEY] = edit_txt.value;
}

function ls_load() {
  if (!localStorage[STORAGE_KEY]) return;
  if (!confirm('OK?')) return;
  const edit_txt = qs('#edit_txt');
  edit_txt.value = localStorage[STORAGE_KEY];
}

function temporarily_save() {
  ls_save();
  save_ajax();
}

function save_ajax() {
  $('#temporarily_save_btn').prop('disabled', true);
  go_ajax('trywrite');
}
function git_save() {
  $('#git_save_btn').prop('disabled', true);
  go_ajax('trygit');
}


function go_ajax(a_mode) {
  var action = $("#wikiedit form").attr('action');
  var text = $('#edit_txt').val();
  $.post(action,
  {
      'i_mode': 'ajax',
      'a_mode': a_mode,
      'a_hash': $('#a_hash').val(),
      'edit_txt': text,
      'edit_token': $('#edit_token').val(),
      'tags': $('#tags').val()
  })
  .done(function(msg) {
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
      $("#edit_info").val("[error] " + msg['reason']);
      $("#edit_info").css("color", "red");
      setButtonsDisabled(false);
      return;
    }
    // count
    countText();
    // set hash
    $('#a_hash').val(msg["a_hash"]);
    $("#edit_info").val('[saved] ' + msg["a_hash"]);
    use_beforeunload(false);    
    // effect - flash info field
    const info = $("#edit_info");
    const oldColor = info.css('backgroundColor');
    info.css('backgroundColor', '#ffffc0');
    setTimeout(function() {
      info.css('backgroundColor', '#f0f0ff');
    }, 500);
    // button
    setButtonsDisabled(false);
  })
  .fail(function(xhr, status, error) {
    $("#edit_info").html("Sorry request failed." + error);
  });
}

function setButtonsDisabled(stat) {
  $('#git_save_btn').prop('disabled', stat);
  $('#temporarily_save_btn').prop('disabled', stat);
}

function countText() {
  var s = ''
  var txt = $("#edit_txt").val()
  // total
  s += 'total(' + txt.length + ') '
  // id
  while (txt) {
    var close_tag = '}}}'
    var i = txt.indexOf('{{{#count')
    if (i < 0) {
        i = txt.indexOf('```#count')
        if (i < 0) break;
        close_tag = '```'
    }
    // trim left side
    txt = txt.substr(i)
    txt = txt.replace(/^[\{`]+\#(countbox|count)/, '')
    // count
    var id = '*'
    var ts = ''
    var ti = txt.indexOf('(id=')
    var ei = 0
    if (ti == 0) { // with id
      txt = txt.substr(ti);
      var m = txt.match(/id=(.+)\)/);
      if (m) id = m[1];
      ei = txt.indexOf(close_tag)
      ts = txt.substr(0, ei);
      ts = ts.split("\n").slice(1).join("")
      txt = txt.substr(ei + close_tag.length);
    } else {
      ei = txt.indexOf(close_tag)
      ts = txt.substr(0, ei);
      ts = ts.split("\n").join("")
    }
    s += id + '(' + ts.length + ') '
  }
  $("#edit_counter").val(s);
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
    $('#edit_txt').show();
    $('#outline_btn').val('Outline');
    $('#outline_div').html('');
  } else {
    $('#edit_txt').hide();
    $('#outline_btn').val('Text');
    outline_build();
  }
}

function outline_build() {
  // alert('実装中のテスト機能です。');
  outline_lines = [];
  var txt = $('#edit_txt').val();
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
  $('#edit_txt').val(text.join("\n"));
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



