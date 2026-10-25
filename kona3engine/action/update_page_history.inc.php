<?php
/**
 * dataフォルダ以下のテキストファイルをpage_historyテーブルに登録するスクリプト
 *
 * 登録した履歴が編集画面から見えるよう、page_id は必ず
 * kona3db_getPageId() (pages テーブル) で取得する (#46)。
 * 以前は .kona3_page_id.json に独自に ID を割り振っていたため、
 * pages テーブルと食い違う ID で履歴が登録され、DB に履歴が
 * 残っているのに編集画面の履歴一覧に出てこないことがあった。
 * また、対象ファイルは .txt だけでなく .md も含む
 * (現在の新規ページの既定形式は Markdown のため)。
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

    // 操作メニューを表示する
    // 全削除は POST フォームにする (#325)。トークン付きでも GET リンクだと
    // URLにトークンが残り (Referer/アクセスログ/履歴)、プリフェッチ等で
    // 誤実行されうるため、破壊的操作はPOSTに統一する。
    $edit_token = kona3_getEditToken('edit_token');
    $current_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $base_url = preg_replace('/[\?\&](clear|edit_token)=[^&]*/', '', $current_url);
    $base_url_h = htmlspecialchars($base_url, ENT_QUOTES, 'UTF-8');
    $edit_token_h = htmlspecialchars($edit_token, ENT_QUOTES, 'UTF-8');

    echo "=== [操作メニュー] ===\n";
    echo "- [1] <a href='{$base_url_h}' style='color: blue; text-decoration: underline;'>通常実行（履歴を残して追加）</a>\n";
    echo "- [2] <form method='post' action='{$base_url_h}' style='display:inline'>"
        . "<input type='hidden' name='clear' value='all'>"
        . "<input type='hidden' name='edit_token' value='{$edit_token_h}'>"
        . "<input type='submit' value='全削除してやり直し' "
        . "style='color: red; text-decoration: underline; background: none; border: none; cursor: pointer; padding: 0;'>"
        . "</form>\n";
    echo "========================\n\n";

    echo "データディレクトリ: ".htmlspecialchars($data_dir)."\n";

    // clearパラメータのチェック (POSTのみ受け付ける, #325)
    // 全削除は取り消せないため、CSRFトークンなしでは実行しない (#324)
    $clear = isset($_POST['clear']) ? $_POST['clear'] : '';
    if ($clear === 'all') {
        if (!kona3_checkEditToken('edit_token')) {
            echo "エラー: Invalid Token (全削除には正しいトークンが必要です)\n";
            return;
        }
        echo "=== page_historyテーブルを全削除します ===\n";
        $stmt = db_exec("DELETE FROM page_history");
        echo "=== 削除完了 ===\n\n";
    }

    if (!is_dir($data_dir)) {
        echo "エラー: データディレクトリが見つかりません: $data_dir\n";
        return;
    }

    // .txt/.mdファイルを再帰的に検索
    $files = kona3_update_page_history_files($data_dir);
    echo "見つかったファイル数: " . count($files) . "\n\n";

    $processed = 0;
    $errors = 0;

    foreach ($files as $file_path) {
        try {
            if (kona3_update_page_history_process_file($file_path, $data_dir)) {
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

// data以下の履歴登録対象 (.txt/.md) を再帰的に列挙する
// ディレクトリツリーの走査は1回だけ行う
// (拡張子ごとに走査するとサブディレクトリ探索が重複するため)
function kona3_update_page_history_files($data_dir)
{
    $files = [];
    kona3_update_page_history_collect($data_dir, $files);
    sort($files, SORT_STRING);
    return $files;
}

function kona3_update_page_history_collect($dir, &$files)
{
    $entries = @scandir($dir);
    if ($entries === false) {
        return;
    }
    foreach ($entries as $name) {
        // 隠しファイル/隠しディレクトリ (.meta など) は
        // ページファイルではないので対象にしない
        if ($name === '.' || $name === '..' || $name[0] === '.') {
            continue;
        }
        $path = $dir . '/' . $name;
        if (is_dir($path)) {
            kona3_update_page_history_collect($path, $files);
        } else if (is_file($path) && preg_match('/\.(txt|md)$/', $name)) {
            $files[] = $path;
        }
    }
}

function kona3_update_page_history_process_file($file_path, $data_dir) {
    // ファイル名からページ名を取得（拡張子を除去）
    // パス区切りは環境によらず '/' にそろえてから接頭辞を取り除く
    $path_norm = str_replace('\\', '/', $file_path);
    $prefix = rtrim(str_replace('\\', '/', $data_dir), '/') . '/';
    $relative_path = $path_norm;
    if (strpos($path_norm, $prefix) === 0) {
        $relative_path = substr($path_norm, strlen($prefix));
    }
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

    // ページIDを取得または作成 (pages テーブルに統一する #46)
    $page_id = kona3_update_page_history_get_page_id($page_name, $mtime);
    if ($page_id <= 0) {
        throw new Exception("ページIDを取得できません");
    }

    // 既存の履歴をチェック（重複登録を避ける）
    // 旧方式の ID でだけ登録済みの履歴も、同じ内容なら再登録しない
    $existing = null;
    foreach (kona3db_getPageIdsForHistory($page_name) as $candidate_id) {
        $existing = db_get1(
            "SELECT history_id FROM page_history WHERE page_id = ? AND mtime = ? AND body = ?",
            [$candidate_id, $mtime, $body]
        );
        if ($existing) {
            break;
        }
    }

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

function kona3_update_page_history_get_page_id($page_name, $mtime) {
    // 以前は .kona3_page_id.json へ独自に ID を書き込んでいたが、
    // それが pages テーブルとの食い違い (見えない履歴) の原因に
    // なっていたため、通常の編集と同じ DB の ID を使う (#46)。
    // レガシーIDしかないページは kona3db_getPageId() が
    // pages テーブルへ取り込んでから返すので、その ID で統一される。
    return intval(kona3db_getPageId($page_name, TRUE));
}
