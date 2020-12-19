<?php
//
// nako3.inc.php から利用される
//
// === #nako3 プラグインのデフォルト値 ===
global $nako3;
$nako3 = array(
  'ver'         => '3.1.9', // なでしこ3のバージョン
  'baseurl'     => '',      // @see nako3_set_script_url
  'post_url'    => 'https://nadesi.com/v3/storage/index.php?page=0&action=presave',
);
require_once __DIR__ . '/lib.inc.php';
require_once __DIR__ . '/tpl.inc.php';

// #nako3 plugin main function
function nako3_main($params) {
  global $nako3;
  
  // Wiki内に複数の#nako3プラグインを配置しても大丈夫なように
  // nako3 の pid でエディタを区別する
  $pid = kona3_getPluginInfo("nako3", "pid", 0) + 1;
  kona3_setPluginInfo("nako3", "pid", $pid);
  $nako3['pid'] = $pid;

  // プラグインのパラメータを解析
  nako3_parse_params($nako3, $params);
  
  // JavaScriptとCSSは1回だけあれば良い
  $nako3['script_once'] = '';
  if ($pid == 1) {
    // <script> <style>タグを生成
    $nako3['script_once'] =
      "<!-- [nako3.include_once] -->\n".
      nako3_make_script_tag($nako3).
      nako3_template('tpl-style.html',array()).
      nako3_template('tpl-js-edit.html', $nako3).
      nako3_template('tpl-js-nako3.html', $nako3).
      "<!-- [/nako3.include_once] -->\n";
  }
  
  // 共通のコード
  $nako3['canvas_code'] = '';
  if ($nako3['use_canvas']) {
    $size_w = $nako3['size_w'];
    $size_h = $nako3['size_h'];
    $nako3['canvas_code'] =
      "<canvas id='nako3_canvas_{$pid}' ".
      " width='{$size_w}' height='{$size_h}'>".
      "</canvas>";
  }
  $nako3['j_use_canvas'] = ($nako3['use_canvas']) ? 1 : 0;
  $nako3['readonly'] = ($nako3['editable']) 
    ? '' : 
    "readonly='1' style='background-color:#f0f0f0;'";
  $nako3['can_save'] = ($nako3['editable']) ? 'true' : 'false';
  $nako3['edit_height'] = (ceil(intval($nako3['rows']) * 1.5)).'em';

  // show template
  $src = nako3_template('tpl-code.html', $nako3);
  return
    "<!-- [nako3.plugin] -->\n".
    $src.
    "<!-- [/nako3.plugin] -->\n";
}




