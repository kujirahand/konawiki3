<?php
// #112 カウンターの移行スクリプト
// https://github.com/kujirahand/konawiki3/issues/112
//
$base = dirname(__DIR__);
$infile = $base.'/data/.info.sqlite';
$outfile = $base.'/private/subdb.sqlite';

echo "[in] $infile\n[out] $outfile\n";

try {
    // ソースデータベースへの接続
    $sourceDb = new PDO("sqlite:$infile");
    $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 目的データベースへの接続
    $targetDb = new PDO("sqlite:$outfile");
    $targetDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // counter_monthテーブルのデータを取得
    $sourceCounterMonthStmt = $sourceDb->query('SELECT * FROM counter_month');
    $counterMonthData = $sourceCounterMonthStmt->fetchAll(PDO::FETCH_ASSOC);

    // counterテーブルのデータを取得
    $sourceCounterStmt = $sourceDb->query('SELECT * FROM counter');
    $counterData = $sourceCounterStmt->fetchAll(PDO::FETCH_ASSOC);

    // counter_monthテーブルのデータを挿入
    $targetDb->beginTransaction(); // トランザクション開始
    $targetDb->exec("DELETE FROM counter_month;"); // データを一旦削除
    $insertCounterMonthStmt = $targetDb->prepare('INSERT INTO counter_month (counter_id, page_id, year, month, value, mtime) VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($counterMonthData as $row) {
        print_r($row);
        $insertCounterMonthStmt->execute([$row['counter_id'], $row['page_id'], $row['year'], $row['month'], $row['value'], $row['mtime']]);
    }
    $targetDb->commit(); // コミット

    // counterテーブルのデータを挿入
    $targetDb->beginTransaction(); // トランザクション開始
    $targetDb->exec("DELETE FROM counter;"); // データを一旦削除
    $insertCounterStmt = $targetDb->prepare('INSERT INTO counter (page_id, value, mtime) VALUES (?, ?, ?)');
    foreach ($counterData as $row) {
        $insertCounterStmt->execute([$row['page_id'], $row['value'], $row['mtime']]);
    }
    $targetDb->commit(); // コミット

    echo "データ移行が完了しました。\n";

} catch (PDOException $e) {
    // エラーが発生した場合はロールバック
    if ($targetDb->inTransaction()) {
        $targetDb->rollBack();
    }
    print_r($e);
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
}


