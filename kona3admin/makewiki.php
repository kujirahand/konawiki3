<?php
print_r($argv);
if (count($argv) < 2) {
  echo "[USAGE] makewiki.php (dir)";
  exit;
}
$dir = $argv[1];
if (!file_exists($dir)) {
  mkdir($dir);
}
$dir = realpath($dir);
$root = dirname(__DIR__);
// copy index
copy($root.'/index.php', $dir.'/index.php');
// make kona3dir.def.php
$data =<<<EOD
<?php
# konawiki3 dir info
define('KONA3_DIR_ENGINE',  '$root/kona3engine');
define('KONA3_DIR_ADMIN',   '$root/kona3admin');
define('KONA3_DIR_SKIN',    '$root/skin');
define('KONA3_DIR_DATA',    __DIR__.'/data');
define('KONA3_DIR_PRIVATE', __DIR__.'/private');
define('KONA3_DIR_CACHE',   __DIR__.'/cache');
EOD;
// save
file_put_contents($dir.'/kona3dir.def.php', $data);
// mkdir
kona_mkdir("$dir/data");
kona_mkdir("$dir/private");
kona_mkdir("$dir/cache");
echo "ok\n";

function kona_mkdir($dir) {
  if (!file_exists($dir)) {
    mkdir($dir);
  }
}





