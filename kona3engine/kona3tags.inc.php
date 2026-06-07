<?php
/**
 * KonaWiki3 Tag Management System (SQLite-based)
 * タグをSQLiteで管理する
 */

/**
 * タグキャッシュDBの初期化
 */
function kona3tags_initDb() {
    $dbPath = KONA3_DIR_PRIVATE . '/tags.sqlite';
    $sqlPath = KONA3_DIR_ENGINE . '/template/tags.sql';
    database_set(
        $dbPath,
        $sqlPath,
        'tags'
    );
    
    // データベースが初期化されていないか、テーブルがない場合は明示的に作成
    if (!db_table_exists('tags', 'tags')) {
        $sql = @file_get_contents($sqlPath);
        if ($sql) {
            $pdo = database_get('tags');
            $pdo->exec($sql);
        }
    }
}

/**
 * 互換性のために残す（何もしないか、DB初期化を呼ぶ）
 */
function kona3tags_getDir() {
    return KONA3_DIR_DATA . '/.kona3_tag';
}

function kona3tags_initDir() {
    kona3tags_initDb();
}

function kona3tags_migrateFromSQLite() {
    // 互換性のために残す
}

function kona3tags_migrateOldFormat() {
    // 互換性のために残す
}

function kona3tags_getFilePath($tag) {
    $tag_safe = rawurlencode($tag);
    $tag_safe = str_replace('.', '%2E', $tag_safe);
    return kona3tags_getDir() . '/' . $tag_safe . '.json';
}

/**
 * タグデータを読み込む
 * @param string $tag タグ名
 * @return array ページ情報の配列 [{page, mtime}, ...]
 */
function kona3tags_load($tag) {
    return kona3tags_getPages($tag, 'mtime', 0);
}

/**
 * タグデータを保存する
 * @param string $tag タグ名
 * @param array $pages ページ情報の配列 [{page, mtime}, ...]
 */
function kona3tags_save($tag, $pages) {
    kona3tags_initDb();
    db_exec("DELETE FROM tags WHERE tag=?", [$tag], 'tags');
    $time = time();
    foreach ($pages as $p) {
        $page = $p['page'];
        $mtime = isset($p['mtime']) ? intval($p['mtime']) : $time;
        db_exec(
            "INSERT OR REPLACE INTO tags (tag, page, created_at, updated_at) VALUES (?, ?, ?, ?)",
            [$tag, $page, $mtime, $mtime],
            'tags'
        );
    }
}

/**
 * ページにタグを追加する
 * @param string $page ページ名
 * @param string $tag タグ名
 */
function kona3tags_addPageTag($page, $tag) {
    $tag = trim($tag);
    if ($tag === '') return;
    
    // タグの長さを20文字に制限
    if (mb_strlen($tag) > 20) {
        $tag = mb_substr($tag, 0, 20);
    }
    
    kona3tags_initDb();
    $time = time();
    db_exec(
        "INSERT INTO tags (tag, page, created_at, updated_at) VALUES (?, ?, ?, ?) " .
        "ON CONFLICT(tag, page) DO UPDATE SET updated_at = ?",
        [$tag, $page, $time, $time, $time],
        'tags'
    );
}

/**
 * ページからタグを削除する
 * @param string $page ページ名
 * @param string $tag タグ名
 */
function kona3tags_removePageTag($page, $tag) {
    kona3tags_initDb();
    db_exec(
        "DELETE FROM tags WHERE page=? AND tag=?",
        [$page, $tag],
        'tags'
    );
}

/**
 * ページの全タグをクリアする
 * @param string $page ページ名
 */
function kona3tags_clearPageTags($page) {
    kona3tags_initDb();
    db_exec(
        "DELETE FROM tags WHERE page=?",
        [$page],
        'tags'
    );
}

/**
 * ページに設定されているタグ一覧を取得する
 * @param string $page ページ名
 * @return array タグ名の配列
 */
function kona3tags_getPageTags($page) {
    kona3tags_initDb();
    $rows = db_get("SELECT tag FROM tags WHERE page=?", [$page], 'tags');
    $tags = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $tags[] = $row['tag'];
        }
    }
    return $tags;
}

/**
 * タグが設定されているページ一覧を取得する
 * @param string $tag タグ名
 * @param string $sort ソート方法 (mtime|page)
 * @param int $limit 取得件数
 * @return array ページ情報の配列
 */
function kona3tags_getPages($tag, $sort = 'mtime', $limit = 30) {
    kona3tags_initDb();
    $order = ($sort === 'page') ? 'page ASC' : 'updated_at DESC';
    $sql = "SELECT page, updated_at as mtime FROM tags WHERE tag=? ORDER BY {$order}";
    $params = [$tag];
    if ($limit > 0) {
        $sql .= " LIMIT ?";
        $params[] = intval($limit);
    }
    $rows = db_get($sql, $params, 'tags');
    return is_array($rows) ? $rows : [];
}

/**
 * 全てのタグ一覧を取得する
 * @return array タグ名の配列
 */
function kona3tags_getAllTags() {
    kona3tags_initDb();
    $rows = db_get("SELECT DISTINCT tag FROM tags ORDER BY tag ASC", [], 'tags');
    $tags = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $tags[] = $row['tag'];
        }
    }
    return $tags;
}

/**
 * ページ単位でタグを一括更新する（DELETEしてからINSERT）
 * @param string $page ページ名
 * @param array $tags タグ名の配列
 */
function kona3tags_updatePageTags($page, $tags) {
    kona3tags_initDb();
    db_exec("DELETE FROM tags WHERE page=?", [$page], 'tags');
    $time = time();
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if ($tag === '') continue;
        if (mb_strlen($tag) > 20) {
            $tag = mb_substr($tag, 0, 20);
        }
        db_exec(
            "INSERT OR IGNORE INTO tags (tag, page, created_at, updated_at) VALUES (?, ?, ?, ?)",
            [$tag, $page, $time, $time],
            'tags'
        );
    }
}

/**
 * 全ページのメタデータからタグキャッシュを再構築する
 */
function kona3tags_rebuildAll() {
    kona3tags_initDb();
    
    // トランザクション処理
    db_begin('tags');
    try {
        db_exec("DELETE FROM tags", [], 'tags');
        
        $meta_dir = KONA3_DIR_DATA . '/.meta';
        if (!file_exists($meta_dir)) {
            db_commit('tags');
            return;
        }
        
        // 再帰的に.jsonを走査
        $dir_iterator = new RecursiveDirectoryIterator($meta_dir);
        $iterator = new RecursiveIteratorIterator($dir_iterator);
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $json_data = @file_get_contents($file->getPathname());
                if ($json_data === FALSE) continue;
                $meta = json_decode($json_data, true);
                if ($meta && isset($meta['page']) && isset($meta['tags']) && is_array($meta['tags'])) {
                    $page = $meta['page'];
                    $created_at = isset($meta['created_at']) ? intval($meta['created_at']) : time();
                    $updated_at = isset($meta['updated_at']) ? intval($meta['updated_at']) : time();
                    foreach ($meta['tags'] as $tag) {
                        $tag = trim($tag);
                        if ($tag === '') continue;
                        if (mb_strlen($tag) > 20) {
                            $tag = mb_substr($tag, 0, 20);
                        }
                        db_exec(
                            "INSERT OR IGNORE INTO tags (tag, page, created_at, updated_at) VALUES (?, ?, ?, ?)",
                            [$tag, $page, $created_at, $updated_at],
                            'tags'
                        );
                    }
                }
            }
        }
        db_commit('tags');
    } catch (Exception $e) {
        db_rollback('tags');
        throw $e;
    }
}
