<?php

/** alias file name (relative to KONA3_DIR_DATA) */
if (!defined('KONA3_ALIAS_JSON_NAME')) {
    define('KONA3_ALIAS_JSON_NAME', 'alias.json');
}
/** alias.json 内の正規表現ルールのキー */
if (!defined('KONA3_ALIAS_RE_RULE_KEY')) {
    define('KONA3_ALIAS_RE_RULE_KEY', '@re-rule');
}
/** max depth to resolve chained aliases */
if (!defined('KONA3_ALIAS_MAX_DEPTH')) {
    define('KONA3_ALIAS_MAX_DEPTH', 5);
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
 * "@re-rule" の値を検証して [[pattern, replace], ...] の配列にする
 * pattern は区切り文字付きのPHP正規表現(例: "/^user(\\d+)$/")。不正なルールは無視する。
 *
 * @param mixed $rules
 * @return array
 */
function kona3go_parseReRules($rules)
{
    $result = [];
    if (!is_array($rules)) {
        return $result;
    }
    foreach ($rules as $rule) {
        if (!is_array($rule) || count($rule) < 2) {
            continue;
        }
        $rule = array_values($rule);
        if (!is_string($rule[0]) || $rule[0] === '') {
            continue;
        }
        if (!is_string($rule[1]) && !is_int($rule[1]) && !is_float($rule[1])) {
            continue;
        }
        // 正規表現として不正なものは無視する
        if (@preg_match($rule[0], '') === FALSE) {
            continue;
        }
        $result[] = [$rule[0], (string)$rule[1]];
    }
    return $result;
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
        // 正規表現ルール: "@re-rule": [["pattern", "replace"], ...]
        if ($alias === KONA3_ALIAS_RE_RULE_KEY) {
            $rules = kona3go_parseReRules($page);
            if (count($rules) > 0) {
                $result[KONA3_ALIAS_RE_RULE_KEY] = $rules;
            }
            continue;
        }
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
 * "@re-rule" の正規表現ルールを順に試し、最初にマッチしたものの置換結果を返す
 *
 * @param string $page
 * @param array $aliases
 * @return string|FALSE マッチしなければ FALSE
 */
function kona3go_applyReRules($page, $aliases)
{
    if (!isset($aliases[KONA3_ALIAS_RE_RULE_KEY]) || !is_array($aliases[KONA3_ALIAS_RE_RULE_KEY])) {
        return FALSE;
    }
    foreach ($aliases[KONA3_ALIAS_RE_RULE_KEY] as $rule) {
        if (!is_array($rule) || count($rule) < 2) {
            continue;
        }
        [$pattern, $replace] = array_values($rule);
        if (@preg_match($pattern, $page) !== 1) {
            continue;
        }
        $result = @preg_replace($pattern, $replace, $page);
        if ($result === NULL) {
            continue;
        }
        return trim($result);
    }
    return FALSE;
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
        if (isset($aliases[$page]) && is_string($aliases[$page])) {
            $next = trim($aliases[$page]);
        } else {
            $next = kona3go_applyReRules($page, $aliases);
            if ($next === FALSE) {
                break;
            }
        }
        if (isset($used[$page])) {
            break; // 循環参照
        }
        $used[$page] = TRUE;
        $page = $next;
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
