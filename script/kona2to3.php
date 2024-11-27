<?php
// konawikiのDBをテキストファイルに書き出す
if (count($argv) <= 2) {
    echo "[Usage] php kona2to3.php (konawiki2.db) (text_dir)\n";
    exit;
}
// 引数
$target_db = $argv[1];
$text_dir = $argv[2];

$text_dir = rtrim($text_dir, "/");
if (!file_exists($text_dir)) {
    mkdir($text_dir, 0777, true);
}
$db = new PDO("sqlite:{$target_db}");
$stmt = $db->query("select * from logs");
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    //print_r($r);
    $name = $r["name"];
    $body = $r["body"];
    $fname = "{$text_dir}/" . $name . ".txt";
    $dir = dirname($fname);
    if (substr($dir, 0, 2) === '..') { continue; }
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    echo $name . "---" . $fname . "\n";
    file_put_contents($fname, $body);
}
echo "ok.";
