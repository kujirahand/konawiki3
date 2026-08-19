<?php

/** alias file name (relative to KONA3_DIR_DATA) */
if (!defined('KONA3_ALIAS_JSON_NAME')) {
    define('KONA3_ALIAS_JSON_NAME', 'alias.json');
}
/** max depth to resolve chained aliases */
if (!defined('KONA3_ALIAS_MAX_DEPTH')) {
    define('KONA3_ALIAS_MAX_DEPTH', 10);
}

/** KonaWiki3 go */
function kona3_action_go()
{
    global $kona3conf;
    $url = kona3go_getRedirectURL($kona3conf['page']);
    header("location: $url");
    echo "<a href='$url'>JUMP</a>";
    exit;
}

/**
 * alias.json のパスを返す
 *
 * @return string
 */
function kona3go_getAliasFile()
{
    return KONA3_DIR_DATA . '/' . KONA3_ALIAS_JSON_NAME;
}

/**
 * data/alias.json を読み込んで、[エイリアス名 => 実際のWiki名] の配列を返す
 *
 * @param string|NULL $path 読み込むファイル(省略時は data/alias.json)
 * @return array
 */
function kona3go_loadAliasList($path = NULL)
{
    if ($path === NULL) {
        $path = kona3go_getAliasFile();
    }
    if (!file_exists($path)) {
        return [];
    }
    $json = kona3lock_load($path);
    if ($json === FALSE) {
        return [];
    }
    return kona3go_parseAliasList($json);
}

/**
 * alias.json の内容(JSON文字列)を解析して、[エイリアス名 => 実際のWiki名] の配列を返す
 * NOTE: json_decode() は "1" のような数値のキーを int に変換するため、キーは文字列に戻して扱う。
 * 真偽値・配列などの値や、空のキー・値は無視する。
 *
 * @param string $json
 * @return array
 */
function kona3go_parseAliasList($json)
{
    $data = json_decode($json, TRUE);
    if (!is_array($data)) {
        return [];
    }
    $result = [];
    foreach ($data as $alias => $page) {
        // 文字列・数値以外(配列・真偽値・null)は無視する
        if (!is_string($page) && !is_int($page) && !is_float($page)) {
            continue;
        }
        $alias = trim((string)$alias);
        $page = trim((string)$page);
        if ($alias === '' || $page === '') {
            continue;
        }
        $result[$alias] = $page;
    }
    return $result;
}

/**
 * エイリアス名を実際のWiki名に変換する(エイリアスが連鎖する場合も辿る)
 *
 * @param string $page
 * @param array $aliases
 * @return string
 */
function kona3go_resolveAlias($page, $aliases)
{
    $page = trim($page);
    if ($page === '' || !is_array($aliases)) {
        return $page;
    }
    $used = [];
    for ($i = 0; $i < KONA3_ALIAS_MAX_DEPTH; $i++) {
        if (!isset($aliases[$page])) {
            break;
        }
        if (isset($used[$page])) {
            break; // 循環参照
        }
        $used[$page] = TRUE;
        $page = trim($aliases[$page]);
    }
    return $page;
}

/**
 * go.php?{PAGE|ALIAS} のリダイレクト先URLを返す
 *
 * @param string $page ページ名またはエイリアス名
 * @param array|NULL $aliases エイリアス一覧(省略時は data/alias.json を読み込む)
 * @return string
 */
function kona3go_getRedirectURL($page, $aliases = NULL)
{
    $page = trim($page);
    if ($page === '') {
        return 'index.php';
    }
    if ($aliases === NULL) {
        $aliases = kona3go_loadAliasList();
    }
    $page = kona3go_resolveAlias($page, $aliases);
    if ($page === '') {
        return 'index.php';
    }
    return 'index.php?' . urlencode($page) . '&show';
}
