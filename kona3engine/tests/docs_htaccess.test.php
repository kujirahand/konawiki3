<?php
require_once __DIR__ . '/test_common.inc.php';

// docs フォルダはファイルの有無でバージョンが推測できるため、直接アクセスを拒否する (#327)
$root = dirname(__DIR__, 2);
$htaccess = "$root/docs/.htaccess";
test_eq(__LINE__, file_exists($htaccess), true, "docs/.htaccess が存在する");
$body = file_exists($htaccess) ? file_get_contents($htaccess) : '';
test_eq(__LINE__, strpos($body, 'Require all denied') !== false, true, "Apache 2.4 で全拒否");
test_eq(__LINE__, strpos($body, 'Deny from all') !== false, true, "Apache 2.2 で全拒否");
