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

function kona3plugins_nako3_execute($params) {
    $base_dir = dirname(__FILE__).'/nako3';
    require_once $base_dir.'/index.inc.php';
    return nako3_main($params);
}



