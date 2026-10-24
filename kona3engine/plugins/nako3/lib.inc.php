<?php

// プラグインのパラメータを生成
function nako3_parse_params(&$nako3, $params)
{

    // プラグインの初期値をセット
    $nako3['rows'] = 5;
    $nako3['code'] = '';
    $nako3['editable'] = false;
    $nako3['use_canvas'] = false;
    $nako3['size_w'] = 300;
    $nako3['size_h'] = 300;
    $nako3['use_textarea'] = false;
    $nako3['nakofile'] = '';
    $nako3['debug'] = false;
    $nako3['auto_run'] = false;
    $nako3['disable_marker'] = false;

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
        if ($s == "debug") {
            $nako3['debug'] = true;
            continue;
        }
        if ($s == "auto_run") {
            $nako3['auto_run'] = true;
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

// 外部スクリプトの読み込み失敗を記録する onerror 属性を生成する (#44)
// ネットに繋がっていない等で読み込めなかった場合に、無言で待たせ続けるのではなく
// エラーを表示できるよう、失敗したファイル名を window.__nako3_load_errors へ積む。
// この属性を持つタグは、通知先の nako3_report_load_error() (tpl-js-edit.html) の
// 定義より前に出力されることがあるため、関数があれば呼ぶだけでなくキューへの
// 記録を必須とし、定義側が後からキューを確認できるようにする。
function nako3_script_onerror_attr($name)
{
    // ファイル名は英数字と ._- のみで構成される前提だが、属性値を壊さないよう正規化する
    $name = preg_replace('/[^a-zA-Z0-9\._\-]/', '_', (string)$name);
    return " onerror=\"(window.__nako3_load_errors=window.__nako3_load_errors||[]).push('{$name}');" .
        "if(window.nako3_report_load_error){window.nako3_report_load_error('{$name}');}\"";
}

// <script>タグを生成
function nako3_make_script_tag(&$nako3)
{
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
        $ace_files = [
            ['ace.js', 'sha512-GZ1RIgZaSc8rnco/8CXfRdCpDxRCphenIiZ2ztLy3XQfCbQUSCuk8IudvNHxkRA3oUg6q0qejgN/qqyG1duv5Q=='],
            ['ext-language_tools.min.js', 'sha512-8qx1DL/2Wsrrij2TWX5UzvEaYOFVndR7BogdpOyF4ocMfnfkw28qt8ULkXD9Tef0bLvh3TpnSAljDC7uyniEuQ=='],
            ['ext-options.min.js', 'sha512-oHR+WVzBiVZ6njlMVlDDLUIOLRDfUUfRQ55PfkZvgjwuvGqL4ohCTxaahJIxTmtya4jgyk0zmOxDMuLzbfqQDA=='],
            ['ext-code_lens.min.js', 'sha512-gsDyyKTnOmSWRDzUbpYcPjzVsEyFGSWeWefzVKvbMULPR2ElIlKKsOtU3ycfybN9kncZXKLFSsUiG3cgJUbc/g=='],
        ];
        $ace_a = [];
        foreach ($ace_files as $ace_file) {
            list($file, $hash) = $ace_file;
            $ace_a[] = '<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.4.12/' . $file . '"' .
                ' integrity="' . $hash . '" crossorigin="anonymous"' .
                nako3_script_onerror_attr($file) . '></script>';
        }
        $include_js .= implode("\n", $ace_a) . "\n";
    }
    // wnako3 and plugins
    $plugins = [
        'wnako3.js',
        'plugin_markup.js',
        'plugin_turtle.js',
        'plugin_webworker.js',
        'plugin_caniuse.js',
        'plugin_kansuji.js',
    ];
    foreach ($plugins as $plugin) {
        $include_js .= "<script defer src=\"{$baseurl}release/$plugin\"" . nako3_script_onerror_attr($plugin) . "></script>\n";
    }
    return $include_js;
}

function isIE()
{
    // IE対策のためmsieパラメータをセット
    $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    $msie = FALSE;
    if (strstr($agent, 'trident') || strstr($agent, 'msie')) {
        $msie = TRUE;
    }
    return $msie;
}
