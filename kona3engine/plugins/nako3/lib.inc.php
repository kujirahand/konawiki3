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
  $nako3['use_textarea'] = false;
  $nako3['nakofile'] = '';
 
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
    if ($s == "use_textarea") {
      $nako3['use_textarea'] = true;
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
    if (preg_match('#post\=([0-9a-zA-Z\.\_\/\%\:\&\#]+)#', $s, $m)) {
      $nako3['post_url'] = $m[1];
      continue;
    }
    if (preg_match('#nakofile\=([0-9a-zA-Z\.\_\/\%\:\&\#]+)#', $s, $m)) {
      $nako3['nakofile'] = $m[1];
      continue;
    }
    
    // それ以外の時はプログラムのコード
    $nako3['code'] = $s;
    break;
  }
  
  // nakofileが指定されていればwikiファイルを取り込む
  if (!empty($nako3['nakofile'])) {
    $nakofile = $nako3['nakofile'];
    $nakofile = kona3getWikiFile($nakofile, FALSE);
    if (!file_exists($nakofile)) {
      $nako3['code'] = '## ファイルが見当たりませんでした';
    } else {
      $nako3['code'] = file_get_contents($nakofile);
    }
  }
  // もしIEであれば、use_textareaを強制
  if (isIE()) {
    $nako3['use_textarea'] = true;
  }
}

// <script>タグを生成
function nako3_make_script_tag(&$nako3) {
  // 基本URL
  if ($nako3['baseurl'] == '') {
    $ver = $nako3['ver'];
    $nako3['baseurl'] = "https://n3s.nadesi.com/cdn.php?v=$ver&f=";
  }
  // 各JavaScriptのパスを設定
  $baseurl = $nako3['baseurl'];
  $include_js = '';
  if (!isIE()) {
    // for ace editor
    $include_js .=
      '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ace.js" integrity="sha512-GZ1RIgZaSc8rnco/8CXfRdCpDxRCphenIiZ2ztLy3XQfCbQUSCuk8IudvNHxkRA3oUg6q0qejgN/qqyG1duv5Q==" crossorigin="anonymous"></script>' .
      '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-language_tools.min.js" integrity="sha512-8qx1DL/2Wsrrij2TWX5UzvEaYOFVndR7BogdpOyF4ocMfnfkw28qt8ULkXD9Tef0bLvh3TpnSAljDC7uyniEuQ==" crossorigin="anonymous"></script>' .
      '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-options.min.js" integrity="sha512-oHR+WVzBiVZ6njlMVlDDLUIOLRDfUUfRQ55PfkZvgjwuvGqL4ohCTxaahJIxTmtya4jgyk0zmOxDMuLzbfqQDA==" crossorigin="anonymous"></script>'.
      '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/ext-code_lens.min.js" integrity="sha512-gsDyyKTnOmSWRDzUbpYcPjzVsEyFGSWeWefzVKvbMULPR2ElIlKKsOtU3ycfybN9kncZXKLFSsUiG3cgJUbc/g==" crossorigin="anonymous"></script>';
  }
  // wnako3 and plugins
  $include_js .=
    "<script defer src=\"${baseurl}release/wnako3.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_csv.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_datetime.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_kansuji.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_markup.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_turtle.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_webworker.js\"></script>" .
    "<script defer src=\"${baseurl}release/plugin_caniuse.js\"></script>";
  // add-on plugins
  $include_js .=    
    "<script defer src=\"${baseurl}demo/js/chart.js@3.2.1/chart.min.js\" integrity=\"sha256-uVEHWRIr846/vAdLJeybWxjPNStREzOlqLMXjW/Saeo=\" crossorigin=\"anonymous\"></script>";
  return $include_js;
}

function isIE() {
    // IE対策のためmsieパラメータをセット
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $msie = FALSE;
    if (strstr($agent , 'trident') || strstr($agent , 'msie')) { $msie = TRUE; }
    return $msie;
}

