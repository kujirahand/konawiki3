<?php
/** 折り返し可能なテキストの囲み枠
 * - [書式] {{{#beta ... }}} の書式で使う
 * - [引数]
 * -- pre ... <pre>結果をタグで囲む
 * -- wiki ... Wiki記法でレンダリングする
 */
function kona3plugins_beta_execute($args) {
  // contents
  $text = array_pop($args);
  // options
  $is_pre = False;
  $is_wiki = False;
  $is_markdown = False;
  foreach ($args as $opt) {
    if ($opt === 'pre') { $is_pre = TRUE; continue; }
    if ($opt === 'wiki') { $is_wiki = TRUE; continue; }
    if ($opt === 'md' || $opt === 'markdown') { $is_markdown = TRUE; continue; }
  }
  // convert to wiki
  if ($is_wiki) {
    $html = konawiki_parser_parse($text);
    return "<div class='beta'>{$html}</div>";
  }
  if ($is_markdown) {
    include_once dirname(__DIR__) . '/kona3parser_md.inc.php';
    if (strpos($text, "\\[") !== FALSE || strpos($text, '\\(')) {
      // include mathjax
      include_once __DIR__ . '/mathjax.inc.php';
      kona3plugins_mathjax_include();
      // replace
      $text = str_replace("\\[", "$$$", $text);
      $text = str_replace("\\]", "$$$", $text);
      $text = str_replace("\\(", "$$$", $text);
      $text = str_replace("\\)", "$$$", $text);
      $text = str_replace("\\", "\\\\", $text); // markdown escape!! 重要 (`\times`が`times`になってしまう)
    }
    $html = kona3markdown_parser_convert($text);
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


