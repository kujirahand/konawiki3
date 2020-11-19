<?php
// data format loader and saver

define('JSONPHP_HEAD', "<?php \$jsonphp___=<<<'_____END_OF_JSONPHP__'\n");
define('JSONPHP_FOOT', "\n_____END_OF_JSONPHP__;exit;\n");
function jsonphp_load($file, $def = null) {
  if (!file_exists($file)) {
    return $def;
  }
  $txt = trim(file_get_contents($file));
  // chomp head
  $head = substr($txt, 0, strlen(JSONPHP_HEAD));
  if ($head != JSONPHP_HEAD) { return $def; }
  $txt = substr($txt, strlen(JSONPHP_HEAD));
  $txt = substr($txt, 0, strlen($txt) - strlen(JSONPHP_FOOT) + 1);
  return json_decode($txt, TRUE);
}
function jsonphp_save($file, $value) {
  $json = json_encode($value,
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  file_put_contents($file,
    JSONPHP_HEAD.$json.JSONPHP_FOOT);
}
