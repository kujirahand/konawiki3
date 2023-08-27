<?php
// {{{#beta ... }}} の書式で使う
// [options]
//   pre ... <pre>結果をタグで囲む
//   wiki ... Wiki記法でレンダリングする
function kona3plugins_beta_execute($args) {
  // contents
  $text = array_pop($args);
  // options
  $is_pre = False;
  $is_wiki = False;
  foreach ($args as $opt) {
    if ($opt === 'pre') { $is_pre = TRUE; continue; }
    if ($opt === 'wiki') { $is_wiki = TRUE; continue; }
  }
  // convert to wiki
  if ($is_wiki) {
    $html = konawiki_parser_parse($text);
    return "<div class='beta'>{$html}</div>";
  }
  if ($is_pre) {
    $html = kona3text2html($text, ENT_QUOTES, "UTF-8");
    return "<pre class='beta'><div class='beta'>{$html}</div></pre>";
  }
  // convert to text
  $html = kona3text2html($text, ENT_QUOTES, "UTF-8");
  $html = str_replace("\n", "<br>", $html);
  return "<div class='beta'>{$html}</div>";
}


