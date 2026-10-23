<?php

/**
 * KonaWiki3 静的Wiki出力: 全体生成 (docs/static_wiki.md 11〜14章)
 *
 * - 出力先の安全確認 (14章)
 * - 公開対象の列挙と除外 (7・11章)
 * - ビルドロックとマニフェスト (12.1・12.7章)
 * - 全ページ・アセット・添付ファイルの出力と 404.html (12.4章)
 */

require_once __DIR__ . '/kona3static.inc.php';

/** 添付ファイルの拒否リスト (14章。設定では変更できない) */
function kona3static_denyExtensions()
{
    return [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'pht', 'phar', 'phps',
        'cgi', 'pl', 'py', 'rb', 'sh', 'asp', 'aspx', 'jsp', 'shtml', 'htaccess', 'htpasswd',
        'html', 'htm', 'xhtml',
    ];
}

/** スキンと resource からコピーするアセットの拡張子 */
function kona3static_assetExtensions()
{
    return [
        'css', 'js', 'map', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot', 'json', 'txt',
    ];
}

// ---------------------------------------------------------------------------
// 14章: 出力先の安全確認
// ---------------------------------------------------------------------------

/** インデックスディレクトリ */
function kona3static_indexDir()
{
    if (defined('KONA3_DIR_INDEX')) {
        return KONA3_DIR_INDEX;
    }
    return dirname(KONA3_DIR_ENGINE);
}

/** `.` と `..` を字句的に解決した絶対パスにする */
function kona3static_absPath($path)
{
    $path = str_replace('\\', '/', $path);
    if ($path === '') {
        throw new Kona3StaticException('static_outdir is empty.');
    }
    if ($path[0] !== '/' && !preg_match('#^[A-Za-z]:/#', $path)) {
        $path = str_replace('\\', '/', kona3static_indexDir()) . '/' . $path;
    }
    $prefix = '';
    if (preg_match('#^([A-Za-z]:)(/.*)$#', $path, $m)) {
        $prefix = $m[1];
        $path = $m[2];
    }
    $res = [];
    foreach (explode('/', $path) as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..') {
            array_pop($res);
            continue;
        }
        $res[] = $seg;
    }
    return $prefix . '/' . implode('/', $res);
}

/** `$a` が `$b` と同じか、その配下か */
function kona3static_isSameOrUnder($a, $b)
{
    $b = rtrim($b, '/');
    return $a === $b || strpos($a, $b . '/') === 0;
}

/**
 * 出力先を検証して絶対パスを返す。不正なら例外
 * @param string|null $outdir 省略時は設定 `static_outdir`
 */
function kona3static_checkOutdir($outdir = null)
{
    global $kona3conf;
    if ($outdir === null) {
        $outdir = isset($kona3conf['static_outdir']) ? trim($kona3conf['static_outdir']) : '';
        if ($outdir === '') {
            $outdir = 'dist';
        }
    }
    if (strpos($outdir, "\0") !== false) {
        throw new Kona3StaticException('Invalid static_outdir.');
    }
    $abs = kona3static_absPath($outdir);
    kona3static_rejectSymlinkPath($abs);
    // 存在する場合は実パスで比較する
    $real = file_exists($abs) ? realpath($abs) : false;
    if ($real !== false) {
        $abs = str_replace('\\', '/', $real);
    }
    $index = kona3static_indexDir();
    $protected = [
        KONA3_DIR_DATA, KONA3_DIR_PRIVATE, KONA3_DIR_CACHE, KONA3_DIR_ENGINE, KONA3_DIR_SKIN,
    ];
    foreach ($protected as $p) {
        $pp = realpath($p);
        $pp = str_replace('\\', '/', $pp !== false ? $pp : kona3static_absPath($p));
        if (kona3static_isSameOrUnder($abs, $pp) || kona3static_isSameOrUnder($pp, $abs)) {
            throw new Kona3StaticException("static_outdir overlaps a system directory: $outdir");
        }
    }
    $ip = realpath($index);
    $ip = str_replace('\\', '/', $ip !== false ? $ip : kona3static_absPath($index));
    // インデックスディレクトリそのもの、またはその親は拒否 (配下は許可)
    if ($abs === rtrim($ip, '/') || kona3static_isSameOrUnder($ip, $abs)) {
        throw new Kona3StaticException("static_outdir must not be the index directory or its parent: $outdir");
    }
    return $abs;
}

// ---------------------------------------------------------------------------
// 12.7: ロックとマニフェスト
// ---------------------------------------------------------------------------

function kona3static_manifestFile()
{
    return KONA3_DIR_CACHE . '/static_manifest.json';
}

/**
 * ビルドロックを取得する。取れなければ例外
 * @return resource
 */
function kona3static_lock()
{
    $fp = @fopen(KONA3_DIR_CACHE . '/static_build.lock', 'c');
    if ($fp === false) {
        throw new Kona3StaticException('Cannot open the build lock file.');
    }
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        throw new Kona3StaticException('Another static build is running.');
    }
    return $fp;
}

function kona3static_unlock($fp)
{
    if (is_resource($fp)) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function kona3static_emptyManifest($outdir)
{
    return ['outdir' => $outdir, 'pages' => [], 'assets' => [], 'dirs' => [], 'built_at' => 0];
}

/** マニフェストを読む。出力先が違う、または壊れていれば空を返す */
function kona3static_loadManifest($outdir)
{
    $file = kona3static_manifestFile();
    if (!file_exists($file)) {
        return kona3static_emptyManifest($outdir);
    }
    $m = json_decode((string)@file_get_contents($file), true);
    if (!is_array($m) || !isset($m['outdir']) || $m['outdir'] !== $outdir) {
        return kona3static_emptyManifest($outdir);
    }
    foreach (['pages', 'assets', 'dirs'] as $k) {
        if (!isset($m[$k]) || !is_array($m[$k])) {
            $m[$k] = [];
        }
    }
    if (!isset($m['built_at'])) {
        $m['built_at'] = 0;
    }
    return $m;
}

/** マニフェストを一時ファイル→rename で保存する */
function kona3static_saveManifest($manifest)
{
    $file = kona3static_manifestFile();
    $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
    $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        throw new Kona3StaticException('Cannot save the manifest.');
    }
}

/** マニフェストに記録済みの出力パスか */
function kona3static_isOwned($manifest, $rel)
{
    if (isset($manifest['assets'][$rel])) {
        return true;
    }
    foreach ($manifest['pages'] as $info) {
        if (isset($info['path']) && $info['path'] === $rel) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------
// 7・11章: 公開対象の列挙
// ---------------------------------------------------------------------------

/** `data/` 内のファイル名(1階層)をWiki名の階層名にする */
function kona3static_decodeFileSegment($seg)
{
    global $kona3conf;
    if (!empty($kona3conf['enc_pagename'])) {
        return urldecode($seg);
    }
    return $seg;
}

/** 添付ファイルとして公開してよい拡張子か (14章) */
function kona3static_isAttachable($filename)
{
    global $kona3conf;
    $name = strtolower($filename);
    $parts = explode('.', $name);
    if (count($parts) < 2) {
        return false;
    }
    array_shift($parts); // 先頭はファイル名本体
    foreach ($parts as $ext) {
        if (in_array($ext, kona3static_denyExtensions(), true)) {
            return false;
        }
    }
    $ext = end($parts);
    $allow = isset($kona3conf['allow_upload_ext']) ? $kona3conf['allow_upload_ext'] : '';
    foreach (preg_split('/[;,\s]+/', strtolower($allow)) as $a) {
        if ($a !== '' && $a === $ext) {
            return true;
        }
    }
    $pattern = isset($kona3conf['image_pattern']) ? $kona3conf['image_pattern'] : '';
    if ($pattern !== '' && @preg_match('/^' . $pattern . '$/i', $ext)) {
        return true;
    }
    return false;
}

/** 個別ページの除外 (11章) */
function kona3static_isExcluded($wikiName)
{
    global $kona3conf;
    $prefixes = isset($kona3conf['static_exclude']) && is_array($kona3conf['static_exclude'])
        ? $kona3conf['static_exclude'] : ['MenuBar'];
    foreach ($prefixes as $prefix) {
        if ($prefix !== '' && strpos($wikiName, $prefix) === 0) {
            return true;
        }
    }
    $meta = function_exists('kona3db_loadPageMeta') ? kona3db_loadPageMeta($wikiName) : null;
    if (is_array($meta) && array_key_exists('static', $meta) && $meta['static'] === false) {
        return true;
    }
    return false;
}

/**
 * `data/` を走査して公開対象を集める。ページ内容は読まない
 * @return array ['pages' => [Wiki名 => 'page'|'dir'], 'files' => [data相対パス => 実パス]]
 */
function kona3static_scanData()
{
    $pages = [];
    $dirs = [];
    $files = [];
    $walk = function ($absDir, $rel) use (&$walk, &$pages, &$dirs, &$files) {
        $entries = @scandir($absDir);
        if ($entries === false) {
            return;
        }
        sort($entries, SORT_STRING);
        foreach ($entries as $e) {
            if ($e === '.' || $e === '..' || $e[0] === '.') {
                continue;
            }
            $abs = $absDir . '/' . $e;
            if (is_link($abs)) {
                continue;
            }
            $relPath = ($rel === '') ? $e : $rel . '/' . $e;
            if (is_dir($abs)) {
                $name = implode('/', array_map('kona3static_decodeFileSegment', explode('/', $relPath)));
                $dirs[$name] = true;
                $walk($abs, $relPath);
                continue;
            }
            if (!is_file($abs)) {
                continue;
            }
            if ($rel === '' && $e === (defined('KONA3_ALIAS_SHOW_JSON_NAME') ? KONA3_ALIAS_SHOW_JSON_NAME : 'alias-show.json')) {
                continue;
            }
            if (preg_match('/^(.+)\.(txt|md)$/', $relPath, $m)) {
                $name = implode('/', array_map('kona3static_decodeFileSegment', explode('/', $m[1])));
                $pages[$name] = 'page';
                continue;
            }
            if (kona3static_isAttachable($e)) {
                $files[$relPath] = $abs;
            }
        }
    };
    $walk(KONA3_DIR_DATA, '');
    foreach ($dirs as $name => $_) {
        if (!isset($pages[$name])) {
            $pages[$name] = 'dir';
        }
    }
    ksort($pages, SORT_STRING);
    ksort($files, SORT_STRING);
    return ['pages' => $pages, 'files' => $files];
}

/** スキンと resource のアセット一覧 [出力パス => 実パス] */
function kona3static_scanAssets()
{
    global $kona3conf;
    $skin = isset($kona3conf['skin']) ? $kona3conf['skin'] : 'def';
    if (preg_match('#[^a-zA-Z0-9_\-]#', $skin)) {
        $skin = 'def';
    }
    $res = [];
    $collect = function ($absDir, $outPrefix) use (&$res) {
        if (!is_dir($absDir)) {
            return;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $f) {
            if ($f->isLink() || !$f->isFile()) {
                continue;
            }
            $rel = substr($f->getPathname(), strlen($absDir) + 1);
            $rel = str_replace('\\', '/', $rel);
            if (strpos('/' . $rel, '/.') !== false) {
                continue;
            }
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            if (!in_array($ext, kona3static_assetExtensions(), true)) {
                continue;
            }
            $res[$outPrefix . $rel] = $f->getPathname();
        }
    };
    $sys = KONA3_STATIC_SYSTEM_DIR;
    $collect(KONA3_DIR_SKIN . '/' . $skin, "$sys/skin/$skin/");
    $resDir = isset($kona3conf['path_resource']) ? $kona3conf['path_resource'] : KONA3_DIR_ENGINE . '/resource';
    $collect($resDir, "$sys/resource/");
    ksort($res, SORT_STRING);
    return $res;
}

// ---------------------------------------------------------------------------
// 12.1: 書き込み (所有権の確認つき)
// ---------------------------------------------------------------------------

/**
 * 1ファイルを出力先に書く。マニフェストに無い既存ファイルは上書きしない (14章)
 * 新しく作ったディレクトリは `$state['dirs']` に記録する
 *
 * @param array $state ['outdir', 'old' => 旧マニフェスト, 'dirs' => [], 'adopt' => bool]
 * @param bool $sameContentOk 内容が同一の既存ファイルを自分のものとして扱う(添付・アセット)
 */
function kona3static_publish(&$state, $rel, $content, $sameContentOk)
{
    $outdir = $state['outdir'];
    $target = $outdir . '/' . $rel;
    if (is_link($target)) {
        throw new Kona3StaticException("Refusing to write to a symbolic link: $rel");
    }
    if (is_dir($target)) {
        throw new Kona3StaticException("Target is a directory: $rel");
    }
    if (file_exists($target)) {
        $owned = kona3static_isOwned($state['old'], $rel);
        if (!$owned) {
            $same = $sameContentOk && @file_get_contents($target) === $content;
            if (!$same && empty($state['adopt'])) {
                throw new Kona3StaticException("Refusing to overwrite a file not created by static output: $rel");
            }
        }
    }
    // 新しく作るディレクトリを記録する
    $segments = explode('/', $rel);
    array_pop($segments);
    $cur = '';
    foreach ($segments as $seg) {
        $cur = ($cur === '') ? $seg : "$cur/$seg";
        $abs = $outdir . '/' . $cur;
        if (file_exists($abs) && !is_dir($abs)) {
            throw new Kona3StaticException("A file exists where a directory is needed: $cur");
        }
        if (!is_dir($abs)) {
            $state['dirs'][$cur] = true;
        }
    }
    kona3static_writeFile($outdir, $rel, $content);
}

/** 空になった記録済みディレクトリを (深い順に) 削除する */
function kona3static_removeEmptyDirs($outdir, &$dirs)
{
    $names = array_keys($dirs);
    usort($names, function ($a, $b) {
        return substr_count($b, '/') - substr_count($a, '/');
    });
    foreach ($names as $d) {
        $abs = $outdir . '/' . $d;
        if (is_link($abs)) {
            unset($dirs[$d]);
            continue;
        }
        if (!is_dir($abs)) {
            unset($dirs[$d]);
            continue;
        }
        $left = array_diff((array)scandir($abs), ['.', '..']);
        if (count($left) === 0 && @rmdir($abs)) {
            unset($dirs[$d]);
        }
    }
}

// ---------------------------------------------------------------------------
// 描画
// ---------------------------------------------------------------------------

/** エイリアスのリダイレクト用HTML (7章) */
function kona3static_redirectHtml($url)
{
    $u = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    return "<!DOCTYPE html>\n<html><head><meta charset=\"UTF-8\">" .
        "<meta http-equiv=\"refresh\" content=\"0; url={$u}\">" .
        "<link rel=\"canonical\" href=\"{$u}\">" .
        "<title>Redirect</title></head><body><a href=\"{$u}\">{$u}</a></body></html>\n";
}

/** 404.html の内容 */
function kona3static_notFoundHtml()
{
    global $kona3conf;
    $title = htmlspecialchars(isset($kona3conf['wiki_title']) ? $kona3conf['wiki_title'] : 'KonaWiki3', ENT_QUOTES, 'UTF-8');
    $home = htmlspecialchars(kona3static_baseUrl(), ENT_QUOTES, 'UTF-8');
    return "<!DOCTYPE html>\n<html><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">" .
        "<title>404 Not Found - {$title}</title></head><body>" .
        "<h1>404 Not Found</h1><p><a href=\"{$home}\">{$title}</a></p></body></html>\n";
}

/**
 * ページを描画する。エイリアスはリダイレクトHTMLにする
 * @return string HTML
 */
function kona3static_renderForBuild($wikiName)
{
    try {
        $html = kona3static_renderPage($wikiName);
    } catch (Kona3StaticRedirectException $e) {
        return kona3static_redirectHtml($e->target);
    }
    $dynamic = kona3static_findDynamicLinks($html);
    if (count($dynamic) > 0) {
        throw new Kona3StaticException(
            "Dynamic links remain in `$wikiName`: " . implode(', ', array_slice($dynamic, 0, 5))
        );
    }
    return $html;
}

// ---------------------------------------------------------------------------
// 12.4: 全体生成
// ---------------------------------------------------------------------------

/**
 * 全ページ・アセット・添付ファイルを出力する
 *
 * @param array $options 'outdir' => 出力先(省略時は設定), 'adopt' => bool
 * @return array ['pages' => 件数, 'failed' => 件数, 'assets' => 件数, 'deleted' => 件数, 'errors' => [], 'warnings' => []]
 * @throws Kona3StaticException 全体を中止するエラー (非公開Wiki、衝突、出力先の不正、実行中)
 */
function kona3static_buildAll($options = [])
{
    global $kona3conf;
    $outdirOpt = isset($options['outdir']) ? $options['outdir'] : null;
    $outdir = kona3static_checkOutdir($outdirOpt);

    // 非公開Wikiは生成せず、生成済みの出力を削除する (11章)
    if (!empty($kona3conf['wiki_private'])) {
        $n = kona3static_purge($outdir);
        throw new Kona3StaticException("Private wiki cannot be exported as static files. ($n files removed)");
    }

    if (!is_dir($outdir)) {
        if (!is_dir(dirname($outdir)) || !@mkdir($outdir, 0777)) {
            throw new Kona3StaticException("Cannot create the output directory: $outdir");
        }
    }

    $lock = kona3static_lock();
    try {
        return kona3static_buildLocked($outdir, $options);
    } finally {
        kona3static_unlock($lock);
    }
}

function kona3static_buildLocked($outdir, $options)
{
    global $kona3conf;
    $old = kona3static_loadManifest($outdir);
    $result = ['pages' => 0, 'failed' => 0, 'assets' => 0, 'deleted' => 0, 'errors' => [], 'warnings' => []];

    // 公開対象を列挙し、物理パスを計算して衝突を検出する (何も書く前に中止できる)
    $scan = kona3static_scanData();
    $plan = [];      // Wiki名 => 出力パス
    $keys = [];      // 衝突キー => [名前...]
    $addKey = function ($rel, $label) use (&$keys) {
        $k = kona3static_collisionKey($rel);
        $keys[$k][] = $label;
    };
    foreach ($scan['pages'] as $name => $kind) {
        if (kona3static_isExcluded($name)) {
            continue;
        }
        try {
            $rel = kona3getStaticFile($name);
        } catch (Kona3StaticException $e) {
            $result['errors'][] = "$name: " . $e->getMessage();
            $result['failed']++;
            continue;
        }
        $plan[$name] = $rel;
        $addKey($rel, $name);
    }
    $attachPlan = [];
    foreach ($scan['files'] as $dataRel => $abs) {
        try {
            $rel = kona3static_attachFile($dataRel);
        } catch (Kona3StaticException $e) {
            $result['errors'][] = "$dataRel: " . $e->getMessage();
            continue;
        }
        $attachPlan[$rel] = $abs;
        $addKey($rel, $dataRel);
    }
    $assetPlan = kona3static_scanAssets();
    foreach ($assetPlan as $rel => $_) {
        $addKey($rel, $rel);
    }
    $addKey('404.html', '404.html');
    foreach ($keys as $k => $labels) {
        if (count($labels) > 1) {
            throw new Kona3StaticException('Output path collision: ' . implode(' / ', $labels));
        }
    }

    $state = ['outdir' => $outdir, 'old' => $old, 'dirs' => $old['dirs'], 'adopt' => !empty($options['adopt'])];
    $new = kona3static_emptyManifest($outdir);

    // ページ
    foreach ($plan as $name => $rel) {
        try {
            $html = kona3static_renderForBuild($name);
            kona3static_publish($state, $rel, $html, false);
            $new['pages'][$name] = ['path' => $rel, 'state' => 'ok'];
            $result['pages']++;
        } catch (Throwable $e) {
            $result['errors'][] = "$name: " . $e->getMessage();
            $result['failed']++;
            // 旧出力は残し、失敗として記録する
            if (isset($old['pages'][$name])) {
                $new['pages'][$name] = ['path' => $old['pages'][$name]['path'], 'state' => 'failed'];
            }
        }
    }

    // アセット・添付ファイル・404.html
    $assets = [];
    foreach ($assetPlan + $attachPlan as $rel => $abs) {
        $content = @file_get_contents($abs);
        if ($content === false) {
            $result['errors'][] = "$rel: cannot read the source file.";
            continue;
        }
        $assets[$rel] = $content;
    }
    $assets['404.html'] = kona3static_notFoundHtml();
    foreach ($assets as $rel => $content) {
        try {
            kona3static_publish($state, $rel, $content, true);
            $new['assets'][$rel] = true;
            $result['assets']++;
        } catch (Throwable $e) {
            $result['errors'][] = "$rel: " . $e->getMessage();
            if (isset($old['assets'][$rel])) {
                $new['assets'][$rel] = true;
            }
        }
    }

    // 今回の対象から外れた旧出力を削除する (マニフェストに記録済みのものだけ)
    $keep = [];
    foreach ($new['pages'] as $info) {
        $keep[$info['path']] = true;
    }
    foreach ($new['assets'] as $rel => $_) {
        $keep[$rel] = true;
    }
    $stale = [];
    foreach ($old['pages'] as $info) {
        $stale[] = $info['path'];
    }
    foreach ($old['assets'] as $rel => $_) {
        $stale[] = $rel;
    }
    foreach ($stale as $rel) {
        if (isset($keep[$rel])) {
            continue;
        }
        $abs = $outdir . '/' . $rel;
        if (is_file($abs) && !is_link($abs) && @unlink($abs)) {
            $result['deleted']++;
        }
    }
    kona3static_removeEmptyDirs($outdir, $state['dirs']);

    $new['dirs'] = $state['dirs'];
    $new['built_at'] = ($result['failed'] === 0 && count($result['errors']) === 0) ? time() : $old['built_at'];
    kona3static_saveManifest($new);
    return $result;
}

// ---------------------------------------------------------------------------
// 12.8: 出力の削除
// ---------------------------------------------------------------------------

/**
 * マニフェストに記録した出力と、記録済みの空ディレクトリだけを削除する
 * @return int 削除したファイル数
 */
function kona3static_purge($outdir = null)
{
    $outdir = kona3static_checkOutdir($outdir);
    $lock = kona3static_lock();
    try {
        $m = kona3static_loadManifest($outdir);
        $n = 0;
        $paths = [];
        foreach ($m['pages'] as $info) {
            $paths[] = $info['path'];
        }
        foreach ($m['assets'] as $rel => $_) {
            $paths[] = $rel;
        }
        foreach ($paths as $rel) {
            $abs = $outdir . '/' . $rel;
            if (is_file($abs) && !is_link($abs) && @unlink($abs)) {
                $n++;
            }
        }
        kona3static_removeEmptyDirs($outdir, $m['dirs']);
        $empty = kona3static_emptyManifest($outdir);
        $empty['dirs'] = $m['dirs'];
        kona3static_saveManifest($empty);
        return $n;
    } finally {
        kona3static_unlock($lock);
    }
}
