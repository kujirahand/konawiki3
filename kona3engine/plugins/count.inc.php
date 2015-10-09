<?php

function kona3plugins_count_execute($args) {
  $text = array_shift($args);
  $len  = strlen($text);
  $mlen = mb_strlen($text);
  $html = kona3text2html($text,ENT_QUOTES,"utf-8");
  $s = "{$len}B";
  if ($mlen != $len) $s .= ",{$mlen}字";
  return "<span>{$html}<span class='memo'>({$s})</span></span>"; 
}


