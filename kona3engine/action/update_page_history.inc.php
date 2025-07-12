<?php
/**
 * dataフォルダ以下のテキストファイルをpage_historyテーブルに登録するスクリプト
 */

function kona3_action_update_page_history()
{
    // ログインしているかチェック
    if (!kona3isLogin()) {
        kona3error("Login Required", "You must be logged in to perform this action.");
        return;
    }

    $data_dir = KONA3_DIR_DATA;
    echo "<pre>";
    echo "データディレクトリ: ".htmlspecialchars($data_dir)."\n";
    
    if (!is_dir($data_dir)) {
        echo "エラー: データディレクトリが見つかりません: $data_dir\n";
        return;
    }
    
    // .txtファイルを再帰的に検索
    $files = glob_recursive($data_dir . '/*.txt');
    echo "見つかったファイル数: " . count($files) . "\n\n";
    
    $processed = 0;
    $errors = 0;
    
    foreach ($files as $file_path) {
        try {
            if (process_text_file($file_path, $data_dir)) {
                $processed++;
            }
        } catch (Exception $e) {
            echo "エラー: $file_path - " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n処理完了:\n";
    echo "- 処理済み: $processed ファイル\n";
    echo "- エラー: $errors ファイル\n";
}

function glob_recursive($pattern) {
    $files = glob($pattern);
    $dirs = glob(dirname($pattern) . '/*', GLOB_ONLYDIR);
    
    foreach ($dirs as $dir) {
        $files = array_merge($files, glob_recursive($dir . '/' . basename($pattern)));
    }
    
    return $files;
}

function process_text_file($file_path, $data_dir) {
    // ファイル名からページ名を取得（.txt拡張子を除去）
    $relative_path = str_replace($data_dir . '/', '', $file_path);
    $page_name = preg_replace('/\.(txt|md)$/', '', $relative_path);
    
    // README.mdなどのシステムファイルはスキップ
    if (in_array(basename($page_name), ['README', 'readme'])) {
        echo "スキップ: $page_name (システムファイル)\n";
        return false;
    }
    
    // ファイルの内容を読み込み
    $body = file_get_contents($file_path);
    if ($body === false) {
        throw new Exception("ファイルを読み込めません");
    }
    
    // ファイルの更新日時を取得
    $mtime = filemtime($file_path);
    if ($mtime === false) {
        throw new Exception("ファイルの更新日時を取得できません");
    }
    
    // ページIDを取得または作成
    $page_id = get_or_create_page_id($page_name, $mtime);
    
    // 既存の履歴をチェック（重複登録を避ける）
    $existing = db_get1(
        "SELECT history_id FROM page_history WHERE page_id = ? AND mtime = ? AND body = ?",
        [$page_id, $mtime, $body]
    );
    
    if ($existing) {
        echo "スキップ: $page_name (既に登録済み)\n";
        return false;
    }
    
    // ハッシュ値を生成
    $hash = kona3getPageHash($body);
    
    // page_historyに登録
    $history_id = db_insert(
        "INSERT INTO page_history (page_id, user_id, body, hash, mtime) VALUES (?, ?, ?, ?, ?)",
        [$page_id, 0, $body, $hash, $mtime] // user_id=0 (システム登録)
    );
    
    if ($history_id) {
        echo "登録: $page_name (ID: $page_id, 履歴ID: $history_id, 更新日: " . date('Y-m-d H:i:s', $mtime) . ")\n";
        return true;
    } else {
        throw new Exception("データベースへの登録に失敗");
    }
}

function get_or_create_page_id($page_name, $mtime) {
    // 既存のページIDを確認
    $existing_page = db_get1(
        "SELECT page_id FROM pages WHERE name = ?",
        [$page_name]
    );
    
    if ($existing_page) {
        // 既存ページの更新日時も更新
        db_exec(
            "UPDATE pages SET mtime = ? WHERE page_id = ?",
            [$mtime, $existing_page['page_id']]
        );
        return $existing_page['page_id'];
    }
    
    // 新しいページを作成
    $page_id = db_insert(
        "INSERT INTO pages (name, ctime, mtime) VALUES (?, ?, ?)",
        [$page_name, $mtime, $mtime]
    );
    
    return $page_id;
}
