<?php

/**
 * KonaWiki3 静的Wiki出力: 全体生成 (docs/static_wiki.md 11〜14章)
 *
 * - 出力先の安全確認 (14章)
 * - 公開対象の列挙と除外 (7・11章)
 * - ビルドロックとマニフェスト (12.1・12.7章)
 * - 全ページ・アセット・添付ファイルの出力と 404.html (12.4章)
 * - 差分生成、編集時の1ページ生成、pending、sitemap.xml (12.2・12.3・12.7章)
 */

require_once __DIR__ . '/kona3static.inc.php';
require_once __DIR__ . '/kona3static_index.inc.php';

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
    // 大文字小文字を区別しない環境 (Windows, macOS) でも保護できるよう、常に小文字で比較する
    $a = mb_strtolower($a, 'UTF-8');
    $b = mb_strtolower(rtrim($b, '/'), 'UTF-8');
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
    $ipl = mb_strtolower(rtrim($ip, '/'), 'UTF-8');
    if (mb_strtolower($abs, 'UTF-8') === $ipl || kona3static_isSameOrUnder($ip, $abs)) {
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
 * @param float $waitSec 取得できるまで待つ秒数 (0 なら待たない)
 * @return resource
 */
function kona3static_lock($waitSec = 0)
{
    $fp = @fopen(KONA3_DIR_CACHE . '/static_build.lock', 'c');
    if ($fp === false) {
        throw new Kona3StaticException('Cannot open the build lock file.');
    }
    $limit = microtime(true) + $waitSec;
    while (!flock($fp, LOCK_EX | LOCK_NB)) {
        if (microtime(true) >= $limit) {
            fclose($fp);
            throw new Kona3StaticException('Another static build is running.');
        }
        usleep(100000);
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
    return ['outdir' => $outdir, 'pages' => [], 'assets' => [], 'dirs' => [], 'built_at' => 0, 'inputs' => []];
}

/** マニフェストが記録している出力先 (無ければ null) */
function kona3static_recordedOutdir()
{
    $file = kona3static_manifestFile();
    if (!file_exists($file)) {
        return null;
    }
    $m = json_decode((string)@file_get_contents($file), true);
    if (is_array($m) && isset($m['outdir']) && is_string($m['outdir']) && $m['outdir'] !== '') {
        return $m['outdir'];
    }
    return null;
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
    if (!isset($m['inputs']) || !is_array($m['inputs'])) {
        $m['inputs'] = [];
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

/**
 * 出力先からの相対パスとして安全か (絶対パス・`..`・空の階層を含まない)
 * マニフェストの値は改ざんや破損があり得るため、削除の前にも通す
 */
function kona3static_isSafeRelPath($rel)
{
    if (!is_string($rel) || $rel === '' || $rel[0] === '/' || strpos($rel, "\0") !== false
        || strpos($rel, '\\') !== false) {
        return false;
    }
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') {
            return false;
        }
    }
    return true;
}

/**
 * 出力先の配下にある通常ファイルを安全に削除する
 * 相対パスが不正、または途中の階層にシンボリックリンクがあるものは削除しない
 * @return bool 削除した
 */
function kona3static_safeUnlink($outdir, $rel)
{
    if (!kona3static_isSafeRelPath($rel)) {
        return false;
    }
    $cur = $outdir;
    $segments = explode('/', $rel);
    $last = array_pop($segments);
    foreach ($segments as $seg) {
        $cur .= '/' . $seg;
        if (is_link($cur) || !is_dir($cur)) {
            return false;
        }
    }
    $abs = $cur . '/' . $last;
    return is_file($abs) && !is_link($abs) && @unlink($abs);
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
 * @return array ['pages' => [Wiki名 => 'page'|'dir'|'alias'], 'files' => [data相対パス => 実パス], 'sources' => [Wiki名 => [ソースの実パス]]]
 */
function kona3static_scanData()
{
    require_once __DIR__ . '/action/show.inc.php';
    $pages = [];
    $dirs = [];
    $files = [];
    $sources = [];
    $walk = function ($absDir, $rel) use (&$walk, &$pages, &$dirs, &$files, &$sources) {
        $entries = @scandir($absDir);
        if ($entries === false) {
            // 不完全な一覧で生成すると、既存の出力を古いものとして消してしまうため中止する
            throw new Kona3StaticException("Cannot read the directory: $absDir");
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
                $sources[$name][] = $abs;
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
    // data/alias-show.json で定義されたエイリアスもリダイレクトページにする (7章)
    $aliasFile = KONA3_DIR_DATA . '/' . KONA3_ALIAS_SHOW_JSON_NAME;
    if (is_file($aliasFile) && !is_link($aliasFile)) {
        foreach (kona3go_loadAliasList($aliasFile) as $alias => $_target) {
            if ($alias === KONA3_ALIAS_RE_RULE_KEY || isset($pages[$alias])) {
                continue;
            }
            $pages[$alias] = 'alias';
        }
    }
    ksort($pages, SORT_STRING);
    ksort($files, SORT_STRING);
    return ['pages' => $pages, 'files' => $files, 'sources' => $sources];
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
    // def 以外のスキンは def のCSSを土台に使うため、def も出力する
    if ($skin !== 'def') {
        $collect(KONA3_DIR_SKIN . '/def', "$sys/skin/def/");
    }
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
    if (!kona3static_isSafeRelPath($rel)) {
        throw new Kona3StaticException("Invalid output path: $rel");
    }
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
        if (is_link($abs)) {
            throw new Kona3StaticException("Refusing to write through a symbolic link: $cur");
        }
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
        if (!kona3static_isSafeRelPath($d)) {
            unset($dirs[$d]);
            continue;
        }
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
// 12.3: 差分生成の補助
// ---------------------------------------------------------------------------

/** 公開URLのベースが絶対URLか (sitemap.xml を生成できるか) */
function kona3static_isAbsoluteBase()
{
    return preg_match('#^https?://#i', kona3static_baseUrl()) === 1;
}

/**
 * ページ集合の変化で内容が変わるプラグイン (10章 B 分類、ls、include) を含むか
 * 差分生成では内容を読まないため、描画時に判定してマニフェストへ記録する
 */
function kona3static_isVolatileSource($paths)
{
    foreach ($paths as $p) {
        $t = @file_get_contents($p);
        if ($t !== false && preg_match('/(?:#|♪|!!)\s*(?:recent_meta|recent|tags|tag|taglist|filelist|countpages|ls|include)\b/u', $t)) {
            return true;
        }
    }
    return false;
}

/** ソースとメタデータのうち新しい方の mtime */
function kona3static_sourceMtime($wikiName, $paths)
{
    $m = 0;
    foreach ($paths as $p) {
        $m = max($m, (int)@filemtime($p));
    }
    $meta = kona3db_getPageMetaFile($wikiName);
    if (is_string($meta) && is_file($meta)) {
        $m = max($m, (int)@filemtime($meta));
    }
    return $m;
}

/** 「全体の入力」の記録 (設定・MenuBar・スキンとresource・エンジンのバージョン) */
function kona3static_currentInputs($scan, $assetPlan)
{
    global $kona3conf;
    $menu = isset($scan['sources']['MenuBar']) ? kona3static_sourceMtime('MenuBar', $scan['sources']['MenuBar']) : 0;
    $assets = 0;
    foreach ($assetPlan as $abs) {
        $assets = max($assets, (int)@filemtime($abs));
    }
    return [
        'version' => defined('KONAWIKI_VERSION') ? KONAWIKI_VERSION : '',
        'skin' => isset($kona3conf['skin']) ? $kona3conf['skin'] : 'def',
        'conf' => (int)@filemtime(KONA3_DIR_PRIVATE . '/kona3conf.json.php'),
        'menubar' => $menu,
        'assets' => $assets,
    ];
}

/** sitemap.xml の内容 (13章 `static_base_url` が絶対URLのときのみ) */
function kona3static_sitemapXml($entries)
{
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
        "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($entries as $url => $mtime) {
        $u = htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml .= "  <url><loc>{$u}</loc>";
        if ($mtime > 0) {
            $xml .= '<lastmod>' . gmdate('Y-m-d\TH:i:s\Z', $mtime) . '</lastmod>';
        }
        $xml .= "</url>\n";
    }
    return $xml . "</urlset>\n";
}

// ---------------------------------------------------------------------------
// 12.7: pending (ビルドロックが取れなかった編集の記録)
// ---------------------------------------------------------------------------

function kona3static_pendingFile()
{
    return KONA3_DIR_CACHE . '/static_pending.json';
}

/** @return resource */
function kona3static_pendingLock()
{
    $fp = @fopen(KONA3_DIR_CACHE . '/static_pending.lock', 'c');
    if ($fp === false) {
        throw new Kona3StaticException('Cannot open the pending lock file.');
    }
    flock($fp, LOCK_EX);
    return $fp;
}

/** 生成待ちのページ名を追記する (編集処理側) */
function kona3static_addPending($wikiName)
{
    $fp = kona3static_pendingLock();
    try {
        $line = json_encode($wikiName, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (@file_put_contents(kona3static_pendingFile(), $line, FILE_APPEND) === false) {
            throw new Kona3StaticException('Cannot write the pending file.');
        }
    } finally {
        kona3static_unlock($fp);
    }
}

/** 生成待ちの記録があるか */
function kona3static_hasPending()
{
    $g = glob(KONA3_DIR_CACHE . '/static_pending.*json');
    return file_exists(kona3static_pendingFile()) || (is_array($g) && count($g) > 0);
}

/**
 * pending を取り込む (ビルドロックを保持した状態で呼ぶ)
 * `static_pending.json` を `static_pending.<ID>.json` へ rename するのが原子的な取り込み。
 * 前回の異常終了で残ったファイルも含めて返す
 * @return string[] 取り込んだファイルのパス
 */
function kona3static_takePending()
{
    $fp = kona3static_pendingLock();
    try {
        $main = kona3static_pendingFile();
        if (file_exists($main)) {
            $taken = KONA3_DIR_CACHE . '/static_pending.' . bin2hex(random_bytes(4)) . '.json';
            @rename($main, $taken);
        }
        $files = glob(KONA3_DIR_CACHE . '/static_pending.*.json');
        return is_array($files) ? $files : [];
    } finally {
        kona3static_unlock($fp);
    }
}

/** 取り込んだファイルからページ名の一覧を作る */
function kona3static_readPending($files)
{
    $names = [];
    foreach ($files as $f) {
        foreach ((array)@file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $n = json_decode($line, true);
            if (is_string($n) && $n !== '') {
                $names[$n] = true;
            }
        }
    }
    return array_keys($names);
}

/** エラーを `cache/static_error.log` に記録する (12.2) */
function kona3static_logError($msg)
{
    $line = date('Y-m-d H:i:s') . "\t" . str_replace(["\r", "\n"], ' ', $msg) . "\n";
    @file_put_contents(KONA3_DIR_CACHE . '/static_error.log', $line, FILE_APPEND);
}

// ---------------------------------------------------------------------------
// 12.4: 全体生成
// ---------------------------------------------------------------------------

/** 出力先が無ければ作る (親が存在する場合のみ) */
function kona3static_prepareOutdir($outdir)
{
    if (!is_dir($outdir)) {
        if (!is_dir(dirname($outdir)) || !@mkdir($outdir, 0777)) {
            throw new Kona3StaticException("Cannot create the output directory: $outdir");
        }
    }
}

/**
 * 全ページ・アセット・添付ファイルを出力する
 *
 * @param array $options 'outdir' => 出力先(省略時は設定), 'adopt' => bool, 'full' => bool (差分判定なしで全ページを再生成)
 * @return array ['pages' => 生成数, 'skipped' => 差分判定で省いた数, 'failed' => 件数, 'assets' => 件数, 'deleted' => 件数, 'errors' => [], 'warnings' => []]
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

    kona3static_prepareOutdir($outdir);

    $lock = kona3static_lock();
    try {
        return kona3static_buildLocked($outdir, $options);
    } finally {
        kona3static_unlock($lock);
    }
}

function kona3static_buildLocked($outdir, $options, $depth = 0)
{
    global $kona3conf;
    $old = kona3static_loadManifest($outdir);
    $result = ['pages' => 0, 'skipped' => 0, 'failed' => 0, 'assets' => 0, 'deleted' => 0, 'errors' => [], 'warnings' => []];
    $full = !empty($options['full']);

    // pending を取り込む (12.7)。取り込んだページは mtime に関わらず再生成する
    $pendingFiles = kona3static_takePending();
    $pendingSet = array_flip(kona3static_readPending($pendingFiles));

    // 公開対象を列挙し、物理パスを計算して衝突を検出する (何も書く前に中止できる)
    $scan = kona3static_scanData();
    $plan = [];      // Wiki名 => 出力パス
    $keys = [];      // 衝突キー => [名前...]
    $addKey = function ($rel, $label) use (&$keys) {
        $k = kona3static_collisionKey($rel);
        $keys[$k][] = $label;
    };
    foreach ($scan['pages'] as $name => $kind) {
        $name = (string)$name; // 数字だけのページ名は配列キーが int になる
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
    $sitemap = kona3static_isAbsoluteBase();
    if ($sitemap) {
        $addKey('sitemap.xml', 'sitemap.xml');
    }
    // 検索・タグ一覧 (20章)。`_kona3/` 以下で、ページとは衝突しない
    $indexFiles = kona3static_buildIndexFiles($plan, $scan);
    foreach ($indexFiles['warnings'] as $w) {
        $result['warnings'][] = $w;
    }
    foreach ($indexFiles['files'] as $rel => $_) {
        $addKey($rel, $rel);
    }
    foreach ($keys as $k => $labels) {
        if (count($labels) > 1) {
            throw new Kona3StaticException('Output path collision: ' . implode(' / ', $labels));
        }
    }

    // 全体の入力が変わっていれば全ページを再生成する。先に全ページを stale にして保存し、
    // 途中で失敗・中断しても次回の生成で再試行されるようにする (12.3)
    $inputs = kona3static_currentInputs($scan, $assetPlan);
    $inputsChanged = $full || json_encode($inputs) !== json_encode($old['inputs']);
    if ($inputsChanged && count($old['pages']) > 0) {
        foreach ($old['pages'] as $n => $_) {
            $old['pages'][$n]['state'] = 'stale';
        }
        $staleManifest = $old;
        $staleManifest['inputs'] = $old['inputs'];
        kona3static_saveManifest($staleManifest);
    }

    $state = ['outdir' => $outdir, 'old' => $old, 'dirs' => $old['dirs'], 'adopt' => !empty($options['adopt'])];
    $new = kona3static_emptyManifest($outdir);
    $new['built_at'] = $old['built_at'];
    $new['inputs'] = $old['inputs'];

    // ページ
    $sitemapEntries = [];
    foreach ($plan as $name => $rel) {
        $name = (string)$name;
        $info = isset($old['pages'][$name]) ? $old['pages'][$name] : null;
        $paths = isset($scan['sources'][$name]) ? $scan['sources'][$name] : [];
        $mtime = ($scan['pages'][$name] === 'page') ? kona3static_sourceMtime($name, $paths) : 0;
        $target = $outdir . '/' . $rel;
        $need = $inputsChanged || $info === null || isset($pendingSet[$name])
            || (isset($info['state']) ? $info['state'] : 'ok') !== 'ok'
            || !empty($info['volatile']) || $scan['pages'][$name] !== 'page'
            || $info['path'] !== $rel || !is_file($target) || is_link($target)
            || $mtime > (int)@filemtime($target);
        if (!$need) {
            $new['pages'][$name] = $info;
            $result['skipped']++;
            if ($sitemap) {
                $sitemapEntries[kona3getStaticURL($name)] = $mtime;
            }
            continue;
        }
        try {
            $html = kona3static_renderForBuild($name);
            kona3static_publish($state, $rel, $html, false);
            $new['pages'][$name] = ['path' => $rel, 'state' => 'ok', 'volatile' => kona3static_isVolatileSource($paths)];
            $result['pages']++;
            if ($sitemap && $scan['pages'][$name] === 'page') {
                $sitemapEntries[kona3getStaticURL($name)] = $mtime;
            }
        } catch (Throwable $e) {
            $result['errors'][] = "$name: " . $e->getMessage();
            $result['failed']++;
            // 旧出力は残し、失敗として記録する
            if ($info !== null) {
                $new['pages'][$name] = ['path' => $info['path'], 'state' => 'failed'];
                // 旧出力は配信され続けるため、サイトマップにも残す
                if ($sitemap && $scan['pages'][$name] === 'page') {
                    $sitemapEntries[kona3getStaticURL($name)] = $mtime;
                }
            }
        }
    }

    // アセット・添付ファイル (ソースが新しいものだけコピーする)・404.html・sitemap.xml
    foreach ($assetPlan + $attachPlan as $rel => $abs) {
        $target = $outdir . '/' . $rel;
        if (!$inputsChanged && isset($old['assets'][$rel]) && is_file($target) && !is_link($target)
            && (int)@filemtime($abs) <= (int)@filemtime($target)) {
            $new['assets'][$rel] = true;
            continue;
        }
        $content = @file_get_contents($abs);
        if ($content === false) {
            $result['errors'][] = "$rel: cannot read the source file.";
            continue;
        }
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
    $generated = ['404.html' => kona3static_notFoundHtml()] + $indexFiles['files'];
    if ($sitemap) {
        ksort($sitemapEntries, SORT_STRING);
        if (count($sitemapEntries) > 50000) {
            $result['warnings'][] = 'sitemap.xml has more than 50000 URLs; the excess is omitted.';
            $sitemapEntries = array_slice($sitemapEntries, 0, 50000, true);
        }
        $generated['sitemap.xml'] = kona3static_sitemapXml($sitemapEntries);
    }
    foreach ($generated as $rel => $content) {
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
        if (kona3static_safeUnlink($outdir, $rel)) {
            $result['deleted']++;
        }
    }
    kona3static_removeEmptyDirs($outdir, $state['dirs']);

    $new['dirs'] = $state['dirs'];
    // 全ページが ok のときだけ、全体の入力の記録を更新する (12.3)
    if ($result['failed'] === 0 && count($result['errors']) === 0) {
        $new['built_at'] = time();
        $new['inputs'] = $inputs;
    }
    kona3static_saveManifest($new);

    // 取り込んだ pending は、処理を終えてから削除する。失敗したページは failed として残っている
    foreach ($pendingFiles as $f) {
        @unlink($f);
    }
    // 生成中に追記された pending があれば、なくなるまで繰り返す (12.7)
    if ($depth < 100 && kona3static_hasPending()) {
        $r = kona3static_buildLocked($outdir, ['adopt' => !empty($options['adopt'])], $depth + 1);
        foreach (['pages', 'skipped', 'failed', 'assets', 'deleted'] as $k) {
            $result[$k] += $r[$k];
        }
        $result['errors'] = array_merge($result['errors'], $r['errors']);
        $result['warnings'] = array_merge($result['warnings'], $r['warnings']);
    } elseif (kona3static_hasPending()) {
        $result['warnings'][] = 'Pending pages remain; they will be generated by the next build.';
    }
    return $result;
}

// ---------------------------------------------------------------------------
// 12.2: 編集時の1ページ生成
// ---------------------------------------------------------------------------

/** Wiki名に対応する `data/` 内のパス (拡張子なし) */
function kona3static_dataBase($wikiName)
{
    global $kona3conf;
    $segments = explode('/', $wikiName);
    if (!empty($kona3conf['enc_pagename'])) {
        $segments = array_map('urlencode', $segments);
    }
    return KONA3_DIR_DATA . '/' . implode('/', $segments);
}

/** ページの種別: 'page' / 'dir' / 'alias'。公開対象のソースが無ければ null */
function kona3static_pageKind($wikiName)
{
    $base = kona3static_dataBase($wikiName);
    foreach (['txt', 'md'] as $ext) {
        if (is_file("$base.$ext") && !is_link("$base.$ext")) {
            return 'page';
        }
    }
    if (is_dir($base) && !is_link($base)) {
        return 'dir';
    }
    require_once __DIR__ . '/action/show.inc.php';
    $aliasFile = KONA3_DIR_DATA . '/' . KONA3_ALIAS_SHOW_JSON_NAME;
    if (is_file($aliasFile) && !is_link($aliasFile)) {
        $list = kona3go_loadAliasList($aliasFile);
        if (isset($list[$wikiName]) && $wikiName !== KONA3_ALIAS_RE_RULE_KEY) {
            return 'alias';
        }
    }
    return null;
}

/**
 * ページ保存後に、そのページだけを生成する。保存の成否には影響させない
 * 対象外のページは、既存の出力があれば削除する。ロックが取れなければ pending に記録する
 *
 * @return string 警告メッセージ (問題がなければ空文字)
 */
function kona3static_onPageSaved($wikiName)
{
    global $kona3conf;
    if (empty($kona3conf['static_enable'])) {
        return '';
    }
    $savedPage = isset($GLOBALS['page']) ? $GLOBALS['page'] : null;
    $savedConf = $kona3conf;
    $level = ob_get_level();
    try {
        $outdir = kona3static_checkOutdir();
        // 非公開Wikiは生成せず、生成済みの出力を削除する (11章)
        if (!empty($kona3conf['wiki_private'])) {
            kona3static_purge($outdir);
            return '';
        }
        kona3static_prepareOutdir($outdir);
        try {
            $lock = kona3static_lock(5);
        } catch (Kona3StaticException $e) {
            kona3static_addPending($wikiName);
            return '';
        }
        try {
            kona3static_buildOneLocked($outdir, $wikiName);
        } finally {
            kona3static_unlock($lock);
        }
        return '';
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        kona3static_logError("$wikiName: " . $e->getMessage());
        return 'Static page generation failed. See cache/static_error.log';
    } finally {
        $kona3conf = $savedConf;
        $GLOBALS['page'] = $savedPage;
    }
}

/** ビルドロックを保持した状態で1ページを生成または削除する */
function kona3static_buildOneLocked($outdir, $wikiName)
{
    $old = kona3static_loadManifest($outdir);
    $new = $old;
    $state = ['outdir' => $outdir, 'old' => $old, 'dirs' => $old['dirs'], 'adopt' => false];
    $kind = kona3static_pageKind($wikiName);

    // 対象外 (削除・除外) なら、記録済みの出力を削除する
    if ($kind === null || kona3static_isExcluded($wikiName)) {
        if (isset($old['pages'][$wikiName])) {
            kona3static_safeUnlink($outdir, $old['pages'][$wikiName]['path']);
            unset($new['pages'][$wikiName]);
            kona3static_removeEmptyDirs($outdir, $state['dirs']);
            $new['dirs'] = $state['dirs'];
            kona3static_saveManifest($new);
        }
        return;
    }

    $rel = kona3getStaticFile($wikiName);
    // 他のページ・アセットと出力パスが衝突するなら書き出さない (6章)
    $key = kona3static_collisionKey($rel);
    $others = [];
    foreach ($old['pages'] as $n => $info) {
        $n = (string)$n;
        if ($n !== $wikiName) {
            $others[$info['path']] = $n;
        }
    }
    foreach ($old['assets'] as $a => $_) {
        $others[$a] = $a;
    }
    foreach ($others as $p => $label) {
        if (kona3static_collisionKey($p) === $key) {
            throw new Kona3StaticException("Output path collision: $wikiName / $label");
        }
    }

    try {
        $html = kona3static_renderForBuild($wikiName);
        kona3static_publish($state, $rel, $html, false);
    } catch (Throwable $e) {
        // 旧出力は残し、失敗として記録する
        if (isset($old['pages'][$wikiName])) {
            $new['pages'][$wikiName]['state'] = 'failed';
            kona3static_saveManifest($new);
        }
        throw $e;
    }
    $paths = [];
    $base = kona3static_dataBase($wikiName);
    foreach (['txt', 'md'] as $ext) {
        if (is_file("$base.$ext")) {
            $paths[] = "$base.$ext";
        }
    }
    $new['pages'][$wikiName] = ['path' => $rel, 'state' => 'ok', 'volatile' => kona3static_isVolatileSource($paths)];
    $new['dirs'] = $state['dirs'];
    kona3static_saveManifest($new);
}

// ---------------------------------------------------------------------------
// 12.5: マイページのボタンから呼ばれる処理
// ---------------------------------------------------------------------------

/** マニフェストに出力が記録されているか (「静的出力を削除」ボタンの表示条件) */
function kona3static_hasOutput()
{
    $recorded = kona3static_recordedOutdir();
    if ($recorded === null) {
        return false;
    }
    $m = kona3static_loadManifest($recorded);
    return count($m['pages']) > 0 || count($m['assets']) > 0;
}

/**
 * `static` アクションの本体。条件を満たさなければ例外を投げ、成功すれば結果のHTMLを返す
 * @param string $mode build(差分生成) | full(すべて再生成) | purge(削除)
 */
function kona3static_handleRequest($mode)
{
    global $kona3conf;
    if (!kona3isAdmin()) {
        throw new Kona3StaticException(lang('You do not have admin perm.'));
    }
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Kona3StaticException('POST only.');
    }
    if (!kona3_checkEditToken('edit_token')) {
        throw new Kona3StaticException(lang('Invalid Token'));
    }
    $h = function ($s) {
        return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    };
    if ($mode === 'purge') {
        if (!kona3static_hasOutput()) {
            throw new Kona3StaticException('No static output is recorded.');
        }
        $n = kona3static_purge();
        return '<p>' . $h(sprintf(lang('%d files removed.'), $n)) . '</p>';
    }
    if ($mode !== 'build' && $mode !== 'full') {
        throw new Kona3StaticException('Invalid mode.');
    }
    if (empty($kona3conf['static_enable'])) {
        throw new Kona3StaticException('Static output is disabled.');
    }
    $r = kona3static_buildAll(['full' => ($mode === 'full')]);
    $html = '<p>' . $h(sprintf(
        lang('Generated: %d, Skipped: %d, Removed: %d, Errors: %d'),
        $r['pages'],
        $r['skipped'],
        $r['deleted'],
        count($r['errors'])
    )) . '</p>';
    foreach ([['warnings', 'Warnings'], ['errors', 'Errors']] as $g) {
        if (count($r[$g[0]]) > 0) {
            $html .= '<h4>' . $h(lang($g[1])) . '</h4><ul>';
            foreach ($r[$g[0]] as $line) {
                $html .= '<li>' . $h($line) . '</li>';
            }
            $html .= '</ul>';
        }
    }
    return $html;
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
    // マニフェストが記録している出力先を優先する (static_outdir を変更した後でも旧出力を消せるように)
    $recorded = kona3static_recordedOutdir();
    $outdir = kona3static_checkOutdir($recorded !== null ? $recorded : $outdir);
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
            if (kona3static_safeUnlink($outdir, $rel)) {
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

/**
 * 全体生成で実際にコピーされる添付ファイルか (`data/` からの相対パス)
 * `ref` プラグインが、コピーされないファイルへの静的URLを出さないための共通判定
 */
function kona3static_isCopyable($dataRelPath)
{
    $rel = str_replace('\\', '/', (string)$dataRelPath);
    $rel = ltrim($rel, '/');
    if (!kona3static_isSafeRelPath($rel)) {
        return false;
    }
    $segments = explode('/', $rel);
    $cur = KONA3_DIR_DATA;
    foreach ($segments as $seg) {
        if ($seg[0] === '.') {
            return false;
        }
        $cur .= '/' . $seg;
        if (is_link($cur)) {
            return false;
        }
    }
    if (!is_file($cur)) {
        return false;
    }
    $name = end($segments);
    if (count($segments) === 1 && $name === KONA3_ALIAS_SHOW_JSON_NAME) {
        return false;
    }
    if (preg_match('/\.(txt|md)$/', $name)) {
        return false;
    }
    return kona3static_isAttachable($name);
}
