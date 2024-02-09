<?php
/** 回り込みをクリアする
 * - [書式] #clear || #clear(----)
 * - [引数]
 * -- "-" ... 水平線を表示
 * -- "!" ... コメント(無視する)
 */
function kona3plugins_clear_execute($args) {
  // args
  $hr = array_shift($args);
  $hr = ($hr === null) ? "" : $hr;
  $c = substr($hr, 0, 1);
  if ($c == "-") {
    return "<p style='clear:both'></p><hr>\n";
  }
  if ($c == "!") { $hr = ""; } // comment
  return "<p style='clear:both'></p>\n";
}


