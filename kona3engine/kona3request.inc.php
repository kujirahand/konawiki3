<?php
// URL and cookie schemes must agree when TLS is terminated by a trusted proxy.
function kona3lib_requestScheme()
{
    global $kona3conf;
    if ((!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ||
        (isset($_SERVER['REQUEST_SCHEME']) && strtolower($_SERVER['REQUEST_SCHEME']) === 'https')) {
        return 'https';
    }
    $proxies = $kona3conf['trusted_proxy_ips'] ?? '127.0.0.1,::1';
    $proxies = is_string($proxies) ? array_map('trim', explode(',', $proxies)) : [];
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    // Require one exact value: chains such as "https,http" need proxy normalization.
    if ($remote !== '' && in_array($remote, $proxies, true) &&
        isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO'])) === 'https') {
        return 'https';
    }
    return 'http';
}
