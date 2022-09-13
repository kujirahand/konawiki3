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
 * --- post=url 保存先CGI(デフォルトは、nako3storage)
 * -- edit/editable 編集可能な状態にする
 * -- disable_marker  コンパイルエラーを表示しない
 * -- size=(width)x(height) canvasの幅と高さ
 * --- use_textarea テキストエリアで表示する
 * --- nakofile=xxx data以下のファイル名を指定
 * --- debug デバッグモードで実行する (ただし、v3.3.72以降で使える)
 * --- auto_run 自動実行する
 * - [使用例] #nako3(なでしこのプログラム);
{{{
#nako3(なでしこのプログラム);
}}}
 * - [備考]
 * - [公開設定] 公開
 */

// wiki page から実行される
function kona3plugins_nako3_execute($params) {
    $base_dir = dirname(__FILE__).'/nako3';
    require_once $base_dir.'/index.inc.php';
    return nako3_main($params);
}

// ?xxx&plugin&name=nako3&... より実行されるaction
// ?xxx&plugin&name=nako3&filecode=xxx にてdataフォルダ以下のxxx.nako3を読み込む
// Plugin APIを実装するのに使われる
function kona3plugins_nako3_action() {
    // GETをチェック
    $mode = empty($_GET['mode']) ? "run" : $_GET['mode'];
    if ($mode == 'run') {
        kona3plugins_nako3_action_mode_run();
        return;
    }
    echo "api_error";
}

function kona3plugins_nako3_action_mode_run() {
    global $kona3conf;
    // check login
    if ($kona3conf["wiki_private"]) {
        if (!kona3isLogin()) {
            kona3error('先にログインしてください');
            return;
        }
    }
    
    // $_GET => parameterを変換
    $params = [];
    if (isset($_GET['edit'])) { $params[] = 'edit'; }
    if (isset($_GET['editable'])) { $params[] = 'editable'; }
    if (isset($_GET['canvas'])) { $params[] = 'canvas'; }
    if (isset($_GET['use_textarea'])) { $params[] = 'use_textarea'; }
    // 値を持つオプション
    if (isset($_GET['rows'])) { $params[] = 'rows='.$_GET['rows']; }
    if (isset($_GET['size'])) { $params[] = 'size='.$_GET['size']; }
    if (isset($_GET['ver'])) { $params[] = 'ver='.$_GET['ver']; }
    if (isset($_GET['nakofile'])) { $params[] = 'nakofile='.$_GET['nakofile']; }
    
    // load nako3 main
    $base_dir = dirname(__FILE__).'/nako3';
    require_once $base_dir.'/index.inc.php';
    $html = nako3_main($params);
    kona3showMessage('#nako3', $html, 'white.html');
}


