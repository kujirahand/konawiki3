<?php
/** countbox plugin

[USAGE] with ID
{{{#count(id=xxx)
abcdefg
abcdefg
}}}

[USAGE] no ID
{{{#count
abcdefg
abcdefg
}}}

 */
function kona3plugins_countbox_execute($args) {
  $text = "";
  $id = "null";
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
  $s = "{$len}B";
  if ($mlen != $len) $s .= ",{$mlen}字";
  return "<div data-id='$id' class='column'><div>{$html}</div><div style='text-align:right'><span class='memo'>({$s})</span></div></div>"; 
}


