<?php
/** テキストエリアを表示する
 * - [書式] {{{#textarea(引数) テキストボックスで表示したいテキスト }}}
 * - [引数]
 * -- id: textareaにidを指定
 * -- class: textareaにclassを指定
 * -- rows: 行数を指定する
 * -- cols: 列数を指定する
 * -- bgcolor: 背景色を指定
 */
function kona3plugins_textarea_execute($args) {
  global $kona3conf;
  $plugin_id = empty($kona3conf['plugins_textarea_id']) 
    ? 1 : $kona3conf['plugins_textarea_id']+ 1;
  $kona3conf['plugins_textarea_id'] = $plugin_id;
  // check arguments
  $text = array_shift($args);
  $page = $kona3conf['page'];
  $id = 'id="'.bin2hex($page)."_{$plugin_id}".'"';
  $class = '';
  $rows = 5;
  $cols = 60;
  foreach ($args as $a) {
    if (!preg_match('#^(\w+?)\=(.+)$#', $s, $m)) continue;
    list($match, $key, $val) = $m;
    if ($key == "id") {
      $id = 'id="'.htmlspecialchars($val, ENT_QUOTES).'"';
      continue;
    }
    if ($key == "class") {
      $class = 'class="'.htmlspecialchars($val, ENT_QUOTES).'"';
      $type = $val;
      continue;
    }
    if ($key == "rows") {
      $rows = intval($val);
      continue;
    }
    if ($key == "cols") {
      $cols = intval($val);
      continue;
    }
  }
  if ($class != '') { 
    $class = htmlspecialchars($class, ENT_QUOTES);
    $class = "class=\"$class\"";
  }
  
  $textEnc = htmlspecialchars($text);
  $html = "<div><textarea $id $class rows='$rows' cols='$cols'>{$textEnc}</textarea></div>";
  return $html; 
}

