<?php

/**
 * KonaWiki3 静的Wiki出力: クライアントサイド検索とタグ一覧ページ (docs/static_wiki.md 20章)
 *
 * - 検索用インデックス `_kona3/search/index.json` と検索ページ `_kona3/search/index.html`
 * - タグ一覧 `_kona3/tags/index.html` とタグごとのページ一覧 `_kona3/tags/<タグ>/index.html`
 * いずれも全体生成のたびに作り直す (kona3static_buildLocked から呼ぶ)。
 */

require_once __DIR__ . '/kona3static.inc.php';

/** 検索インデックスに載せる本文の最大文字数 (1ページあたり) */
if (!defined('KONA3_STATIC_SEARCH_BODY_CHARS')) {
    define('KONA3_STATIC_SEARCH_BODY_CHARS', 3000);
}

/** タグ一覧ページの公開URL。タグ名を渡すとそのタグのページ一覧 */
function kona3static_tagUrl($tag = null)
{
    $url = kona3static_baseUrl() . KONA3_STATIC_SYSTEM_DIR . '/tags/';
    if ($tag === null) {
        return $url;
    }
    return $url . kona3static_encodePathForUrl(kona3static_tagDir($tag)) . '/';
}

/** タグ名の物理名 (`/` を含むタグも1階層にする) */
function kona3static_tagDir($tag)
{
    $parts = [];
    foreach (explode('/', $tag) as $p) {
        $parts[] = ($p === '') ? '' : kona3static_encodeSegment($p);
    }
    return implode(kona3static_escapeChar('/'), $parts);
}

/** 検索ページの公開URL */
function kona3static_searchUrl()
{
    return kona3static_baseUrl() . KONA3_STATIC_SYSTEM_DIR . '/search/';
}

/** ページのタグ (メタ情報から取得) */
function kona3static_pageTags($wikiName)
{
    $meta = kona3db_loadPageMeta($wikiName);
    $res = [];
    if (is_array($meta) && isset($meta['tags']) && is_array($meta['tags'])) {
        foreach ($meta['tags'] as $t) {
            $t = trim((string)$t);
            if ($t !== '' && !in_array($t, $res, true)) {
                $res[] = $t;
            }
        }
    }
    return $res;
}

/** 検索インデックス用の本文。ログイン時だけ見える行は載せない */
function kona3static_searchBody($paths)
{
    $text = '';
    foreach ($paths as $p) {
        $t = @file_get_contents($p);
        if ($t !== false) {
            $text .= $t . "\n";
        }
    }
    $text = preg_replace('/^[^\n]*[#♪]iflogin\b[^\n]*$/mu', '', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string)$text);
    return mb_substr($text, 0, KONA3_STATIC_SEARCH_BODY_CHARS);
}

/** 検索・タグ一覧ページ共通のHTML */
function kona3static_indexHtml($title, $bodyHtml, $extraHead = '')
{
    global $kona3conf;
    $wiki = htmlspecialchars(isset($kona3conf['wiki_title']) ? $kona3conf['wiki_title'] : 'KonaWiki3', ENT_QUOTES, 'UTF-8');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $home = htmlspecialchars(kona3static_baseUrl(), ENT_QUOTES, 'UTF-8');
    $search = htmlspecialchars(kona3static_searchUrl(), ENT_QUOTES, 'UTF-8');
    $tags = htmlspecialchars(kona3static_tagUrl(), ENT_QUOTES, 'UTF-8');
    return "<!DOCTYPE html>\n<html><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">" .
        "<meta name=\"robots\" content=\"noindex\"><title>{$t} - {$wiki}</title>" .
        "<style>body{font-family:sans-serif;max-width:48em;margin:0 auto;padding:1em;line-height:1.6}" .
        "nav a{margin-right:1em}input[type=search]{width:70%;padding:.4em}li{margin:.4em 0}" .
        ".snippet{color:#666;font-size:.9em}.count{color:#666;font-size:.8em}</style>{$extraHead}</head><body>" .
        "<nav><a href=\"{$home}\">{$wiki}</a><a href=\"{$search}\">Search</a><a href=\"{$tags}\">Tags</a></nav>" .
        "<h1>{$t}</h1>\n{$bodyHtml}\n</body></html>\n";
}

/**
 * 検索インデックス・検索ページ・タグ一覧ページを組み立てる
 * @param array $plan [Wiki名 => 出力パス]
 * @param array $scan kona3static_scanData() の結果
 * @return array ['files' => [出力パス => 内容], 'warnings' => [...]]
 */
function kona3static_buildIndexFiles($plan, $scan)
{
    $warnings = [];
    $entries = [];
    $tagPages = []; // タグ => [Wiki名 => true]
    foreach ($plan as $name => $_rel) {
        if ($scan['pages'][$name] !== 'page') {
            continue;
        }
        $tags = kona3static_pageTags($name);
        $entries[] = [
            'u' => kona3getStaticURL($name),
            't' => $name,
            'b' => kona3static_searchBody(isset($scan['sources'][$name]) ? $scan['sources'][$name] : []),
            'g' => $tags,
        ];
        foreach ($tags as $tag) {
            $tagPages[$tag][$name] = true;
        }
    }
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
    $dir = KONA3_STATIC_SYSTEM_DIR;
    $files = [];
    $files["$dir/search/index.json"] = json_encode($entries, $flags) . "\n";
    $files["$dir/search/index.html"] = kona3static_searchPageHtml();

    // タグ一覧。物理名が衝突するタグ (大文字小文字違いなど) と、長すぎるタグは載せない
    ksort($tagPages, SORT_STRING);
    $seen = [];
    $list = '';
    foreach ($tagPages as $tag => $pages) {
        try {
            $tdir = kona3static_tagDir($tag);
            $rel = "$dir/tags/$tdir/index.html";
            if (strlen($rel) > KONA3_STATIC_MAX_PATH_BYTES) {
                throw new Kona3StaticException('Path too long');
            }
        } catch (Kona3StaticException $e) {
            $warnings[] = "tag `$tag`: " . $e->getMessage() . '; the tag page is not generated.';
            continue;
        }
        $key = kona3static_collisionKey($rel);
        if (isset($seen[$key])) {
            $warnings[] = "tag `$tag` collides with `{$seen[$key]}`; the tag page is not generated.";
            continue;
        }
        $seen[$key] = $tag;
        $names = array_keys($pages);
        sort($names, SORT_STRING);
        $li = '';
        foreach ($names as $n) {
            $li .= '<li><a href="' . htmlspecialchars(kona3getStaticURL($n), ENT_QUOTES, 'UTF-8') . '">' .
                htmlspecialchars($n, ENT_QUOTES, 'UTF-8') . "</a></li>\n";
        }
        $files[$rel] = kona3static_indexHtml('Tag: ' . $tag, "<ul>\n{$li}</ul>");
        $list .= '<li><a href="' . htmlspecialchars(kona3static_tagUrl($tag), ENT_QUOTES, 'UTF-8') . '">' .
            htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') . '</a> <span class="count">(' . count($names) . ")</span></li>\n";
    }
    if ($list === '') {
        $list = '<li>No tags.</li>';
    }
    $files["$dir/tags/index.html"] = kona3static_indexHtml('Tags', "<ul>\n{$list}</ul>");
    return ['files' => $files, 'warnings' => $warnings];
}

/** 検索ページ。インデックス (index.json) を読み込み、ブラウザ上で絞り込む */
function kona3static_searchPageHtml()
{
    $json = htmlspecialchars(kona3static_searchUrl() . 'index.json', ENT_QUOTES, 'UTF-8');
    $body = <<<HTML
<form id="f" onsubmit="return false"><input type="search" id="q" placeholder="Search" autofocus aria-label="Search"></form>
<p id="status" class="count"></p>
<ul id="res"></ul>
<script>
(function () {
  var q = document.getElementById('q'), res = document.getElementById('res'), st = document.getElementById('status');
  var data = null;
  function norm(s) { return String(s).toLowerCase(); }
  function snippet(body, word) {
    var i = norm(body).indexOf(word);
    if (i < 0) return body.substr(0, 100);
    var s = Math.max(0, i - 30);
    return (s > 0 ? '...' : '') + body.substr(s, 100);
  }
  function run() {
    res.textContent = '';
    var words = norm(q.value).split(/\\s+/).filter(function (w) { return w !== ''; });
    if (!data || words.length === 0) { st.textContent = ''; return; }
    var n = 0;
    data.forEach(function (e) {
      var hay = norm(e.t + ' ' + e.g.join(' ') + ' ' + e.b);
      if (!words.every(function (w) { return hay.indexOf(w) >= 0; })) return;
      n++;
      if (n > 100) return;
      var li = document.createElement('li'), a = document.createElement('a'), sn = document.createElement('div');
      a.href = e.u; a.textContent = e.t;
      sn.className = 'snippet'; sn.textContent = snippet(e.b, words[0]);
      li.appendChild(a); li.appendChild(sn); res.appendChild(li);
    });
    st.textContent = n + ' found' + (n > 100 ? ' (showing 100)' : '');
  }
  q.addEventListener('input', run);
  st.textContent = 'Loading...';
  fetch('{$json}').then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
    .then(function (d) {
      data = d; st.textContent = '';
      var m = location.search.match(/[?&]q=([^&]*)/);
      if (m) { q.value = decodeURIComponent(m[1].replace(/\\+/g, ' ')); }
      run();
    }).catch(function () { st.textContent = 'Failed to load the search index.'; });
})();
</script>
HTML;
    return kona3static_indexHtml('Search', $body);
}
