<?php
// セットアップの必要があるかどうか確認する
$target = dirname(__DIR__) . "/kona3engine/fw_simple/fw_template_engine.lib.php";
if (file_exists($target)) {
  echo "<h1>【OK】No need to download / セットアップは不要です。</h1>\n";
  echo "<h3><a href='../index.php'>Next Step</a></h3>";
  exit;
}
echo "<h1>Downloader - テンプレートをダウンロードします</h1>\n";
echo "<div>(memo)もし、最終的に【完了】と表示されなければ、途中でエラーが発生しています。<br>\n";
echo "その際は、繰り返し実行してください。</div>\n";
// ZIPファイルをダウンロード
$zip_url = "https://github.com/kujirahand/php_fw_simple/archive/refs/tags/1.0.zip";
$zip_file = __DIR__ . "/fw_simple.zip";
if (!file_exists($zip_file)) {
  echo "<h3>Downloading zip file</h3>\n";
  $bin = file_get_contents($zip_url);
  file_put_contents($zip_file, $bin);
}
$zip_dir = __DIR__ . "/tmp";
echo "<h3>Unzip - 解凍します。</h3>\n";
$zip = new ZipArchive();
$zip->open($zip_file); // 展開したい zip ファイルを指定します
$zip->extractTo($zip_dir); // 展開先のディレクトリを指定します
$zip->close();
echo "<h3>Move - 該当パスに移動します</h3>\n";
$src = $zip_dir . "/php_fw_simple-1.0";
$to = dirname(__DIR__) . "/kona3engine/fw_simple";
echo "src: $src<br>des: $to<br>\n";
rename($src, $to);
echo "【完了】The template setup has been completed.\n";
echo "<h3><a href='../index.php'>Next Step - 次にこちらをクリック</a></h3>";
