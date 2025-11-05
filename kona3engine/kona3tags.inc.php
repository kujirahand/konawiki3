<?php
/**
 * KonaWiki3 Tag Management System (File-based)
 * タグをファイルベースで管理する
 */

/**
 * タグディレクトリのパスを取得
 */
function kona3tags_getDir() {
    return KONA3_DIR_DATA . '/.kona3_tag';
}

/**
 * タグディレクトリを初期化する
 */
function kona3tags_initDir() {
    $tag_dir = kona3tags_getDir();
    if (!file_exists($tag_dir)) {
        mkdir($tag_dir, 0777, true);
        // SQLiteからの移行処理
        kona3tags_migrateFromSQLite();
    }
}

/**
 * SQLiteからタグデータを移行する
 */
function kona3tags_migrateFromSQLite() {
    // tagsテーブルからデータを取得
    try {
        $rows = db_get('SELECT * FROM tags', []);
        if (!$rows) return;
        
        // タグごとにグループ化してファイルに保存
        $tag_data = [];
        foreach ($rows as $row) {
            $tag = $row['tag'];
            $page_id = $row['page_id'];
            $mtime = $row['mtime'];
            
            if (!isset($tag_data[$tag])) {
                $tag_data[$tag] = [];
            }
            
            // ページ名を取得
            $page_name = kona3db_getPageNameById($page_id);
            if ($page_name) {
                $tag_data[$tag][] = [
                    'page' => $page_name,
                    'page_id' => $page_id,
                    'mtime' => $mtime
                ];
            }
        }
        
        // タグファイルに保存
        foreach ($tag_data as $tag => $pages) {
            kona3tags_save($tag, $pages);
        }
        
        // tagsテーブルをクリア
        db_exec('DELETE FROM tags', []);
    } catch (Exception $e) {
        // SQLiteにtagsテーブルがない場合は無視
    }
}

/**
 * タグファイルのパスを取得
 */
function kona3tags_getFilePath($tag) {
    $tag_safe = preg_replace('/[^a-zA-Z0-9_\-\.]+/', '_', $tag);
    return kona3tags_getDir() . '/' . $tag_safe . '.json';
}

/**
 * タグデータを読み込む
 * @param string $tag タグ名
 * @return array ページ情報の配列 [{page, page_id, mtime}, ...]
 */
function kona3tags_load($tag) {
    kona3tags_initDir();
    
    $filepath = kona3tags_getFilePath($tag);
    if (!file_exists($filepath)) {
        return [];
    }
    
    $json = file_get_contents($filepath);
    $data = json_decode($json, true);
    
    if (!is_array($data)) {
        return [];
    }
    
    return $data;
}

/**
 * タグデータを保存する
 * @param string $tag タグ名
 * @param array $pages ページ情報の配列 [{page, page_id, mtime}, ...]
 */
function kona3tags_save($tag, $pages) {
    kona3tags_initDir();
    
    $filepath = kona3tags_getFilePath($tag);
    $json = json_encode($pages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    kona3lock_save($filepath, $json);
}

/**
 * ページにタグを追加する
 * @param string $page ページ名
 * @param string $tag タグ名
 */
function kona3tags_addPageTag($page, $tag) {
    $tag = trim($tag);
    if ($tag === '') return;
    
    $page_id = kona3db_getPageId($page, TRUE);
    $pages = kona3tags_load($tag);
    
    // 既に存在する場合は更新
    $found = false;
    foreach ($pages as &$p) {
        if ($p['page'] === $page) {
            $p['mtime'] = time();
            $found = true;
            break;
        }
    }
    
    // 新規追加
    if (!$found) {
        $pages[] = [
            'page' => $page,
            'page_id' => $page_id,
            'mtime' => time()
        ];
    }
    
    kona3tags_save($tag, $pages);
}

/**
 * ページからタグを削除する
 * @param string $page ページ名
 * @param string $tag タグ名
 */
function kona3tags_removePageTag($page, $tag) {
    $pages = kona3tags_load($tag);
    
    $new_pages = [];
    foreach ($pages as $p) {
        if ($p['page'] !== $page) {
            $new_pages[] = $p;
        }
    }
    
    if (count($new_pages) > 0) {
        kona3tags_save($tag, $new_pages);
    } else {
        // タグにページがなくなったらファイルを削除
        $filepath = kona3tags_getFilePath($tag);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}

/**
 * ページの全タグをクリアする
 * @param string $page ページ名
 */
function kona3tags_clearPageTags($page) {
    kona3tags_initDir();
    
    // 全タグファイルを走査
    $files = glob(kona3tags_getDir() . '/*.json');
    foreach ($files as $filepath) {
        $json = file_get_contents($filepath);
        $pages = json_decode($json, true);
        
        if (!is_array($pages)) continue;
        
        $new_pages = [];
        foreach ($pages as $p) {
            if ($p['page'] !== $page) {
                $new_pages[] = $p;
            }
        }
        
        if (count($new_pages) > 0) {
            $json = json_encode($new_pages, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            kona3lock_save($filepath, $json);
        } else {
            // タグにページがなくなったらファイルを削除
            unlink($filepath);
        }
    }
}

/**
 * ページに設定されているタグ一覧を取得する
 * @param string $page ページ名
 * @return array タグ名の配列
 */
function kona3tags_getPageTags($page) {
    kona3tags_initDir();
    
    $tags = [];
    $files = glob(kona3tags_getDir() . '/*.json');
    
    foreach ($files as $filepath) {
        $json = file_get_contents($filepath);
        $pages = json_decode($json, true);
        
        if (!is_array($pages)) continue;
        
        foreach ($pages as $p) {
            if ($p['page'] === $page) {
                // ファイル名からタグ名を取得
                $basename = basename($filepath, '.json');
                $tags[] = $basename;
                break;
            }
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
    $pages = kona3tags_load($tag);
    
    // ソート
    if ($sort === 'mtime') {
        usort($pages, function($a, $b) {
            return $b['mtime'] - $a['mtime'];
        });
    } else if ($sort === 'page') {
        usort($pages, function($a, $b) {
            return strcmp($a['page'], $b['page']);
        });
    }
    
    // 件数制限
    if ($limit > 0 && count($pages) > $limit) {
        $pages = array_slice($pages, 0, $limit);
    }
    
    return $pages;
}

/**
 * 全てのタグ一覧を取得する
 * @return array タグ名の配列
 */
function kona3tags_getAllTags() {
    kona3tags_initDir();
    
    $tags = [];
    $files = glob(kona3tags_getDir() . '/*.json');
    
    foreach ($files as $filepath) {
        $basename = basename($filepath, '.json');
        $tags[] = $basename;
    }
    
    sort($tags);
    return $tags;
}
