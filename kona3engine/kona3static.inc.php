<?php

/**
 * KonaWiki3 静的Wiki出力 (docs/static_wiki.md)
 *
 * - Wiki名と出力パス・公開URLの変換 (4〜6章)
 * - 静的モードでのページ描画 (8・12.5章)
 */

/** 1階層あたりの物理名の最大バイト数 (4.3) */
if (!defined('KONA3_STATIC_MAX_SEGMENT_BYTES')) {
    define('KONA3_STATIC_MAX_SEGMENT_BYTES', 200);
}
/** 出力パス全体の最大バイト数 (4.3) */
if (!defined('KONA3_STATIC_MAX_PATH_BYTES')) {
    define('KONA3_STATIC_MAX_PATH_BYTES', 1000);
}
/** システム予約領域 (4.5) */
if (!defined('KONA3_STATIC_SYSTEM_DIR')) {
    define('KONA3_STATIC_SYSTEM_DIR', '_kona3');
}

/** 静的出力で使う例外 */
class Kona3StaticException extends RuntimeException
{
}

/**
 * 静的出力中に exit の代わりにページを別URLへ転送する例外
 */
class Kona3StaticRedirectException extends Kona3StaticException
{
    public $target;

    public function __construct($target)
    {
        parent::__construct("redirect: $target");
        $this->target = $target;
    }
}

/** 静的モードかどうか */
function kona3static_isStaticMode()
{
    global $kona3conf;
    return !empty($kona3conf['static_mode']);
}

// ---------------------------------------------------------------------------
// 4章: 物理名のエンコード
// ---------------------------------------------------------------------------

/** Windows の予約名 (小文字) */
function kona3static_reservedNames()
{
    return [
        'con', 'prn', 'aux', 'nul',
        'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
        'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
    ];
}

/** 1文字(UTF-8)を `_HH` 形式にする */
function kona3static_escapeChar($ch)
{
    $res = '';
    $len = strlen($ch);
    for ($i = 0; $i < $len; $i++) {
        $res .= '_' . strtoupper(bin2hex($ch[$i]));
    }
    return $res;
}

/** UTF-8 として正しいか */
function kona3static_isValidUtf8($s)
{
    return is_string($s) && preg_match('//u', $s) === 1;
}

/** NFC 正規化 (4.1)。intl が無ければ例外 */
function kona3static_normalize($s)
{
    if (!class_exists('Normalizer')) {
        throw new Kona3StaticException(
            'The intl extension (Normalizer) is required for static output.'
        );
    }
    $n = Normalizer::normalize($s, Normalizer::FORM_C);
    if ($n === false) {
        throw new Kona3StaticException('Invalid UTF-8 sequence in a wiki name.');
    }
    return $n;
}

/**
 * Wiki名を NFC 正規化して階層名の配列にする (4.1, 4.3)
 * @return string[]
 */
function kona3static_splitWikiName($wikiName)
{
    if (is_int($wikiName)) {
        $wikiName = (string)$wikiName; // 数字だけのページ名は配列キーが int になる
    }
    if (!is_string($wikiName) || $wikiName === '') {
        throw new Kona3StaticException('Empty wiki name.');
    }
    if (!kona3static_isValidUtf8($wikiName)) {
        throw new Kona3StaticException('Invalid UTF-8 sequence in a wiki name.');
    }
    if ($wikiName[0] === '/') {
        throw new Kona3StaticException("Absolute path is not allowed: $wikiName");
    }
    $wikiName = kona3static_normalize($wikiName);
    $segments = explode('/', $wikiName);
    foreach ($segments as $seg) {
        if ($seg === '') {
            throw new Kona3StaticException("Empty segment in wiki name: $wikiName");
        }
        if ($seg === '.' || $seg === '..') {
            throw new Kona3StaticException("Invalid segment `$seg` in wiki name: $wikiName");
        }
    }
    return $segments;
}

/**
 * 階層名1つを物理名にする (4.2)
 */
function kona3static_encodeSegment($seg)
{
    $chars = preg_split('//u', $seg, -1, PREG_SPLIT_NO_EMPTY);
    $count = count($chars);
    // 先頭の連続する `.` と空白、末尾の連続する `.` と空白
    $lead = 0;
    while ($lead < $count && ($chars[$lead] === '.' || $chars[$lead] === ' ')) {
        $lead++;
    }
    $trail = $count;
    while ($trail > 0 && ($chars[$trail - 1] === '.' || $chars[$trail - 1] === ' ')) {
        $trail--;
    }
    $out = [];
    foreach ($chars as $i => $ch) {
        $esc = false;
        if (strlen($ch) === 1) {
            $o = ord($ch);
            if ($ch === '_' || $ch === '%' || strpos('<>:"\\|?*', $ch) !== false) {
                $esc = true;
            } elseif ($o < 0x20 || $o === 0x7F) {
                $esc = true;
            }
        }
        if ($i < $lead || $i >= $trail) {
            $esc = true;
        }
        $out[] = $esc ? kona3static_escapeChar($ch) : $ch;
    }
    // Windows 予約名: 先頭1文字をエンコードする (拡張子付きを含む)
    $base = strtolower(explode('.', $seg, 2)[0]);
    $base = rtrim($base, ' ');
    if (in_array($base, kona3static_reservedNames(), true)) {
        $first = $chars[0];
        // 既にエンコード済みなら何もしない
        if ($out[0] === $first) {
            $out[0] = kona3static_escapeChar($first);
        }
    }
    $enc = implode('', $out);
    if (strlen($enc) > KONA3_STATIC_MAX_SEGMENT_BYTES) {
        throw new Kona3StaticException(
            'Name too long (' . strlen($enc) . ' bytes): ' . $seg
        );
    }
    return $enc;
}

/**
 * 物理名を階層名に戻す (`_HH` を復号)
 */
function kona3static_decodeSegment($enc)
{
    return preg_replace_callback('/_([0-9A-F]{2})/', function ($m) {
        return chr(hexdec($m[1]));
    }, $enc);
}

/**
 * Wiki名を物理名の階層配列にする
 * @return string[]
 */
function kona3static_encodeWikiName($wikiName)
{
    $segments = kona3static_splitWikiName($wikiName);
    $res = [];
    foreach ($segments as $seg) {
        $res[] = kona3static_encodeSegment($seg);
    }
    return $res;
}

/** 設定上のトップページ名 */
function kona3static_frontPage()
{
    global $kona3conf;
    return !empty($kona3conf['FrontPage']) ? $kona3conf['FrontPage'] : 'FrontPage';
}

/**
 * Wiki名が FrontPage か (NFC 正規化して比較する)
 */
function kona3static_isFrontPage($wikiName)
{
    try {
        $a = kona3static_splitWikiName($wikiName);
        $b = kona3static_splitWikiName(kona3static_frontPage());
    } catch (Kona3StaticException $e) {
        return false;
    }
    return $a === $b;
}

/**
 * 出力パス (出力先からの相対) を返す (5章)
 * 例: `使い方/基本操作` => `使い方/基本操作/index.html`, FrontPage => `index.html`
 */
function kona3getStaticFile($wikiName)
{
    if (kona3static_isFrontPage($wikiName)) {
        return 'index.html';
    }
    $segments = kona3static_encodeWikiName($wikiName);
    $path = implode('/', $segments) . '/index.html';
    if (strlen($path) > KONA3_STATIC_MAX_PATH_BYTES) {
        throw new Kona3StaticException('Path too long: ' . $wikiName);
    }
    return $path;
}

/** ベースURL (末尾 `/` 付き) */
function kona3static_baseUrl()
{
    global $kona3conf;
    $base = isset($kona3conf['static_base_url']) ? trim($kona3conf['static_base_url']) : '';
    if ($base === '') {
        $base = '/';
    }
    if (substr($base, -1) !== '/') {
        $base .= '/';
    }
    return $base;
}

/** 物理パスの各階層を rawurlencode して連結する */
function kona3static_encodePathForUrl($relativePath)
{
    $parts = explode('/', $relativePath);
    $res = [];
    foreach ($parts as $p) {
        $res[] = rawurlencode($p);
    }
    return implode('/', $res);
}

/**
 * 公開URLを返す (5章)
 * 例: `使い方/基本操作` => `/%E4%BD%BF.../%E5%9F%BA.../`, FrontPage => `/`
 */
function kona3getStaticURL($wikiName)
{
    $base = kona3static_baseUrl();
    if (kona3static_isFrontPage($wikiName)) {
        return $base;
    }
    $segments = kona3static_encodeWikiName($wikiName);
    return $base . kona3static_encodePathForUrl(implode('/', $segments)) . '/';
}

/**
 * 生成済みの静的サイトを、このサーバー上で開くためのトップURL (#290)
 *
 * マイページの管理者メニューから、生成した静的サイトを素早く確認するために使う。
 * 出力先がこの Wiki の index ディレクトリの下にあるなら、その実物は公開ディレクトリの
 * 下にあるので、`baseurl` + 出力先 の URL を返す。
 * index ディレクトリの外 (絶対パスや `..`) の場合はこのサーバーの URL にできないので、
 * 公開URL (`kona3getStaticURL()` のトップ) を返す。
 *
 * @param string|null $outdir 出力先。省略時はマニフェストが記録している出力先を優先し、
 *                            なければ設定 `static_outdir` (生成後に設定を変えても、実際の出力を指すため)
 * @return string
 */
function kona3static_outputTopUrl($outdir = null)
{
    global $kona3conf;
    if ($outdir === null && function_exists('kona3static_recordedOutdir')) {
        $outdir = kona3static_recordedOutdir();
    }
    if ($outdir === null) {
        $outdir = isset($kona3conf['static_outdir']) ? trim((string)$kona3conf['static_outdir']) : '';
    }
    if ($outdir === '') {
        $outdir = 'dist';
    }
    $outdir = str_replace('\\', '/', $outdir);
    $isAbsolute = ($outdir[0] === '/' || preg_match('/^[A-Za-z]:\//', $outdir) === 1);
    if ($isAbsolute) {
        // 記録済みの出力先は絶対パスなので、index ディレクトリの下なら相対パスに直す
        $index = rtrim(str_replace('\\', '/', realpath(kona3static_indexDir()) ?: kona3static_indexDir()), '/');
        $lower = mb_strtolower($outdir, 'UTF-8');
        $lowerIndex = mb_strtolower($index, 'UTF-8');
        if ($lowerIndex !== '' && strpos($lower, $lowerIndex . '/') === 0) {
            $outdir = substr($outdir, strlen($index) + 1);
            $isAbsolute = false;
        }
    }
    $segments = [];
    foreach (explode('/', $outdir) as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..') {
            $segments = [];
            break;
        }
        $segments[] = rawurlencode($seg);
    }
    if (!$isAbsolute && $segments && !empty($kona3conf['baseurl'])) {
        return rtrim($kona3conf['baseurl'], '/') . '/' . implode('/', $segments) . '/';
    }
    $front = isset($kona3conf['FrontPage']) && $kona3conf['FrontPage'] !== ''
        ? $kona3conf['FrontPage'] : 'FrontPage';
    return kona3getStaticURL($front);
}

/**
 * アセット(スキン, resource, 添付)の公開URL (`_kona3/` 以下)
 * @param string $kind skin|resource|files
 * @param string $path 種別ごとの相対パス (`/` 区切り)
 */
function kona3static_assetUrl($kind, $path)
{
    $path = str_replace('..', '', $path);
    $path = ltrim($path, '/');
    return kona3static_baseUrl() . KONA3_STATIC_SYSTEM_DIR . '/' . $kind . '/' .
        kona3static_encodePathForUrl($path);
}

/**
 * 添付ファイルの出力パス (出力先からの相対。`_kona3/files/` 以下に階層を維持する)
 * @param string $dataRelPath `data/` からの相対パス (`/` 区切り)
 */
function kona3static_attachFile($dataRelPath)
{
    $segments = kona3static_encodeWikiName($dataRelPath);
    $path = KONA3_STATIC_SYSTEM_DIR . '/files/' . implode('/', $segments);
    if (strlen($path) > KONA3_STATIC_MAX_PATH_BYTES) {
        throw new Kona3StaticException('Path too long: ' . $dataRelPath);
    }
    return $path;
}

/** 添付ファイルの公開URL */
function kona3static_attachUrl($dataRelPath)
{
    $segments = kona3static_encodeWikiName($dataRelPath);
    return kona3static_assetUrl('files', implode('/', $segments));
}

// ---------------------------------------------------------------------------
// 6章: 衝突検出
// ---------------------------------------------------------------------------

/**
 * 衝突検出用のキー (NFC → ケースフォールディング → NFC)
 */
function kona3static_collisionKey($relativePath)
{
    $s = kona3static_normalize($relativePath);
    $s = mb_convert_case($s, MB_CASE_FOLD, 'UTF-8');
    return kona3static_normalize($s);
}

/**
 * Wiki名の一覧から、同じ出力先になる組を検出する
 * @param string[] $wikiNames
 * @return array [衝突キー => [Wiki名, ...]] (衝突したものだけ)
 */
function kona3static_findCollisions($wikiNames)
{
    $map = [];
    foreach ($wikiNames as $name) {
        $key = kona3static_collisionKey(kona3getStaticFile($name));
        if (!isset($map[$key])) {
            $map[$key] = [];
        }
        if (!in_array($name, $map[$key], true)) {
            $map[$key][] = $name;
        }
    }
    $res = [];
    foreach ($map as $key => $names) {
        if (count($names) > 1) {
            $res[$key] = $names;
        }
    }
    return $res;
}

// ---------------------------------------------------------------------------
// 書き込み (12.1, 14章)
// ---------------------------------------------------------------------------

/**
 * 出力先自身と、その既存の親階層にシンボリックリンクがあれば拒否する (14章)
 * `realpath()` はリンクをたどってしまうため、その前に検査する
 */
function kona3static_rejectSymlinkPath($outdir)
{
    $path = rtrim(str_replace('\\', '/', $outdir), '/');
    if ($path === '' || $path[0] !== '/' && !preg_match('#^[A-Za-z]:#', $path)) {
        $path = str_replace('\\', '/', getcwd()) . '/' . $path;
    }
    $cur = '';
    foreach (explode('/', $path) as $seg) {
        if ($seg === '' || $seg === '.') {
            if ($cur === '') { $cur = ''; }
            continue;
        }
        $cur .= '/' . $seg;
        if (preg_match('#^/[A-Za-z]:$#', $cur)) {
            $cur = substr($cur, 1);
            continue;
        }
        if (is_link($cur)) {
            throw new Kona3StaticException("Output directory contains a symbolic link: $cur");
        }
    }
}

/**
 * 出力先 `$outdir` の配下の相対パスに、一時ファイル→rename で原子的に書き込む
 * @return string 書き込んだ実パス
 */
function kona3static_writeFile($outdir, $relativePath, $content)
{
    if (!is_dir($outdir)) {
        throw new Kona3StaticException("Output directory does not exist: $outdir");
    }
    kona3static_rejectSymlinkPath($outdir);
    $realOut = realpath($outdir);
    if ($realOut === false) {
        throw new Kona3StaticException("Invalid output directory: $outdir");
    }
    // 相対パスの検証
    if ($relativePath === '' || $relativePath[0] === '/' || strpos($relativePath, "\0") !== false) {
        throw new Kona3StaticException("Invalid output path: $relativePath");
    }
    foreach (explode('/', $relativePath) as $seg) {
        if ($seg === '' || $seg === '.' || $seg === '..') {
            throw new Kona3StaticException("Invalid output path: $relativePath");
        }
    }
    $target = $realOut . '/' . $relativePath;
    $dir = dirname($target);
    // ディレクトリを作る (出力先の配下に限る)
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new Kona3StaticException("Cannot create directory: $dir");
        }
    }
    $realDir = realpath($dir);
    if ($realDir === false ||
        ($realDir !== $realOut && strpos($realDir, $realOut . DIRECTORY_SEPARATOR) !== 0)) {
        throw new Kona3StaticException("Output path escapes the output directory: $relativePath");
    }
    if (is_link($target)) {
        throw new Kona3StaticException("Refusing to write to a symbolic link: $relativePath");
    }
    if (is_dir($target)) {
        throw new Kona3StaticException("Target is a directory: $relativePath");
    }
    $tmp = $realDir . '/.kona3tmp_' . bin2hex(random_bytes(6));
    if (file_put_contents($tmp, $content) === false) {
        @unlink($tmp);
        throw new Kona3StaticException("Cannot write: $relativePath");
    }
    if (!@rename($tmp, $target)) {
        @unlink($tmp);
        throw new Kona3StaticException("Cannot rename: $relativePath");
    }
    return $target;
}

// ---------------------------------------------------------------------------
// ページの描画 (8・12.5章)
// ---------------------------------------------------------------------------

/**
 * 静的版に残ってはいけない動的リンク (`index.php?` など) を探す (8.1)
 * @return string[] 見つかった属性値
 */
function kona3static_findDynamicLinks($html)
{
    global $kona3conf;
    $script = !empty($kona3conf['scriptname']) ? $kona3conf['scriptname'] : 'index.php';
    $found = [];
    $phpUrl = !empty($kona3conf['static_php_url']) ? $kona3conf['static_php_url'] : '';
    $pattern = '/\b(?:href|src|action)\s*=\s*(?:(["\'])(.*?)\1|([^\s"\'>]+))/is';
    if (preg_match_all($pattern, $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $url = isset($mm[3]) ? $mm[3] : $mm[2];
            $u = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            // PHP版への導線 (static_php_url) は許可する
            if ($phpUrl !== '' && strpos($u, $phpUrl) === 0) {
                continue;
            }
            if (preg_match('#(^|/)' . preg_quote($script, '#') . '(\?|$)#', $u) ||
                preg_match('#(^|/)(index|go)\.php(\?|$)#', $u)) {
                $found[] = $url;
            }
        }
    }
    return $found;
}

/** ベースURLが絶対URL (`https://...`) か */
function kona3static_baseIsAbsolute()
{
    return preg_match('#^https?://#i', kona3static_baseUrl()) === 1;
}

/**
 * 出力ファイル (`Wiki名/index.html` など) から出力ルートへ戻る相対接頭辞
 * 例: `index.html` => `./`, `a/index.html` => `../`, `a/b/index.html` => `../../`
 */
function kona3static_relativePrefix($relativeFilePath)
{
    $dir = str_replace('\\', '/', dirname($relativeFilePath));
    if ($dir === '.' || $dir === '' || $dir === '/') {
        return './';
    }
    return str_repeat('../', substr_count($dir, '/') + 1);
}

/**
 * 書き出すHTMLのリンク・アセットURLを、そのファイルからの相対URLに書き換える (#287)
 *
 * 描画時のURLはベースURL (`/` など) から始まる絶対パスなので、出力先を
 * サーバーのルート以外 (Wikiのサブディレクトリの `dist/` のまま等) に置くと
 * CSS が読めず、リンクが 404 になる。相対URLにすれば、出力先ツリーを
 * どのパスに置いても (移動しても) そのまま機能する。
 * ベースURLが絶対URLのときは、公開先が確定しているので書き換えない。
 * 公開URLそのもの (`kona3getStaticURL()`、sitemap.xml 用) は変更しない。
 * 変換対象は href / src / action / srcset 属性と <meta http-equiv="refresh"> の
 * url だけ。それ以外 (meta の content 属性、data-* 属性、<script> / <style> の
 * 中身、本文テキスト) は変換しない (docs/static_wiki.md 5章)。
 *
 * @param string $html 描画済みHTML
 * @param string $relativeFilePath 出力先からの相対パス (`Wiki名/index.html` など)
 * @return string
 */
function kona3static_relativizeHtml($html, $relativeFilePath)
{
    if (kona3static_baseIsAbsolute()) {
        return $html;
    }
    $base = kona3static_baseUrl();
    $prefix = kona3static_relativePrefix($relativeFilePath);
    $toRelative = function ($url) use ($base, $prefix) {
        if ($url === '' || substr($url, 0, 2) === '//' || strpos($url, $base) !== 0) {
            return $url;
        }
        return $prefix . substr($url, strlen($base));
    };
    // href / src / action / srcset 属性 (引用符あり・なし)
    $rewriteAttrs = function ($text) use ($toRelative) {
        return preg_replace_callback(
            '/\b(href|src|action|srcset)(\s*=\s*)("[^"]*"|\'[^\']*\'|[^\s"\'>]+)/i',
            function ($m) use ($toRelative) {
                $raw = $m[3];
                $quote = '';
                $val = $raw;
                if ($raw !== '' && ($raw[0] === '"' || $raw[0] === "'")) {
                    $quote = $raw[0];
                    $val = substr($raw, 1, -1);
                }
                if (strtolower($m[1]) === 'srcset') {
                    // srcset は「URL ディスクリプタ, ...」の並び。各URLだけを書き換える
                    $parts = [];
                    foreach (explode(',', $val) as $entry) {
                        $tokens = preg_split('/\s+/', trim($entry));
                        if ($tokens === false || $tokens[0] === '') {
                            continue;
                        }
                        $tokens[0] = $toRelative($tokens[0]);
                        $parts[] = implode(' ', $tokens);
                    }
                    $new = implode(', ', $parts);
                } else {
                    $new = $toRelative($val);
                }
                if ($new === $val) {
                    return $m[0];
                }
                return $m[1] . $m[2] . $quote . $new . $quote;
            },
            $text
        );
    };
    // エイリアスのリダイレクトは、<meta http-equiv="refresh"> の中だけを書き換える
    // (description など、他の content 属性に `url=` が含まれても誤変換しない)
    $rewriteMetaRefresh = function ($text) use ($toRelative) {
        return preg_replace_callback(
            '/<meta\b[^>]*>/i',
            function ($m) use ($toRelative) {
                $tag = $m[0];
                if (!preg_match('/http-equiv\s*=\s*["\']?refresh/i', $tag)) {
                    return $tag;
                }
                return preg_replace_callback(
                    '/(url\s*=\s*)([^"\';]+)/i',
                    function ($mm) use ($toRelative) {
                        return $mm[1] . $toRelative(trim($mm[2]));
                    },
                    $tag
                );
            },
            $text
        );
    };
    $rewrite = function ($text) use ($rewriteAttrs, $rewriteMetaRefresh) {
        return $rewriteMetaRefresh($rewriteAttrs($text));
    };
    // <script> / <style> の中身 (JSの文字列リテラルや CSS の url()) は書き換えない。
    // 開始タグの src など、タグ自体の属性は書き換える
    $out = '';
    $rest = $html;
    while (preg_match('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', $rest, $m, PREG_OFFSET_CAPTURE)) {
        $block = $m[0][0];
        $pos = $m[0][1];
        $out .= $rewrite(substr($rest, 0, $pos));
        if (preg_match('/^(<[^>]*>)(.*)(<\/\w+\s*>)$/s', $block, $pm)) {
            $out .= $rewrite($pm[1]) . $pm[2] . $pm[3];
        } else {
            $out .= $rewrite($block);
        }
        $rest = substr($rest, $pos + strlen($block));
    }
    $out .= $rewrite($rest);
    return $out;
}

/**
 * 静的モードの既定の CSS/JS 一覧を (静的URLで) 設定し直す
 */
function kona3static_resetAssets()
{
    global $kona3conf;
    $kona3conf['js'] = [
        kona3getResourceURL('ajax_qq.js'),
        kona3getSkinURL('drawer.js', true),
    ];
    $kona3conf['css'] = [
        kona3getResourceURL('pure-min.css'),
        kona3getResourceURL('grids-responsive-min.css'),
        kona3getResourceURL('kona3def.css', true),
        kona3getResourceURL('darkmode.css', true),
        kona3getSkinURL('kona3.css', true),
        kona3getSkinURL('drawer.css', true),
    ];
}

/**
 * ページを静的HTMLとして描画する。`$kona3conf` は描画後に元へ戻す (12.5)
 *
 * @param string $wikiName
 * @return string HTML
 * @throws Kona3StaticException
 */
function kona3static_renderPage($wikiName)
{
    global $kona3conf;
    require_once __DIR__ . '/action/show.inc.php';

    // 衝突の起きない名前か先に検証する
    kona3getStaticFile($wikiName);

    $saved_conf = $kona3conf;
    $saved_get = $_GET;
    $level = ob_get_level();
    try {
        $kona3conf['static_mode'] = true;
        $kona3conf['page'] = $_GET['page'] = $wikiName;
        $kona3conf['action'] = $_GET['action'] = 'show';
        $kona3conf['status'] = $_GET['status'] = '';
        $kona3conf['show_counter'] = false;
        $kona3conf['robots'] = '';
        unset($kona3conf['data_filename'], $kona3conf['page_not_found'], $kona3conf['page_ext']);
        kona3static_resetAssets();
        kona3getPageTitleLink();

        ob_start();
        kona3_action_show();
        $html = ob_get_clean();
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        $kona3conf = $saved_conf;
        $_GET = $saved_get;
        throw $e;
    }
    $kona3conf = $saved_conf;
    $_GET = $saved_get;
    return $html;
}

/**
 * 1ページを描画して出力先に書き出す
 * @return string 出力パス (出力先からの相対)
 * @throws Kona3StaticException
 */
function kona3static_buildPage($wikiName, $outdir)
{
    $html = kona3static_renderPage($wikiName);
    $dynamic = kona3static_findDynamicLinks($html);
    if (count($dynamic) > 0) {
        throw new Kona3StaticException(
            "Dynamic links remain in `$wikiName`: " . implode(', ', array_slice($dynamic, 0, 5))
        );
    }
    $rel = kona3getStaticFile($wikiName);
    $html = kona3static_relativizeHtml($html, $rel);
    kona3static_writeFile($outdir, $rel, $html);
    return $rel;
}
