// edit.js
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
    }
    // console.log(e.keyCode);
  }, false);

  $('#save_ajax_btn').click(function(){
    var action = $("#wikiedit form").attr('action');
    $.post(action,
      {
        'i_mode': 'ajax',
        'a_mode': 'trywrite',
        'a_hash': $('#a_hash').val(),
        'edit_txt': $('#edit_txt').val()
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
        $("#edit_info").html('saved --- ' + msg["a_hash"]);
        $('#a_hash').val(msg["a_hash"]);
      },
      "json");
  });
  
  // recover_div
  if (localStorage[STORAGE_KEY] !== undefined) {
    $('#recover_div').html(
      '<a href="#edit_txt" onclick="edit_recover()">Recover last text</a>');
  }
}

function edit_recover() {
  var r = confirm('Really recover text?');
  if (!r) return false;
  var edit_txt = qs('#edit_txt');
  edit_txt.value = localStorage[STORAGE_KEY]; 
}

