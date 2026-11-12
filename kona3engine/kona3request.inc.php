<?php
// Compare binary IP addresses, including non-byte-aligned IPv4/IPv6 CIDR masks.
function kona3request_ipMatches($address, $entry)
{
    $parts = explode('/', $entry);
    if (count($parts) > 2 || (count($parts) === 2 && !preg_match('/^(0|[1-9][0-9]*)$/D', $parts[1]))) return false;
    $ip = @inet_pton($address);
    $network = @inet_pton($parts[0]);
    if ($ip === false || $network === false) return false;
    $mapped = str_repeat("\0", 10) . "\xff\xff";
    if (strlen($ip) === 16 && substr($ip, 0, 12) === $mapped) $ip = substr($ip, 12);
    $prefix = count($parts) === 2 ? (int)$parts[1] : strlen($network) * 8;
    if (strlen($network) === 16 && substr($network, 0, 12) === $mapped) {
        if ($prefix < 96 || $prefix > 128) return false;
        $network = substr($network, 12);
        $prefix -= 96;
    }
    if (strlen($ip) !== strlen($network) || $prefix > strlen($network) * 8) return false;
    $bytes = intdiv($prefix, 8);
    $bits = $prefix % 8;
    if (substr($ip, 0, $bytes) !== substr($network, 0, $bytes)) return false;
    return $bits === 0 || ((ord($ip[$bytes]) ^ ord($network[$bytes])) & (0xff << (8 - $bits))) === 0;
}

// URL and cookie schemes must agree when TLS is terminated by a trusted proxy.
function kona3request_scheme()
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
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
        strtolower(trim($_SERVER['HTTP_X_FORWARDED_PROTO'])) === 'https') {
        foreach ($proxies as $proxy) {
            if (kona3request_ipMatches($remote, $proxy)) return 'https';
        }
    }
    return 'http';
}
