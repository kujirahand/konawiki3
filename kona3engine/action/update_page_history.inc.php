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
    // user_id=0の人だけが実行可能
    $info = kona3getLoginInfo();
    $user_id = $info['user_id'];
    if ($user_id != 0) {
        kona3error("Invalid User", "You must be logged in as a valid user.");
        return;
    }

    $data_dir = KONA3_DIR_DATA;
    echo "<pre>";
    
    // リンクを表示
    $current_url = $_SERVER['REQUEST_URI'];
    $base_url = preg_replace('/[\?\&]clear=\w+/', '', $current_url);
    $separator = (strpos($base_url, '?') !== false) ? '&' : '?';
    
    echo "=== [操作メニュー] ===\n";
    echo "- [1] <a href='{$base_url}' style='color: blue; text-decoration: underline;'>通常実行（履歴を残して追加）</a>\n";
    echo "- [2] <a href='{$base_url}{$separator}clear=all' style='color: red; text-decoration: underline;'>全削除してやり直し</a>\n";
    echo "========================\n\n";

    echo "データディレクトリ: ".htmlspecialchars($data_dir)."\n";
    
    // clearパラメータのチェック
    $clear = isset($_GET['clear']) ? $_GET['clear'] : '';
    if ($clear === 'all') {
        echo "=== page_historyテーブルを全削除します ===\n";
        $stmt = db_exec("DELETE FROM page_history");
        echo "=== 削除完了 ===\n\n";
    }
    
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
    $page_id_file = KONA3_DIR_DATA . '/.kona3_page_id.json';
    
    // JSONファイルからページID一覧を読み込み
    $page_ids = [];
    if (file_exists($page_id_file)) {
        $json_content = file_get_contents($page_id_file);
        if ($json_content !== false) {
            $page_ids = json_decode($json_content, true) ?: [];
        }
    }
    
    // 既存のページIDを確認
    if (isset($page_ids[$page_name])) {
        return $page_ids[$page_name];
    }
    
    // 新しいページIDを生成（既存の最大ID + 1）
    $max_id = 0;
    foreach ($page_ids as $name => $id) {
        if ($id > $max_id) {
            $max_id = $id;
        }
    }
    $new_page_id = $max_id + 1;
    
    // 新しいページを追加
    $page_ids[$page_name] = $new_page_id;
    
    // JSONファイルに保存
    file_put_contents($page_id_file, json_encode($page_ids, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    return $new_page_id;
}
