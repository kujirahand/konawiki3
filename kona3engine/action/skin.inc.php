<?php
include_once dirname(dirname(__FILE__)).'/kona3lib.inc.php';

function kona3_action_skin() {
  global $kona3conf;
  $fname = $kona3conf["page"];

  // check filename
  if (!preg_match('#^[a-zA-Z0-9\_\%][a-zA-Z0-9\_\.\-\%]+$#', $fname)) {
    header('HTTP/1.0 404 Not Found');
    echo "File not found.\n";
    exit;
  }
  
  // get extension
  $ext = "";
  if (preg_match('/(\.\w+?)$/', $fname, $m)) {
    $ext = $m[1];
  }
  $skin = $kona3conf['skin'];
  // 設定中以外のスキンのファイル (派生スキンが土台にする def のCSSなど)
  if (isset($_GET['s']) && is_string($_GET['s'])) {
    $skin = $_GET['s'];
  }
  // check skin name
  if (!preg_match('#^[a-zA-Z0-9\_\-]+$#', $skin)) {
    header('HTTP/1.0 404 Not Found');
    echo "404 Not Found - skin conf error.\n";
    exit;
  }
  $skin_dir = KONA3_DIR_SKIN;
  $res_dir = $kona3conf['path_resource'];
  
  // check skin dir
  $path = "$skin_dir/$skin/$fname";
  if (!file_exists($path)) {
    $path = "$skin_dir/def/$fname";
    if (!file_exists($path)) {
      $path = "$res_dir/$fname";
      if (!file_exists($path)) {
        header('HTTP/1.0 404 Not Found');
        echo "File not found.";
        exit;
      }
    }
  }
  // check ext
  $ext = strtolower($ext);
  $ctype = "text/plain";
  if ($ext == ".css") $ctype = "text/css";
  else if ($ext == ".js") $ctype = "text/javascript";
  else if ($ext == ".png") $ctype = "image/png";
  else if ($ext == ".jpg" || $ext == ".jpeg" || $ext == ".jpe") $ctype = "image/jpeg";
  else if ($ext == ".gif") $ctype = "image/gif";
  else if ($ext == ".ico") $ctype = "image/png";
  else if ($ext == ".json") $ctype = "application/json";
  // output header
  $cache_expire_time = 60 * 60 * 3;
  header("Content-Type: $ctype");
  header("Cache-Control: max-age={$cache_expire_time}");
  header("Expires: ".gmdate('D, d M Y H:i:s \G\M\T', time() + $cache_expire_time));
  $last_m = gmdate('D, d M Y H:i:s \G\M\T', filemtime($path));
  header("Last-Modified: ". $last_m);
  // CSSには情報コメントを付けるため、Content-Lengthは実際に出力する長さにする
  // (ファイルサイズを送ると、コメントの分だけ末尾が切り捨てられる)
  $body = kona3_skin_buildBody($path, $ext, $fname, $skin, $last_m);
  header("Content-Length: ".strlen($body));
  // output contents
  echo $body;
}

// スキンファイルの出力内容を作る
function kona3_skin_buildBody($path, $ext, $fname, $skin, $last_m) {
  $body = file_get_contents($path);
  if ($ext == ".css") {
    $info = "/**\n";
    $info .= "+[konawiki3]\n";
    $info .= "| file: {$fname}\n";
    $info .= "| skin: {$skin}\n";
    $info .= "| mtime: {$last_m}\n";
    $info .= "*/\n";
    $info .= "\n";
    $body = $info . $body;
  }
  return $body;
}



