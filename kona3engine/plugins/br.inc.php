<?php
/** <br>要素を指定個数挿入するプラグイン
 * - [書式] #br(5)
 * - [引数]
 *   - 個数
 */

function kona3plugins_br_execute($args) {
  $count = array_shift($args);
  if (!is_numeric($count) || $count < 1) {
    $count = 1; // デフォルトは1
  }
  $br = str_repeat('<br>', $count);
  return $br;
}


