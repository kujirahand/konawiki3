<?php
// file: kona3db.inc.php
require_once __DIR__ . '/kona3conf.inc.php';
// global
global $kona3pageIds, $kona3pageIdCache;
// init global
$kona3pageIds = NULL;
$kona3pageIdCache = NULL;

// ページIDを取得する
function kona3db_getPageId($page, $canCreate = FALSE)
{
    global $kona3pageIds;
    if ($kona3pageIds === NULL) {
        // load KONA3_PAGE_ID_JSON
        if (file_exists(KONA3_PAGE_ID_JSON)) {
            $jsonData = kona3lock_load(KONA3_PAGE_ID_JSON);
            $kona3pageIds = json_decode($jsonData, TRUE);
        } else {
            $kona3pageIds = [];
        }
    }
    // return page id
    if (isset($kona3pageIds[$page])) {
        return $kona3pageIds[$page];
    }
    // create page id
    if ($canCreate) {
        // page_idの最大値を得る
        $maxId = 0;
        foreach ($kona3pageIds as $_ => $id) {
            if ($id > $maxId) {
                $maxId = $id;
            }
        }
        $kona3pageIds[$page] = $maxId + 1;
        kona3lock_save(KONA3_PAGE_ID_JSON, json_encode($kona3pageIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    return 0;
}

// ページIDから名前を取得する
function kona3db_getPageNameById($page_id, $default = '')
{
    // load page_id
    global $kona3pageIds;
    global $kona3pageIdCache;
    // load data
    if ($kona3pageIds === NULL) {
        // load
        kona3db_getPageId(kona3getConf("FrontPage"), FALSE);
    }
    // make cache
    if ($kona3pageIdCache === NULL) {
        $kona3pageIdCache = [];
        foreach ($kona3pageIds as $name => $id) {
            $kona3pageIdCache[$id] = $name;
        }
    }
    // check cache
    if (isset($kona3pageIdCache[$page_id])) {
        return $kona3pageIdCache[$page_id];
    }
    return $default;
}

function kona3db_writePage($page, $body, $user_id = 0, $tags = NULL)
{
    $page_id = kona3db_getPageId($page, TRUE); // in kona3db_writePage
    $hash = kona3getHash($body);
    // check 1 hour history
    $recent_time = time() - (60 * 60 * 1);
    $r = db_get1(
        "SELECT * FROM page_history " .
            "WHERE page_id=? AND mtime > ? AND user_id=?",
        [$page_id, $recent_time, $user_id]
    );
    if ($r) {
        $history_id = $r['history_id'];
        db_exec(
            "UPDATE page_history SET " .
                "body=?,hash=?,mtime=?,user_id=? " .
                "WHERE history_id=?",
            [$body, $hash, time(), $user_id, $history_id]
        );
    } else {
        db_exec(
            "INSERT INTO page_history" .
                "(page_id,user_id,body,hash,mtime)" .
                "VALUES(?,?,?,?,?)",
            [$page_id, $user_id, $body, $hash, time()]
        );
    }
    // update pages.mtime (for #recent plugin)
    if (trim($body) != "") {
        db_exec(
            "UPDATE pages SET mtime=? WHERE page_id=?",
            [time(), $page_id]
        );
    } else {
        // remove page 
        // 間違いかもしれないので履歴は削除しない!!
        // 但し更新履歴には出さないようにしたい	  
        db_exec(
            "UPDATE pages SET mtime=0 WHERE page_id=?",
            [$page_id]
        );
    }
    // tags (deprecated - now using file-based tag system)
    // 互換性のため$tag引数は残しますが、何もしません
    return TRUE;
}

function kona3db_getUserByName($name)
{
    // select
    $r = db_get1(
        "SELECT * FROM users WHERE name=?",
        [$name]
    );
    return $r;
}

function kona3db_getUserById($user_id)
{
    // check cache
    global $kona3db_users;
    if (!isset($kona3db_users)) {
        $kona3db_users = [];
    }
    $isNumericId = is_int($user_id) || (is_string($user_id) && ctype_digit($user_id));
    $cacheKey = $isNumericId ? intval($user_id) : (string)$user_id;
    if (isset($kona3db_users[$cacheKey])) {
        return $kona3db_users[$cacheKey];
    }
    if (!$isNumericId) {
        $info = [
            'user_id' => $cacheKey,
            'user' => $cacheKey,
            'name' => $cacheKey,
        ];
        $kona3db_users[$cacheKey] = $info;
        return $info;
    }
    $intId = intval($user_id);
    if ($intId === 0) {
        $adminName = lang('Admin');
        $info = [
            'user_id' => 0,
            'user' => $adminName,
            'name' => $adminName,
        ];
        $kona3db_users[$cacheKey] = $info;
        return $info;
    }
    // select
    $r = db_get1(
        "SELECT * FROM users WHERE user_id=?",
        [$intId]
    );
    $kona3db_users[$cacheKey] = $r;
    return $r;
}

function kona3db_getUserNameById($user_id)
{
    $u = kona3db_getUserById($user_id);
    return isset($u['name']) ? $u['name'] : '';
}


function kona3db_getPageHistory($page, $edit_token)
{
    $page_id = kona3db_getPageId($page);
    $r = db_get(
        "SELECT * FROM page_history " .
            "WHERE page_id=? ORDER BY history_id DESC",
        [$page_id]
    );
    if ($r) {
        foreach ($r as &$v) {
            $v['user'] = kona3db_resolveHistoryUserName($v['user_id']);
            $v['link'] = kona3getPageURL(
                $page,
                "edit",
                "",
                kona3getURLParams([
                    "q" => "history",
                    "history_id" => $v['history_id'],
                    "edit_token" => $edit_token
                ])
            );
            $v['delete_link'] = kona3getPageURL(
                $page,
                "edit",
                "",
                kona3getURLParams([
                    "cmd" => "history_delete",
                    "history_id" => $v['history_id'],
                    "hash" => $v['hash'],
                    "edit_token" => $edit_token
                ])
            );
            $v['size'] = strlen($v['body']);
        }
    }
    return $r;
}

function kona3db_resolveHistoryUserName($rawUserId)
{
    if ($rawUserId === null) {
        return '';
    }
    if (is_string($rawUserId)) {
        $rawUserId = trim($rawUserId);
        if ($rawUserId === '') {
            return '';
        }
        if (!ctype_digit($rawUserId)) {
            return $rawUserId;
        }
    }
    if (is_numeric($rawUserId)) {
        $intId = intval($rawUserId);
        if ($intId === 0) {
            return lang('Admin');
        }
        $name = kona3db_getUserNameById($intId);
        if ($name !== '') {
            return $name;
        }
        return (string)$rawUserId;
    }
    return (string)$rawUserId;
}

function kona3db_getPageHistoryById($history_id)
{
    $r = db_get1(
        "SELECT * FROM page_history " .
            "WHERE history_id=?",
        [$history_id]
    );
    return $r;
}

function kona3db_getPageHistoryByUserId($user_id)
{
    $pages = [];
    $r = db_get(
        "SELECT * FROM page_history " .
            "WHERE user_id=? " .
            "ORDER BY history_id DESC LIMIT 50",
        [$user_id]
    );
    $result = [];
    foreach ($r as $v) {
        $page_id = $v['page_id'];
        if (isset($pages[$page_id])) {
            continue;
        }
        $v['page'] = kona3db_getPageNameById($page_id);
        $pages[$page_id] = TRUE;
        $result[] = $v;
    }
    return $result;
}

function kona3db_getMetaStr($key, $def = '')
{
    $meta = db_get1("SELECT * FROM meta WHERE name=?", [$key]);
    if ($meta) {
        return $meta['value_s'];
    }
    return $def;
}

function kona3db_getMetaInt($key, $def = 0)
{
    $meta = db_get1("SELECT * FROM meta WHERE name=?", [$key]);
    if ($meta) {
        return $meta['value_i'];
    }
    return $def;
}

function kona3db_setMeta($key, $value_s, $value_i = 0)
{
    $meta = db_get1("SELECT * FROM meta WHERE name=?", [$key]);
    if ($meta) {
        db_exec("UPDATE meta SET value_s=?, value_i=? WHERE name=?", [$value_s, $value_i, $key]);
    } else {
        db_exec("INSERT INTO meta (name, value_s, value_i) VALUES (?, ?, ?)", [$key, $value_s, $value_i]);
    }
}

function kona3db_setMetaStr($key, $val)
{
    kona3db_setMeta($key, $val, 0);
}

function kona3db_setMetaInt($key, $val)
{
    kona3db_setMeta($key, "", $val);
}

// subdb for plugins
function subdb_exec($sql, $params = array())
{
    $dbname = "subdb";
    return db_exec($sql, $params, $dbname);
}

function subdb_insert($sql, $params = array())
{
    $dbname = "subdb";
    return db_insert($sql, $params, $dbname);
}

function subdb_get($sql, $params = array())
{
    $dbname = "subdb";
    return db_get($sql, $params, $dbname);
}

function subdb_get1($sql, $params = array())
{
    $dbname = "subdb";
    return db_get1($sql, $params, $dbname);
}

/**
 * ページのメタ情報を保存する
 * @param string $page ページ名
 * @param array $meta メタ情報の配列
 * @return bool 成功した場合はTRUE
 */
function kona3db_savePageMeta($page, $meta)
{
    // ページIDを取得
    $page_id = kona3db_getPageId($page, TRUE);
    if ($page_id === 0) {
        return FALSE;
    }
    
    // メタ情報にpage_idとpageを追加
    $meta['page_id'] = $page_id;
    $meta['page'] = $page;
    
    // タイムスタンプを更新
    if (!isset($meta['created_at'])) {
        $meta['created_at'] = time();
    }
    $meta['updated_at'] = time();
    
    // メタ情報ファイルのパスを取得
    $metaFile = kona3db_getPageMetaFile($page);
    
    // ディレクトリが存在しない場合は作成
    $dir = dirname($metaFile);
    if (!file_exists($dir)) {
        global $kona3conf;
        $dir_mode = @octdec($kona3conf['chmod_mkdir']);
        if ($dir_mode == 0) {
            $dir_mode = 0755;
        }
        @mkdir($dir, $dir_mode, TRUE);
    }
    
    // JSON形式で保存
    $jsonData = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return kona3lock_save($metaFile, $jsonData);
}

/**
 * ページのメタ情報を読み込む
 * @param string $page ページ名
 * @return array|null メタ情報の配列、存在しない場合はnull
 */
function kona3db_loadPageMeta($page)
{
    $metaFile = kona3db_getPageMetaFile($page);
    
    // ファイルが存在しない場合はnullを返す
    if (!file_exists($metaFile)) {
        return null;
    }
    
    // ファイルを読み込む
    $jsonData = kona3lock_load($metaFile);
    if ($jsonData === FALSE) {
        return null;
    }
    
    // JSONをデコード
    $meta = json_decode($jsonData, TRUE);
    return $meta;
}

/**
 * ページのメタ情報ファイルのパスを取得
 * @param string $page ページ名
 * @return string メタ情報ファイルのパス
 */
function kona3db_getPageMetaFile($page)
{
    global $kona3conf;
    
    // ページ名から拡張子を除去
    $pageName = $page;
    $ext = kona3getFileExt($page);
    if ($ext != '') {
        $pageName = substr($page, 0, -(strlen($ext) + 1));
    }
    
    // enc_pagename設定を取得
    if (empty($kona3conf['enc_pagename'])) {
        $kona3conf['enc_pagename'] = FALSE;
    }
    $encode = $kona3conf['enc_pagename'];
    
    // メタ情報ファイルのパスを生成
    // data/.meta/{タイトル}.json の形式で保存
    $dataDir = KONA3_DIR_DATA;
    $metaDir = $dataDir . '/.meta';
    
    // 階層構造を持つページの場合、各パーツをエンコード
    $pathParts = explode('/', $pageName);
    $encodedParts = array();
    foreach ($pathParts as $part) {
        $enc = $part;
        if ($encode) {
            $enc = urlencode($part);
        }
        $encodedParts[] = $enc;
    }
    
    $fileName = array_pop($encodedParts);
    
    if (count($encodedParts) > 0) {
        // サブディレクトリがある場合
        $subDir = implode('/', $encodedParts);
        $metaFile = $metaDir . '/' . $subDir . '/' . $fileName . '.json';
    } else {
        // ルートディレクトリの場合
        $metaFile = $metaDir . '/' . $fileName . '.json';
    }
    
    return $metaFile;
}
