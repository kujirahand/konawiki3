<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';
include_once __DIR__.'/show.inc.php';

function kona3_action_data() {
  $fileNotFound = "<html><body><h1>404 File not found.</h1><a href='index.php'>-&gt; index</a></body></html>\n\n";
  $fileNotAllowed = "<html><body><h1>415 Unsupported Media Type.</h1><a href='index.php'>-&gt; index</a></body></html>\n\n";
  $fname = kona3getConf('page');
  // check login
  if (kona3show_check_private($fname)) {
    return;
  } 
  // check filename (日本語などマルチバイト文字のファイル名も許可する #395。
  // data フォルダの外へ出ないことは、この後の realpath チェックで保証する #324)
  if (!kona3_action_data_isValidName($fname)) {
    header('HTTP/1.0 404 Not Found');
    echo $fileNotFound;
    exit;
  }
  // remove ".."
  $fname = str_replace('..', '', $fname);
  // check data dir (シンボリックリンク経由で data 外のファイルを配信しない, #324)
  $fullpath = KONA3_DIR_DATA."/$fname";
  $real = realpath($fullpath);
  $data_real = realpath(KONA3_DIR_DATA);
  if ($real === false || $data_real === false || !is_file($real) ||
      strpos($real, rtrim($data_real, '/\\') . DIRECTORY_SEPARATOR) !== 0) {
    header('HTTP/1.0 404 Not Found');
    echo $fileNotFound;
    exit;
  }
  $fullpath = $real;
  // image (拡張子の大文字小文字は区別しない: refプラグインやMarkdown側は小文字化して判定している)
  $image_ext = kona3_action_data_imageExt($fname);
  if ($image_ext !== '') {
    $ctype = "image/".$image_ext;
    if ($image_ext === 'svg') {
      $ctype = 'image/svg+xml';
    }
    header("Content-Type: $ctype");
    header("Content-Length: ".filesize($fullpath));
    header("X-Content-Type-Options: nosniff");
    if ($image_ext === 'svg') {
      // SVGはスクリプトを持てるため、トップレベル文書として開かれても
      // Wikiのオリジンで実行されないよう sandbox を付ける (#324)。
      header("Content-Security-Policy: sandbox");
    }
    echo file_get_contents($fullpath);
    exit;
  }
  // data_dir_allow_pattern (拡張子の大文字小文字は区別しない)
  if (!kona3_action_data_isAllowed($fname)) {
    header('HTTP/1.0 415 Unsupported Media Type');
    echo $fileNotAllowed;
    exit;
  }
  // output contents
  // ファイルの中身からMIMEを判定すると、HTMLやSVGを書いた .csv / .xml が
  // text/html や image/svg+xml として配信され、Wikiのオリジンでスクリプトが
  // 動いてしまう。拡張子から固定のMIMEを決め、スニッフィングも禁止する (#324)。
  $ext = kona3_action_data_ext($fname);
  $mime = kona3_action_data_getContentType($ext);
  header("Content-Type: $mime");
  header("Content-Length: ".filesize($fullpath));
  header("X-Content-Type-Options: nosniff");
  if ($mime === 'application/octet-stream') {
    // 種類の分からないファイルはブラウザで開かずダウンロードさせる
    header('Content-Disposition: attachment');
  }
  if ($ext !== 'pdf') {
    // 万一ブラウザが文書として描画しても、スクリプトを実行させない
    // (PDFはsandbox下だとブラウザのビューアが動かないため除外)
    header("Content-Security-Policy: sandbox");
  }
  echo file_get_contents($fullpath);
}

/** 拡張子を正規化して返す (全角英数字→半角・全角ドット対応・小文字化, #395) */
function kona3_action_data_ext($fname) {
  if (!is_string($fname)) {
    return '';
  }
  if (!preg_match('#[.．]([^.．]*)$#u', $fname, $m)) {
    return '';
  }
  if ($m[1] === '') {
    return '';
  }
  return strtolower(mb_convert_kana($m[1], 'a'));
}

/** 画像ファイルなら正規化した拡張子を返す (大文字小文字・全角を区別しない, #395) */
function kona3_action_data_imageExt($fname) {
  $ext = kona3_action_data_ext($fname);
  $image_type = kona3getConf("image_pattern", "(jpg|jpeg|png|gif|ico|svg|webp)");
  if (preg_match("#^($image_type)$#i", $ext)) {
    return $ext;
  }
  return '';
}

/** data_dir_allow_pattern で配信が許可されたファイルか (大文字小文字・全角を区別しない, #395) */
function kona3_action_data_isAllowed($fname) {
  $ext = kona3_action_data_ext($fname);
  $allow_pattern = kona3getConf(
    "data_dir_allow_pattern",
    "(csv|json|xml|doc|docx|xls|xlsx|ppt|pptx|pdf|zip|gz|bz2|wav|mid|mp3|mp4|ogg|mmd|mermaid|drawio)");
  return preg_match("#^($allow_pattern)$#i", $ext) === 1;
}

/** 配信してよいファイル名か (制御文字・バックスラッシュ・ドット始まりの要素を禁じる, #395) */
function kona3_action_data_isValidName($fname) {
  if (!is_string($fname) || $fname === '') {
    return FALSE;
  }
  if (preg_match('/[\x00-\x1F\x7F]/', $fname) || strpos($fname, '\\') !== FALSE) {
    return FALSE;
  }
  foreach (explode('/', $fname) as $segment) {
    if (strpos($segment, '.') === 0) {
      return FALSE;
    }
  }
  return TRUE;
}

/** dataフォルダのファイルを配信するときのContent-Type (拡張子から固定で決める, #324) */
function kona3_action_data_getContentType($ext) {
  $types = [
    // テキスト系はスクリプトを実行しない形式で返す (XML系は text/plain)
    'csv' => 'text/csv; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'xml' => 'text/plain; charset=utf-8',
    'mmd' => 'text/plain; charset=utf-8',
    'mermaid' => 'text/plain; charset=utf-8',
    'drawio' => 'text/plain; charset=utf-8',
    'txt' => 'text/plain; charset=utf-8',
    'md' => 'text/plain; charset=utf-8',
    'ini' => 'text/plain; charset=utf-8',
    // バイナリ系
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip' => 'application/zip',
    'gz' => 'application/gzip',
    'bz2' => 'application/x-bzip2',
    'wav' => 'audio/wav',
    'mid' => 'audio/midi',
    'mp3' => 'audio/mpeg',
    'mp4' => 'video/mp4',
    'ogg' => 'audio/ogg',
  ];
  return isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
}



