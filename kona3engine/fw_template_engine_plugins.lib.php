<?php
// --- filters ---
// escape value
function t_echo($v) {
  $v = htmlspecialchars($v);
  echo $v;
}
function t_multiline($v) {
  $v = htmlspecialchars($v);
  $v = preg_replace('#(\r\n|\n|\r)#s', '<br>', $v);
  echo $v;
}
// raw
function t_raw($v) {
  echo $v;
}
function t_safe($v) {
  echo $v;
}
// date
function t_date($v) {
  echo date('Y年m月d日', $v);
}
function t_datetime($v) {
  echo date('Y/m/d H:i', $v);
}
function t_lang($msg) {
  echo lang($msg);
}


// --- {{{e:xxx}}} ---
function echo_options($sel_value, $labels, $values) {
  $cnt = count($labels);
  for ($i = 0; $i < $cnt; $i++) {
    $label = $labels[$i];
    $value = $values[$i];
    $selected = '';
    if ($value == $sel_value) {
      $selected = ' selected';
    }
    echo "<option value=\"$value\"$selected>$label</option>\n";
  }
}



