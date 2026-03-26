<?php
/** 要約を表示用領を表示
 * - [書式] {{{#summary ... }}}
 * - [引数]
 * -- id ... 識別子(省略可)
 */

function kona3plugins_summary_execute($args) {
  $text = "";
  $id = "summary";
  while ($args) {
    $line = trim(array_shift($args));
    if (preg_match('/^id\=(\w+)/', $line, $m)) {
      $id = $m[1];
    } else {
      $text = $line;
    }
  }
  // count
  $len  = strlen($text);
  $mlen = mb_strlen($text);
  $html = konawiki_parser_convert($text);
  // length
  $s = "{$mlen}字";
  return "<div data-id='$id' class='column summary'><div>{$html}</div><div style='text-align:right'><span class='memo'>({$s})</span></div></div>"; 
}
