<?php
require_once __DIR__ . '/test_common.inc.php';

$saved_server_proxy = $_SERVER;
$saved_conf_proxy = $kona3conf;
$saved_get_proxy = $_GET;
$_SERVER = [
    'REQUEST_URI' => '/repos/konawiki3/index.php?FrontPage',
    'SCRIPT_NAME' => '/repos/konawiki3/index.php',
    'HTTP_HOST' => 'wiki.example.test',
    'REQUEST_SCHEME' => 'http',
];
foreach ([
    [[], 'http'],
    [['HTTPS' => 'on'], 'https'],
    [['REQUEST_SCHEME' => 'https'], 'https'],
    [['HTTP_X_FORWARDED_PROTO' => 'https'], 'https'],
    [['HTTP_X_FORWARDED_PROTO' => 'HTTPS'], 'https'],
    [['HTTP_X_FORWARDED_PROTO' => 'https,http'], 'http'],
    [['HTTPS' => 'on', 'HTTP_X_FORWARDED_PROTO' => 'http'], 'https'],
] as [$extra, $expected]) {
    $_SERVER = array_merge(array_intersect_key($_SERVER, array_flip(['REQUEST_URI', 'SCRIPT_NAME', 'HTTP_HOST'])), ['REQUEST_SCHEME' => 'http'], $extra);
    $kona3conf = ['wiki_private' => false, 'FrontPage' => 'FrontPage'];
    kona3conf_setURI();
    kona3lib_parseURI();
    test_eq(__LINE__, $kona3conf['url.index'], "$expected://wiki.example.test/repos/konawiki3/index.php", 'index URL scheme');
    test_eq(__LINE__, $kona3conf['baseurl'], "$expected://wiki.example.test/repos/konawiki3", 'base URL scheme');
    test_eq(__LINE__, kona3remember_cookie_secure(), $expected === 'https', 'remember cookie secure');
}
$_SERVER = $saved_server_proxy;
$kona3conf = $saved_conf_proxy;
$_GET = $saved_get_proxy;
