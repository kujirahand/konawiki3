<?php
/** マークダウンを変換して表示
 * - [書式] #markdown(code)
 * - [引数]
 * -- code
 */
include_once dirname(__DIR__).'/kona3parser_md.inc.php';

// {{{#markdown ... }}} の書式で使う
// [options]
//   pre ... <pre>結果をタグで囲む
//   wiki ... Wiki記法でレンダリングする
function kona3plugins_markdown_execute($args) {
  $text = array_pop($args);
  $html = kona3markdown_parser_convert($text);
  return "<div class='beta'>{$html}</div>";
}
