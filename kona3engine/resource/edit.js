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
    if (c == 13) { // ENTER
      var text = edit_txt.value;
      localStorage[STORAGE_KEY] = text;
      $('#edit_info').html('localStorage.saved len=' + text.length);
      if ($("#auto_save").val() == "auto_save") {
        save_ajax();
      }
    }
    // console.log(e.keyCode);
  }, false);

  $('#save_ajax_btn').click(save_ajax);
  $('#outline_btn').click(change_outline);
  
  // recover_div
  if (localStorage[STORAGE_KEY] !== undefined) {
    $('#recover_div').html(
      '<a href="#edit_txt" onclick="edit_recover()">Recover last text</a>');
  }
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
    },
    function(msg) {
      var result = msg["result"];
      if (!result) {
        $("#edit_info").html("Sorry request failed.");
        return;
      }
      if (result == "ng") {
        $("#edit_info").html("[error]" + msg['reason']);
        return;
      }
      $("#edit_info").html('saved --- ' + msg["a_hash"] + 
          " --- " + text.length + "字");
      $('#a_hash').val(msg["a_hash"]);
    },
    "json");
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
  var txt = $('#edit_txt').val();
  var lines = txt.split("\n");
  var root = document.createElement('div');
  for (var i = 0; i < lines.length; i++) {
    var line = document.createElement('div');
    var cmd = lines[i];
    line.innerHTML = lines[i];
    line.contentEditable = true;
    var ch = cmd.substr(0, 1);
    if (ch == "-") {
      var l = cmd.match(/^\-+/);
      var px = l[0].length * 16;
      line.style.marginLeft= px + "px";
    }
    root.appendChild(document.createElement('a'));
    root.appendChild(line);
  }
  qs('#outline_div').appendChild(root);
}



