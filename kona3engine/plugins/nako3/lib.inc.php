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
    if ($s == "disable_marker") {
      $nako3['disable_marker'] = true;
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

  $include_js =
    '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js" integrity="sha512-GZ1RIgZaSc8rnco/8CXfRdCpDxRCphenIiZ2ztLy3XQfCbQUSCuk8IudvNHxkRA3oUg6q0qejgN/qqyG1duv5Q==" crossorigin="anonymous"></script>' .
    '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-language_tools.min.js" integrity="sha512-8qx1DL/2Wsrrij2TWX5UzvEaYOFVndR7BogdpOyF4ocMfnfkw28qt8ULkXD9Tef0bLvh3TpnSAljDC7uyniEuQ==" crossorigin="anonymous"></script>' .
    '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-options.min.js" integrity="sha512-oHR+WVzBiVZ6njlMVlDDLUIOLRDfUUfRQ55PfkZvgjwuvGqL4ohCTxaahJIxTmtya4jgyk0zmOxDMuLzbfqQDA==" crossorigin="anonymous"></script>' .

    "<script defer src=\"${baseurl}release/wnako3.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_csv.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_datetime.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_kansuji.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_markup.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_turtle.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_webworker.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_caniuse.js\"></script>" .
  
    '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js" integrity="sha512-d9xgZrVZpmmQlfonhQUvTR7lMPtO7NkZMkA0ABN3PHCbKA5nqylQ/yWlFAyY6hYgdF1Qh6nYiuADWwKB4C2WSw==" crossorigin="anonymous"></script>' .
    '<script defer src="https://cdnjs.cloudflare.com/ajax/libs/mocha/8.3.0/mocha.min.js" integrity="sha512-LA/TpBXau/JNubKzHQhdi5vGkRLyQjs1vpuk2W1nc8WNgf/pCqBplD8MzlzeKJQTZPvkEZi0HqBDfRC2EyLMXw==" crossorigin="anonymous"></script>';
  return $include_js;
}
