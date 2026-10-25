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
  // check filename
  if (!preg_match('#^[a-zA-Z0-9\_\%][a-zA-Z0-9\_\.\-\%\/]+$#', $fname)) {
    header('HTTP/1.0 404 Not Found');
    echo $fileNotFound;
    exit;
  }
  // remove ".."
  $fname = str_replace('..', '', $fname);
  // check data dir
  $fullpath = KONA3_DIR_DATA."/$fname";
  if (!file_exists($fullpath)) {
    header('HTTP/1.0 404 Not Found');
    echo $fileNotFound;
    exit;
  }
  // image
  $image_type = kona3getConf("image_pattern", "(jpg|jpeg|png|gif|ico|svg|webp)");
  $pattern = "#\.($image_type)$#";
  if (preg_match($pattern, $fname, $m)) {
    $ctype = "image/".$m[1];
    if ($ctype == 'image/svg') {
      $ctype = 'image/svg+xml';
    }
    header("Content-Type: $ctype");
    header("Content-Length: ".filesize($fullpath));
    header("X-Content-Type-Options: nosniff");
    if (strtolower($m[1]) === 'svg') {
      // SVGはスクリプトを持てるため、トップレベル文書として開かれても
      // Wikiのオリジンで実行されないよう sandbox を付ける (#324)。
      header("Content-Security-Policy: sandbox");
    }
    echo file_get_contents($fullpath);
    exit;
  }
  // data_dir_allow_pattern
  $allow_pattern = kona3getConf(
    "data_dir_allow_pattern",
    "(csv|json|xml|doc|docx|xls|xlsx|ppt|pptx|pdf|zip|gz|bz2|wav|mid|mp3|mp4|ogg|mmd|mermaid|drawio)");
  $pattern = "#\.($allow_pattern)$#";
  if (!preg_match($pattern, $fname)) {
    header('HTTP/1.0 415 Unsupported Media Type');
    echo $fileNotAllowed;
    exit;
  }
  // output contents
  // ファイルの中身からMIMEを判定すると、HTMLやSVGを書いた .csv / .xml が
  // text/html や image/svg+xml として配信され、Wikiのオリジンでスクリプトが
  // 動いてしまう。拡張子から固定のMIMEを決め、スニッフィングも禁止する (#324)。
  $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
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



