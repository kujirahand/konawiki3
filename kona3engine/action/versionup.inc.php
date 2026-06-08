<?php
// file: kona3engine/action/versionup.inc.php

/**
 * Version Up Action
 */
function kona3_action_versionup() {
    global $kona3conf;
    
    // ログインチェック
    if (!kona3isLogin()) {
        $url = kona3getPageURL('', 'login');
        kona3error(
            lang('Please login.'),
            "<a href='{$url}'>" . lang('Login') . "</a>"
        );
        return;
    }
    
    $ver = isset($_GET['ver']) ? $_GET['ver'] : '';
    $cmd = isset($_GET['cmd']) ? $_GET['cmd'] : '';
    
    if ($ver === '3.3to3.4') {
        if ($cmd === 'delete_msg') {
            // メッセージを消す（リネームのみ行う）
            $json_path = KONA3_PAGE_ID_JSON;
            $bak_path = KONA3_PAGE_ID_JSON . '.bak';
            if (file_exists($json_path)) {
                if (@rename($json_path, $bak_path) === FALSE) {
                    copy($json_path, $bak_path);
                    unlink($json_path);
                }
            }
            kona3showMessage(
                lang('Version Up'),
                lang('The migration message has been dismissed.')
            );
            return;
        }
        
        // 移行処理を実行
        $result = kona3_ver33to34();
        if ($result) {
            kona3showMessage(
                lang('Version Up'),
                lang('Alias files have been created successfully.')
            );
        } else {
            kona3showMessage(
                lang('Version Up'),
                lang('No migration target found or already migrated.')
            );
        }
        return;
    }
    
    kona3error(
        lang('Invalid Parameter'),
        lang('Invalid version up parameter.')
    );
}

/**
 * 3.3から3.4へのバージョンアップ処理（ページID廃止に伴うエイリアスファイル作成）
 * NOTE: この移行処理は数年後には不要になると考えられるため、1.5年後の2028年までに廃止することを想定しています。
 *
 * @return bool
 */
function kona3_ver33to34() {
    $json_path = KONA3_PAGE_ID_JSON;
    if (!file_exists($json_path)) {
        return FALSE;
    }
    
    $jsonData = kona3lock_load($json_path);
    $legacy_page_ids = json_decode($jsonData, TRUE);
    if (!is_array($legacy_page_ids) || empty($legacy_page_ids)) {
        return FALSE;
    }

    
    foreach ($legacy_page_ids as $name => $id) {
        // ページIDのバリデーション（安全なファイル名とするため、英数字、ハイフン、アンダースコアのみ許容）
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $id)) {
            continue;
        }
        
        $filepath = KONA3_DIR_DATA . "/{$id}.md";
        // 既存のファイルがあるときは、上書きしない
        if (file_exists($filepath)) {
            continue;
        }
        
        // エイリアスファイルを作成
        $content = "!!alias({$name})";
        kona3lock_save($filepath, $content);
    }
    
    // 最後に、.json を .json.bak にリネーム
    $bak_path = $json_path . '.bak';
    if (@rename($json_path, $bak_path) === FALSE) {
        copy($json_path, $bak_path);
        unlink($json_path);
    }
    
    return TRUE;
}
