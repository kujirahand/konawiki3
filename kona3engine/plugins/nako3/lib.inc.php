<?php

// プラグインのパラメータを生成
function nako3_parse_params(&$nako3, $params) {
  
  // プラグインの初期値をセット
  $nako3['rows'] = 5;
  $nako3['code'] = '';
  $nako3['editable'] = false;
  $nako3['use_canvas'] = false;
  $nako3['size_w'] = 300;
  $nako3['size_h'] = 300;
 
  // パラメータを一つずつチェック
  foreach ($params as $s) {
    
    // 固定キーのパラメータ
    if ($s == "edit" || $s == "editable") {
      $nako3['editable'] = true;
      continue;
    }
    if ($s == "canvas") {
      $nako3['use_canvas'] = true;
      continue;
    }
    
    // 引数付きパラメータ
    if (preg_match('#rows\=([0-9]+)#', $s, $m)) {
      $nako3['rows'] = $m[1];
      continue;
    }
    if (preg_match('#size\=([0-9]+)x([0-9]+)#', $s, $m)) {
      $nako3['use_canvas'] = true;
      $nako3['size_w'] = $m[1];
      $nako3['size_h'] = $m[2];
      continue;
    }
    if (preg_match('#ver\=([0-9\.]+)#', $s, $m)) {
      $nako3['ver'] = $m[1];
      continue;
    }
    if (preg_match('#baseurl\=([0-9a-zA-Z\.\_\/\%\:\&\#]+)#', $s, $m)) {
      $nako3['baseurl'] = $m[1];
      continue;
    }
    if (preg_match('#post\=([0-9a-zA-Z\.\_\/\%\:\&\#]+)#', $s, $m)) {
      $nako3['post_url'] = $m[1];
      continue;
    }
    
    // それ以外の時はプログラムのコード
    $nako3['code'] = $s;
    break;
  }
}

// <script>タグを生成
function nako3_make_script_tag(&$nako3) {
  // 基本URL
  if ($nako3['baseurl'] == '') {
    $ver = $nako3['ver'];
    $nako3['baseurl'] = "https://nadesi.com/v3/cdn.php?v=$ver&f=";
  }
  // 各JavaScriptのパスを設定
  $baseurl = $nako3['baseurl'];
  $jslist = array(
    // editor
    array(
      'src'   => 'https://pagecdn.io/lib/ace/1.4.12/ace.js',
      'async' => FALSE,
    ),
    // nadesiko
    array(
      'src'   => $baseurl."release/wnako3.js",
      'async'=> TRUE
    ),
    array(
      'src'   => $baseurl."release/plugin_turtle.js",
      'async' => TRUE,
    ),
    // chart.js
    array(
      'src'   => 'https://cdn.jsdelivr.net/npm/chart.js@2.9.3/dist/Chart.min.js',
      'async' => TRUE,
    ),
  );
  // スクリプトタグを生成
  $include_js = '';
  foreach ($jslist as $js) {
    $src = $js['src'];
    $defer = $js['async'] ? 'defer' : '';
    $include_js .= "<script {$defer} src=\"$src\"></script>\n";
  }
  return $include_js;
}



