<?php
/** konawiki3 plugins -- なでしこ3のWEBエディタを表示する
 * - [書式]
{{{
#nako3(なでしこのプログラム);
}}}
 * - [引数]
 * -- rows=num エディタの行数
 * -- ver=xxx なでしこ3のバージョン
 * -- canvas canvasを用意する場合に指定
 * -- baseurl=url なでしこ3の基本URL
 * --- post=url 保存先CGI(デフォルトは、nako3storage)
 * -- edit/editable 編集可能な状態にする
 * -- size=(width)x(height) canvasの幅と高さ
 * - [使用例] #nako3(なでしこのプログラム);
{{{
#nako3(なでしこのプログラム);
}}}
 * - [備考]
 * - [公開設定] 公開
 */

function kona3plugins_nako3_execute($params)
{
  // pid
  $pid = kona3_getPluginInfo("nako3", "pid", 0) + 1;
  kona3_setPluginInfo("nako3", "pid", $pid);

  // default value
  $code = "";
  $rows = 5;
  $ver = "3.0.72"; // default version
  $size_w = 300;
  $size_h = 300;
  $use_canvas = false;
  $baseurl = "";
  $editable = false;
  $post_url = "https://nadesi.com/v3/storage/index.php?page=0&action=presave";
  foreach ($params as $s) {
    if ($s == "edit" || $s == "editable") {
      $editable = true;
      continue;
    }
    if (preg_match('#rows\=([0-9]+)#', $s, $m)) {
      $rows = $m[1]; continue;
    }
    if (preg_match('#ver\=([0-9\.]+)#', $s, $m)) {
      $ver = $m[1]; continue;
    }
    if (preg_match('#baseurl\=([0-9a-zA-Z\.\_\/\%\:\&\#]+)#', $s, $m)) {
      $baseurl = $m[1]; continue;
    }
    if (preg_match('#post\=([0-9a-zA-Z\.\_\/\%\:\&\#]+)#', $s, $m)) {
      $post_url = $m[1]; continue;
    }
    if ($s == "canvas") {
      $use_canvas = true; continue;
    }
    if (preg_match('#size\=([0-9]+)x([0-9]+)#', $s, $m)) {
      $use_canvas = true;
      $size_w = $m[1];
      $size_h = $m[2];
      continue;
    }
    $code = $s;
    break;
  }
  // URL
  $include_js = "";
  if ($pid == 1) {
    if ($baseurl == "") {
      $pc = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
      $baseurl = "https://nadesi.com/v3/cdn.php?v=$ver&f=";
    }
    $jslist = array(
      // nadesiko
      $baseurl."release/wnako3.js?v=$ver",
      $baseurl."release/plugin_turtle.js",
      // chart.js
      'https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js'
    );
    foreach ($jslist as $js) {
      $include_js .= "<script defer src='$js'></script>";
    }
  }
  // JavaScriptとCSSは1回だけあれば良い
  $js_code = "";
  $style_code = "";
  if ($pid == 1) {
    $js_code = plugin_nako3_gen_js_code($baseurl, $use_canvas);
    $style_code = plugin_nako3_gen_style_code();
  }
  // CODE
  $canvas_code = "";
  if ($use_canvas) {
    $canvas_code =
      "<canvas id='nako3_canvas_$pid' ".
      "width='$size_w' height='$size_h'></canvas>";
  }
  $j_use_canvas = ($use_canvas) ? 1 : 0;
  $readonly = ($editable) ? "" : "readonly='1' style='background-color:#f0f0f0;'";
  $can_save = ($editable) ? 'true' : 'false';
	$html = trim(htmlspecialchars($code));
  return <<< EOS
<!-- nako3 plugin -->
{$include_js}{$style_code}
<div class="nako3">

<div id="nako3_editor_main_{$pid}" class="nako3row">
<form id="nako3codeform_{$pid}" action="{$post_url}" method="POST">
<textarea rows="$rows" id="nako3_code_$pid"
          class="nako3txt" name="body" {$readonly}>{$html}</textarea>
<input type="hidden" name="version" value="{$ver}" />
</form>
</div><!-- end of #nako3_editor_main_{$pid} -->

<div id="nako3_editor_controlls_{$pid}" class="nako3row" style="padding-bottom:4px;">
  <button onclick="nako3_run($pid, $j_use_canvas)">▶ 実行</button>
  <button onclick="nako3_clear($pid, $j_use_canvas)">クリア</button>
  <span id="post_span_{$pid}" class="post_span">
    <button id="post_button_{$pid}" onclick="nako3_post_{$pid}()">公開</button>
    &nbsp;&nbsp;
    <a href="#" id="save_button_{$pid}" class="tmp_btn" onclick="nako3_save({$pid})">仮保存</a>
    <a href="#" id="load_button_{$pid}" class="tmp_btn" onclick="nako3_load({$pid})">仮読込</a>
  </span>
  <span class='nako3ver'>&nbsp;&nbsp;v{$ver}</span>
</div><!-- end of #nako3_editor_controlls_{$pid} -->

<!-- ERROR -->
<div class="nako3row nako3error" id="nako3_error_{$pid}" style="display:none"></div>

<!-- RESULT -->
<div id="nako3result_div_$pid" class="nako3row" style="display:none;">
  <textarea class="nako3row nako3info" readonly
            id="nako3_info_$pid" rows="5" style="display:none"></textarea>
  <div style="text-align:right;margin:0;padding:0">
    <span id="nako3_conv_html_$pid" class="nako3_conv_html_link"
             onclick="nako3_conv_html($pid)">→HTML出力</span>
  </div>
  <div id="nako3_info_html_$pid" class="nako3info_html" style="display:none"></div>
</div><!-- end of #nako3_error_{$pid} -->

<!-- FREE DOM AREA -->
<div id="nako3_div_{$pid}" class="nako3_div"></div>

{$canvas_code}

{$js_code}
</div><!-- end of #nako3 -->

<!-- dynamic js code -->
<script type="text/javascript">
post_span_{$pid}.style.visibility = {$can_save} ? "visible" : "hidden"; // for post
function nako3_post_{$pid}() {
  const post_button = document.getElementById('post_button_{$pid}')
  document.getElementById('nako3codeform_{$pid}').submit();
}
</script>
<!-- /nako3 plugin -->
EOS;
}

// ---------------------------------------------------------
// CSS
// ---------------------------------------------------------
function plugin_nako3_gen_style_code() {
  // --- CSS --
  return <<< EOS
<style>
.nako3 { border: 1px solid #a0a0ff; padding:4px; margin:2px; }
.nako3row { margin:0; padding: 0; }
.nako3txt {
  margin:0; padding: 4px; font-size:1em; line-height:1.2em;
  width: 98%;
}
.nako3row  > button, 
.post_span > button { font-size:1em; padding:8px; }
.post_span { margin-left: 8px; }
.tmp_btn {
  border-bottom: 1px solid gray;
  text-decoration: none;
  padding: 4px;
  font-size: 0.8em;
  background-color: #f3f3ff;
}
.tmp_btn > a {
  color: black;
}
.nako3info {
  background-color: #f0f0ff;
  border: 1px solid #a0a0ff;
  padding: 4px; margin: 0;
  font-size: 1em;
  width:98%;
}
.nako3error {
  background-color: #fff0f0; padding:8px; color: #904040;
  font-size:1em; border:1px solid #a0a0ff; margin:4px;
}
.nako3ver { font-size:0.7em; color:gray; }
.nako3info_html {
  border: 1px solid #a0a0a0;
  padding: 4px; margin: 4px;
}
.nako3_conv_html_link {
  color: navy;
  font-size: 9px; padding: 4px;
  border: 1px solid silver;
  background-color: #f0f0f0;
}
.nako3_div {
  font-size: 1em;
  line-height: 1.1em;
}
.nako3_div button {
  margin: 4px;
  padding: 4px;
  font-size: 0.9em;
}
.nako3_div input {
  margin: 6px;
  padding: 6px;
}
.nako3_div input[type=checkbox] {
  padding: 4px;
  margin: 4px;
}
</style>
EOS;
}
// ---------------------------------------------------------
// JavaScript
// ---------------------------------------------------------
function plugin_nako3_gen_js_code($baseurl, $use_canvas) {
  $s_use_canvas = ($use_canvas) ? "true" : "false";
  $j_use_canvas = ($use_canvas) ? 1 : 0;
  return <<< EOS
<script type="text/javascript">
var nako3_info_id = 0
var baseurl = "{$baseurl}"
var use_canvas = $s_use_canvas
var nako3_get_resultbox = function () {
  return document.getElementById("nako3result_div_" + nako3_info_id)
}
var nako3_get_info = function () {
  return document.getElementById("nako3_info_" + nako3_info_id)
}
var nako3_get_error = function () {
  return document.getElementById("nako3_error_" + nako3_info_id)
}
var nako3_get_canvas = function () {
  return document.getElementById("nako3_canvas_" + nako3_info_id)
}
var nako3_get_div = function () {
  return document.getElementById("nako3_div_" + nako3_info_id)
}
// 表示
var nako3_print = function (s) {
  console.log("[表示] " + s)
  var info = nako3_get_info()
  if (!info) return
  var box = nako3_get_resultbox()
  box.style.display = 'block'
  s = "" + s // 文字列に変換
  // エラーだった場合
  if (s.substr(0, 9) == "==ERROR==") {
    s = s.substr(9)
    var err = nako3_get_error()
    err.innerHTML = s
    err.style.display = 'block'
    return
  } else {
    info.innerHTML += to_html(s) + "\\n"
    info.style.display = 'block'
  }
}
//---------------------------------
var nako3_clear = function (s, use_canvas) {
  var info = nako3_get_info()
  if (!info) return
  info.innerHTML = ''
  info.style.display = 'none'
  var err = nako3_get_error()
  err.innerHTML = ''
  err.style.display = 'none'
  var div = nako3_get_div()
  if (div) div.innerHTML = ''
  if (use_canvas) {
    var canvas = nako3_get_canvas()
    if (canvas) {
      var ctx = canvas.getContext('2d')
      ctx.clearRect(0, 0, canvas.width, canvas.height)
    }
  }
}

// 独自関数の登録
var nako3_add_func = function () {
  navigator.nako3.setFunc("表示", [['の', 'を', 'と']], nako3_print)
  navigator.nako3.setFunc("表示ログクリア", [], nako3_clear)
}
var nako3_init_timer = setInterval(function(){
  if (typeof(navigator.nako3) === 'undefined') return
  clearInterval(nako3_init_timer)
  nako3_add_func()
}, 500)

function to_html(s) {
  s = '' + s
  return s.replace(/\&/g, '&amp;')
          .replace(/\</g, '&lt;')
          .replace(/\>/g, '&gt;')
}
//------------------------------------
// なでしこのプログラムを実行する関数
//------------------------------------
function nako3_run(id, use_canvas) {
  if (typeof(navigator.nako3) === 'undefined') {
    alert('現在ライブラリを読み込み中です。しばらくお待ちください。')
    return
  }
  var code_e = document.getElementById("nako3_code_" + id)
  if (!code_e) return
  var code = code_e.value
  var canvas_name = "#nako3_canvas_" + id
  var div_name = "#nako3_div_" + id
  var addon =
    "「" + div_name + "」へDOM親要素設定;" +
    "「" + div_name + "」に「」をHTML設定;"
  if (use_canvas) {
    addon += 
      "「" + canvas_name + "」へ描画開始;" +
      "カメ描画先=「" + canvas_name + "」;" +
      "カメ全消去;" +
      "カメ画像URL=「" + baseurl + "/demo/turtle.png」;"
  } 
  try {
    nako3_info_id = id
    nako3_clear()
    navigator.nako3.run(addon + code)
    console.log("DONE")
  } catch (e) {
    nako3_print("==ERROR==" + e.message + "")
    console.log(e)
  }
}
// コンソールに出したテキストをHTMLに変換して表示
function nako3_conv_html(id) {
  var textInfo = document.getElementById('nako3_info_' + id)
  var htmlInfo = document.getElementById('nako3_info_html_' + id)
  htmlInfo.style.display = 'block'
  htmlInfo.innerHTML = textInfo.value
}

// 仮保存のための処理
function get_kari_hozon_key(pid) {
  return 'nako3edit_kari_src_' + pid;
}
function nako3_save(pid) {
  var doc = document.getElementById('nako3_code_' + pid)
  localStorage[get_kari_hozon_key(pid)] = doc.value
  alert('仮保存しました')
}
function nako3_load(pid) {
  var src = localStorage[get_kari_hozon_key(pid)];
  if (!src) {
    alert('仮保存しているプログラムはありません');
    return;
  }
  var a = confirm(
    '仮保存しているソースを読み込みますか？\\n' + 
    '---\\n' + src.substr(0, 10));
  if (!a) return;
  var doc = document.getElementById('nako3_code_' + pid);
  doc.value = src;
}
</script>
EOS;
}
// ---------------------------------------------------------



