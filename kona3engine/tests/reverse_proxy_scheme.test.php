<?php
require_once __DIR__ . '/test_common.inc.php';

$saved_server_proxy = $_SERVER;
$saved_conf_proxy = $kona3conf;
$saved_get_proxy = $_GET;
$_SERVER = [
    'REQUEST_URI' => '/repos/konawiki3/index.php?FrontPage',
    'SCRIPT_NAME' => '/repos/konawiki3/index.php',
    'HTTP_HOST' => 'wiki.example.test',
    'REMOTE_ADDR' => '127.0.0.1',
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
    $_SERVER = array_merge(array_intersect_key($_SERVER, array_flip(['REQUEST_URI', 'SCRIPT_NAME', 'HTTP_HOST'])), ['REQUEST_SCHEME' => 'http', 'REMOTE_ADDR' => '127.0.0.1'], $extra);
    $kona3conf = ['wiki_private' => false, 'FrontPage' => 'FrontPage'];
    kona3conf_setURI();
    kona3lib_parseURI();
    test_eq(__LINE__, $kona3conf['url.index'], "$expected://wiki.example.test/repos/konawiki3/index.php", 'index URL scheme');
    test_eq(__LINE__, $kona3conf['baseurl'], "$expected://wiki.example.test/repos/konawiki3", 'base URL scheme');
    test_eq(__LINE__, kona3remember_cookie_secure(), $expected === 'https', 'remember cookie secure');
}
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
unset($_SERVER['HTTPS']);
$_SERVER['REMOTE_ADDR'] = '203.0.113.5';
$kona3conf = [];
test_eq(__LINE__, kona3request_scheme(), 'http', 'untrusted proxy ignored');
$kona3conf['trusted_proxy_ips'] = '203.0.113.5, ::1';
test_eq(__LINE__, kona3request_scheme(), 'https', 'configured proxy trusted');
$kona3conf['trusted_proxy_ips'] = '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
test_eq(__LINE__, kona3request_scheme(), 'http', 'empty proxy list disables trust');
$_SERVER['REMOTE_ADDR'] = '::1';
unset($kona3conf['trusted_proxy_ips']);
test_eq(__LINE__, kona3request_scheme(), 'https', 'IPv6 loopback default');
foreach ([
    ['::ffff:127.0.0.1', '127.0.0.1', true],
    ['172.20.1.2', '172.16.0.0/12', true],
    ['172.32.0.1', '172.16.0.0/12', false],
    ['2001:db8:1234::1', '2001:db8:1200::/40', true],
    ['2001:db8:1300::1', '2001:db8:1200::/40', false],
    ['192.0.2.1', '0.0.0.0/0', true],
    ['::1', '0.0.0.0/0', false],
    ['::ffff:192.0.2.1', '::ffff:192.0.2.0/120', true],
    ['192.0.2.1', '192.0.2.0/33', false],
    ['192.0.2.1', '192.0.2.0/-1', false],
    ['192.0.2.1', 'invalid/0', false],
    ['192.0.2.1', '192.0.2.0/24/0', false],
] as [$ip, $entry, $match]) {
    test_eq(__LINE__, kona3request_ipMatches($ip, $entry), $match, "proxy address $ip matches $entry");
}
$_SERVER['REMOTE_ADDR'] = '::ffff:127.0.0.1';
test_eq(__LINE__, kona3request_scheme(), 'https', 'mapped loopback trusted by default');
$_SERVER['REMOTE_ADDR'] = '172.20.1.2';
$kona3conf['trusted_proxy_ips'] = '172.16.0.0/12';
test_eq(__LINE__, kona3request_scheme(), 'https', 'configured CIDR trusted');
$standalone = 'require ' . var_export(dirname(__DIR__) . '/kona3login.inc.php', true) . '; echo kona3remember_cookie_secure() ? "secure" : "plain";';
exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($standalone), $standalone_output, $standalone_status);
test_eq(__LINE__, [$standalone_status, implode('', $standalone_output)], [0, 'plain'], 'login can be loaded independently');
$_SERVER = $saved_server_proxy;
$kona3conf = $saved_conf_proxy;
$_GET = $saved_get_proxy;
