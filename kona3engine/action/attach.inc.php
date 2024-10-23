<?php
// action : attach file
require_once __DIR__.'/show.inc.php';

function kona3_action_attach() {
    global $kona3conf;
    
    // check allow_upload
    if (!isset($kona3conf['allow_upload']) || !$kona3conf['allow_upload']) {
        kona3error('Disallow upload', '[Config] allow_upload = false'); exit;
    }
    
    // check login
    if (!kona3isLogin()) {
        kona3error('ログインが必要', 'ログインしてください');
        exit;
    }
    
    // page
    $page = $kona3conf["page"];
    
    // detect file type
    $wiki_live = kona3show_detect_file($page, $fname, $ext);
    if (!$wiki_live) {
        $msg = lang('Please make wiki page.');
        kona3error("Page not found", $msg);
        exit;
    }
    
    // check dir
    $dir_data = KONA3_DIR_DATA;
    $savedir = dirname($fname);
    $dir = substr($savedir, strlen($dir_data));
    $action = kona3getPageURL($page, 'attach');
    $max_file_size = empty($kona3conf['upload_max_file_size']) ? (1024 * 1024 * 10) : $kona3conf['upload_max_file_size'];

    // has upload
    $msg = '';
    if (!empty($_FILES['file']['tmp_name'])) {
        $tmp_name = $_FILES['file']['tmp_name'];
        // detect file name
        $name = empty($_POST['name']) ? '' : trim($_POST['name']);
        if ($name == '') {
            $name = $_FILES['file']['name'];
        }
        if ($name == '') {
            kona3error('ファイル名の未指定', 'ファイル名が未指定です'); exit;
        }
        // check name
        $name = basename($name);
        $name = preg_replace('#[\'\"\<\>\\/\:\;\*\?\|\=\s]#', '_', $name);
        // savefile
        $savefile = $savedir.'/'.$name;
        $p = pathinfo($savefile);
        $ext = empty($p['extension']) ? '' : $p['extension'];
        if ($ext == '') {
            // 元ファイルに拡張子があれば使う
            $pp = pathinfo($_FILES['file']['name']);
            if (isset($pp['extension'])) {
                $ext = $pp['extension'];
                $savefile .= ".{$ext}";
            }
        }
        $allow_ext = $kona3conf['allow_upload_ext'];
        $allow_ext_a = explode(';', $allow_ext);
        if (!in_array($ext, $allow_ext_a)) {
            kona3error(
                "アップロードエラー", 
                "設定よりアップロード可能なのは次の形式です。<br>".
                implode("<br>", $allow_ext_a)
            );
            exit;
        }

        // check double
        if (file_exists($savefile)) {
            $org = $savefile;     
            for ($i = 1; $i < 200; $i++) {
                $savefile = str_replace('.'.$ext, '', $org);
                $savefile .= "_{$i}.{$ext}";
                if (file_exists($savefile)) continue;
                break;
            }
        }

        $name = basename($savefile);
        if (move_uploaded_file($_FILES['file']['tmp_name'], $savefile)) {
            $msg = "Success to upload. [[$dir/$name]]";
        } else {
            $msg = "Failed to upload.";
        }
    }
    // files
    $htm = "";
    $fs = glob("$savedir/*");
    foreach ($fs as $f) {
        if (!is_file($f)) continue;
        $fn = substr($f, strlen($savedir) + 1);
        $htm .= "<li>".htmlspecialchars($fn)."</li>";
    }
    
    // show form 
    kona3template('attach.html', [
        'msg' => $msg,
        'action' => $action,
        'dir' => $dir,
        'max_file_size' => $max_file_size,
        'files' => $htm,
    ]);  
}


