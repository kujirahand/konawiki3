<?php
// PATH from script/setup-template.php
echo "<h1>This is old script. Plase rewrite `index.php`</h1>\n";
echo "<h1>古いスクリプトです。新しい`index.php`をコピーしてください。</h1>";
echo "<p><a href='https://github.com/kujirahand/konawiki3'>konawiki3</a></p>";
exit;




$DIR_ROOT = dirname(__DIR__);
$zip_dir = "{$DIR_ROOT}/cache/tmp";
$template_dir = "{$DIR_ROOT}/kona3engine/fw_simple";
$template_engine_lib = "{$template_dir}/fw_template_engine.lib.php";
// Need to download the template engine?
if (file_exists($template_engine_lib)) {
  echo "<h1>[OK] No need to setup template engine</h1>\n";
  echo "<h3><a href='../index.php'>Next Step</a></h3>";
  exit;
}
echo <<<EOS
<h1>Setup template path</h1>
<hr>
<table>
  <tr><th>root_dir</th><td>{$DIR_ROOT}</td></tr>
  <tr><th>cache/zip_dir</th><td>{$zip_dir}</td></tr>
  <tr><th>template_dir</th><td>{$template_dir}</td></tr>
</table>
<hr>
EOS;
echo "<h1>Template Downloader</h1>\n";
echo "".
  "<p>If \"Success\" is not displayed at the end, an error has occurred along the way.<br>" .
  "もし、最終的に【Success】と表示されなければ、途中でエラーが発生しています。その際は、繰り返し実行してください。</p>\n";
// Download ZIP file
$zip_url = "https://github.com/kujirahand/php_fw_simple/archive/refs/tags/1.0.zip";
$zip_file = __DIR__ . "/fw_simple.zip";
if (!file_exists($zip_file)) {
  echo "<h3>Downloading zip file</h3>\n";
  $bin = file_get_contents($zip_url);
  file_put_contents($zip_file, $bin);
}
echo "<h3>Unzip - 解凍します。</h3>\n";
// $zip = new ZipArchive();
// $zip->open($zip_file);
// $zip->extractTo($zip_dir);
// $zip->close();
system("unzip \"{$zip_file}\" -d \"{$zip_dir}\"");
echo "<h3>Move - 該当パスに移動します</h3>\n";
$src = $zip_dir . "/php_fw_simple-1.0";
$to = $template_dir;
echo "src: $src<br>des: $to<br>\n";
rename($src, $to);
echo "<hr>\n";
echo "<p>【Success】The template setup has been completed.</p>\n";
echo "<h3><a href='../index.php'>Next Step - 次にこちらをクリック</a></h3>\n";
