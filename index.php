<?php
// ----------------------------------------------------
// konawiki3 - index.php
// ----------------------------------------------------
define('KONA3_DIR_INDEX', __DIR__);

// Read Directories info
$file_kona3dir_def = __DIR__.'/kona3dir.def.php';
if (file_exists($file_kona3dir_def)) {
  require_once $file_kona3dir_def;
} else {
  define('KONA3_DIR_ENGINE',  __DIR__.'/kona3engine');
  define('KONA3_DIR_SKIN',    __DIR__.'/skin');
  define('KONA3_DIR_DATA',    __DIR__.'/data');
  define('KONA3_DIR_PRIVATE', __DIR__.'/private');
  define('KONA3_DIR_CACHE',   __DIR__.'/cache');
}

// Execute kona3engine/index.inc.php
$engine_index = KONA3_DIR_ENGINE.'/index.inc.php';
if (!file_exists($engine_index)) {
  echo '<p><a href="https://kujirahand.com/konawiki3/index.php?install%2Fkona3dir.def.php">Please check KONA3_DIR_ENGINE</a></p>'."\n";
  exit;
}
include_once $engine_index;

