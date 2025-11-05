<?php
/** 指定のパス以下にあるページ一覧を列挙して表示(ページのみ表示)
 * - [書式] #ls(filter)
 * - [引数]
 * -- filter ... フィルタ(ただし拡張子は無視される)
 * -- show_system ... MenuBarなどの隠しファイルも表示
 * -- show_dir ... ディレクトリがあればそれも表示
 * -- reverse ... 逆順に表示
 * -- sort_by_time ... 日付順に表示
 */

function kona3plugins_ls_execute($args) {
    global $kona3conf;
    // arguments
    $show_system = false;
    $show_dir = false;
    $reverse = false;
    $sort_by_time = false;
    // 引数を取得
    $filter = array_shift($args); // 先頭の引数をフィルタとする
    if ($filter == null) {
        $filter = "*";
    }
    foreach ($args as $arg) {
        if ($arg == "show_system") {
            $show_system = true;
            continue;
        }
        if ($arg == "show_dir") {
            $show_dir = true;
            continue;
        }
        if ($arg == "reverse") {
            $reverse = true;
            continue;
        }
        if ($arg == "sort_by_time") {
            $sort_by_time = true;
            continue;
        }
    }

    // セキュリティ対策: パストラバーサル攻撃を防ぐ
    // サブディレクトリ指定を許可するが、危険なパターンは除外
    $filter = preg_replace('#/\.\.(/|$)#', '/', $filter);  // /../ を / に置換（先に処理）
    $filter = preg_replace('/\.\.+/', '', $filter);  // 連続するドットを全て削除（../防止）
    $filter = str_replace(['\\', "\0", "\r", "\n"], '', $filter);  // バックスラッシュ、制御文字を削除
    $filter = preg_replace('#^/+#', '', $filter);  // 先頭のスラッシュを削除（絶対パス防止）
    $filter = preg_replace('#/+#', '/', $filter);  // 連続するスラッシュを1つに統合
    
    // 空になった場合はデフォルト値
    if (empty($filter)) {
        $filter = '*';
    }
    
    // フィルタに拡張子が含まれていない場合、.txt と .md の両方を対象にする
    $filter_patterns = [];
    if (strpos($filter, '.') === false) {
        $filter_patterns[] = $filter.".txt";
        $filter_patterns[] = $filter.".md";
    } else {
        $filter_patterns[] = $filter;
    }
    
    // ページ名のセキュリティチェック
    $page = $kona3conf['page'];  
    $page = str_replace('..', '', $page);
    if (strpos($page, '//') !== false) {
        return "";
    }
    
    $fname = kona3getWikiFile($page, false);
    
    // ディレクトリがKONA3_DIR_DATA配下にあることを確認
    $data_dir = realpath(KONA3_DIR_DATA);
    
    // ファイルが存在しない場合は親ディレクトリで確認
    if (is_dir($fname)) {
        $fname_real = realpath($fname);
    } else if (file_exists($fname)) {
        $fname_real = realpath($fname);
    } else {
        // ファイルが存在しない場合は親ディレクトリを使用
        $parent = dirname($fname);
        $fname_real = realpath($parent);
    }
    
    if ($fname_real === false || strpos($fname_real, $data_dir) !== 0) {
        // data/ディレクトリ外へのアクセスを禁止
        return "";
    }
    
    // dir?
    if (is_dir($fname_real)) {
        $dir = $fname_real;
    } else {
        $dir = dirname($fname_real);
    }
    // get all files
    $files = glob_recursive($dir."/*");
    if ($sort_by_time) {
        // 日付順にソート
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
    } else {
        sort($files);
    }
    if ($reverse) {
        $files = array_reverse($files);
    }

    $code = "<ul>";
    foreach ($files as $f) {
        $name = kona3getWikiName($f);
        $url = kona3getPageURL($name);
        $name = htmlentities($name, ENT_QUOTES, 'UTF-8');
        // ディレクトリならリンクを表示
        if ($show_dir) { // ただしオプションがあれば
            if (is_dir($f)) {
                $code .= "<li>&lt;<a href='$url'>$name/</a>&gt;</li>\n";
                continue;
            }
        }
        // ファイルならフィルタでマッチさせる（複数のパターンをチェック）
        $f2 = substr($f, strlen($dir)+1); // ディレクトリ部分を除去
        $matched = false;
        foreach ($filter_patterns as $pattern) {
            if (fnmatch($pattern, $f2)) {
                $matched = true;
                break;
            }
        }
        if ($matched) {
            // システムファイル（MenuBar等）の判定はbasenameで行う
            $basename = basename($name);
            if (!$show_system) {
                if ($basename == "MenuBar" || $basename == "GlobalBar" || $basename == "SideBar") {
                    continue;
                }
            }
            $code .= "<li><a href='$url'>$name</a></li>\n";
        }
    }
    $code .= "</ul>\n";
    return $code;
}

// 再帰的にglobする関数（セキュリティ対策強化版）
function glob_recursive($pattern, $max_depth = 5, $current_depth = 0) {
    // 最大深度チェック
    if ($current_depth >= $max_depth) {
        return [];
    }
    
    $files = glob($pattern);
    if ($files === false) {
        return [];
    }
    
    // シンボリックリンクを除外
    $files = array_filter($files, function($f) {
        return !is_link($f);
    });
    
    // サブディレクトリを取得
    $dirs = glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT);
    if ($dirs === false) {
        return $files;
    }
    
    foreach ($dirs as $dir) {
        // シンボリックリンクのディレクトリはスキップ
        if (is_link($dir)) {
            continue;
        }
        
        // 再帰呼び出し（深度を+1）
        $sub_files = glob_recursive($dir.'/'.basename($pattern), $max_depth, $current_depth + 1);
        $files = array_merge($files, $sub_files);
    }
    
    return $files;
}
