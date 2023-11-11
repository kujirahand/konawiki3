<?php
/** HTMLを出力するプラグイン(action=showのときのみ)
 * - [書式] #htmlshow(HTMLタグ)
 * - [引数]
 * -- HTMLタグ タグを出力する
 * - [使用例] #htmlshow(<b>test</b>)
 */

function kona3plugins_htmlshow_execute($params) {
    if ($_GET['action'] == 'show') {
      $html = array_shift($params);
      return $html;
    } else {
      return "- #htmlshow -";
    }
}

