<?php

function nako3_template($tpl_file, $values) {
  global $nako3;
  $file = dirname(__FILE__).'/'.$tpl_file;
  if (!file_exists($file)) {
    return "<!-- #nako3.error file not found: $tpl_file -->";
  }
  $template = file_get_contents($file);
  $html = preg_replace_callback(
    '#\{\{\s*([a-zA-Z0-9_\:]+)\s*\}\}#',
    function ($m) use ($values) {
      $key = trim($m[1]);
      $enc = TRUE;
      // option
      if (substr($key, 0, 4) == 'raw:') {
        $enc = FALSE;
        $key = trim(substr($key, 4));
      }
      // encode
      if (isset($values[$key])) {
        $val = $values[$key];
        if ($enc) {
          $val = htmlspecialchars($val);
        }
      } else {
        $val = "{{$key}}";
      }
      return $val;
    },
    $template);
  return $html;
}


