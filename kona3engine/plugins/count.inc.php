<?php

function kona3plugins_count_execute($args) {
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
  return "<span data-id='$id'>{$html}<span class='memo'>({$s})</span></span>"; 
}


