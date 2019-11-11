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
  // event
  var edit_txt = qs('#edit_txt');
  edit_txt.addEventListener('keydown', function(e) {
    var c = e.keyCode;
    if (37 <= c && c <= 40) {
      return;
    }
    if (c == 13) { // ENTER
      var text = edit_txt.value;
      localStorage[STORAGE_KEY] = text;
      countText();
    }
    use_beforeunload(true);
    // console.log(e.keyCode);
  }, false);

  $('#save_ajax_btn').click(save_ajax);
  // $('#outline_btn').click(change_outline);
  $(window).keydown(function(e) {
    // shortcut Ctrl+S
    if ((e.metaKey || e.ctrlKey) && e.keyCode == 83) {
      save_ajax();
      e.preventDefault();
      return false;
    }
  });
  // recover_div
  if (localStorage[STORAGE_KEY] !== undefined) {
    // recover?
  }
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


function save_ajax() {
  var action = $("#wikiedit form").attr('action');
  var text = $('#edit_txt').val();
  $.post(action,
  {
      'i_mode': 'ajax',
      'a_mode': 'trywrite',
      'a_hash': $('#a_hash').val(),
      'edit_txt': text
  })
  .done(function(msg) {
    if (typeof(msg) == 'string') {
      try {
        msg = JSON.parse(msg);
      } catch (e) {
        msg = {"result":false, "reason":msg};
      }
    }
    var result = msg["result"];
    if (!result) {
      $("#edit_info").val("Sorry request failed." + msg['reason']);
      return;
    }
    if (result == "ng") {
      console.log(msg);
      $("#edit_info").val("[error] " + msg['reason']);
      return;
    }
    countText();
    $("#edit_info").val('[saved] ' + msg["a_hash"]);
    $('#a_hash').val(msg["a_hash"]);
    use_beforeunload(false);
  })
  .fail(function(xhr, status, error){
    $("#edit_info").html("Sorry request failed." + error);
  });
}

function countText() {
  var s = ''
  var txt = $("#edit_txt").val()
  // total
  s += 'total(' + txt.length + ') '
  // id
  while (txt) {
    var i = txt.indexOf('{{{#count')
    if (i < 0) break;
    // trim left side
    txt = txt.substr(i)
    txt = txt.replace(/^\{+\#(countbox|count)/, '')
    // count
    var id = '*'
    var ts = ''
    var ti = txt.indexOf('(id=')
    var ei = 0
    if (ti == 0) { // with id
      txt = txt.substr(ti);
      var m = txt.match(/id=(.+)\)/);
      if (m) id = m[1];
      ei = txt.indexOf('}}}')
      ts = txt.substr(0, ei);
      ts = ts.split("\n").slice(1).join("")
      txt = txt.substr(ei + 3);
    } else {
      ei = txt.indexOf('}}}')
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



