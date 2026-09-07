<?php
// DATABASE
global $FW_DB_MAIN; // PDOオブジェクトの配列
global $FW_DB_INFO; // 設定
$FW_DB_INFO = [];

function database_set($file_db, $file_sql, $dbname = 'main')
{
    global $FW_DB_INFO;
    $FW_DB_INFO[$dbname] = [
        'file_db' => $file_db,
        'file_sql' => $file_sql,
        'handle' => null,
    ];
}

function database_get($dbname = 'main')
{
    global $FW_DB_INFO;

    // 既にオープンしたか確認
    if (isset($FW_DB_INFO[$dbname]['handle']) && $FW_DB_INFO[$dbname]['handle']) {
        return $FW_DB_INFO[$dbname]['handle'];
    }

    // Check info
    if (empty($FW_DB_INFO[$dbname]['file_db'])) {
        echo '<h1>[ERROR] Database not set.</h1>';
        exit;
    }
    // Open
    $file_db = $FW_DB_INFO[$dbname]['file_db'];
    $file_sql = $FW_DB_INFO[$dbname]['file_sql'];
    // for konawiki2.2.x
    $file_db = preg_replace('#^(pdosqlite|sqlite)\:#', '', $file_db);
    $need_init = false;
    if (! file_exists($file_db)) {
        $need_init = true;
    }
    $pdo = new PDO('sqlite:' . $file_db);
    $FW_DB_INFO[$dbname]['handle'] = $pdo;
    // エラーで例外を投げる
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 連想配列を返す
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // 複数読み取り+単一書き込みを同時許可し、書き込み競合時は例外を投げる前に待機させる
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA busy_timeout=5000');
    // 生成
    if ($need_init) {
        $sql = file_get_contents($file_sql);
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            echo "<pre><h1>DB CREATED ERROR : $dbname</h1>";
            print_r($e);
            unlink($file_db);
            exit;
        }
    }
    return $pdo;
}

// SQLITE_BUSY(database is locked)時に指数バックオフで再試行する
function db_retry_busy($fn, $max_retries = 4)
{
    $attempt = 0;
    while (true) {
        try {
            return $fn();
        } catch (PDOException $e) {
            $is_busy = (stripos($e->getMessage(), 'database is locked') !== false)
                || (stripos($e->getMessage(), 'SQLITE_BUSY') !== false);
            $attempt++;
            if (! $is_busy || $attempt > $max_retries) {
                throw $e;
            }
            usleep((int)(50000 * (2 ** ($attempt - 1)))); // 50ms, 100ms, 200ms, 400ms...
        }
    }
}

function db_begin($dbname = 'main')
{
    $db = database_get($dbname);
    return db_retry_busy(function () use ($db) {
        $db->beginTransaction();
        return $db;
    });
}

function db_commit($dbname = 'main')
{
    $db = database_get($dbname);
    $db->commit();
    return $db;
}

function db_rollback($dbname = 'main')
{
    $db = database_get($dbname);
    $db->rollback();
    return $db;
}

function db_exec($sql, $params = array(), $dbname = 'main')
{
    $db = database_get($dbname);
    return db_retry_busy(function () use ($db, $sql, $params) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $db;
    });
}

function db_insert($sql, $params = array(), $dbname = 'main')
{
    $db = database_get($dbname);
    return db_retry_busy(function () use ($db, $sql, $params) {
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        if ($result) {
            $id = $db->lastInsertId();
            return $id;
        }
        return 0;
    });
}

function db_get($sql, $params = array(), $dbname = 'main')
{
    $db = database_get($dbname);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $r = $stmt->fetchAll();
    return $r;
}

function db_get1($sql, $params = array(), $dbname = 'main')
{
    $db = database_get($dbname);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $r = $stmt->fetch();
    return $r;
}

function db_table_exists($table, $dbname = 'main')
{
    $r = db_get1(
        "SELECT * FROM sqlite_master " .
            "WHERE type='table' AND name=?",
        [$table],
        $dbname
    );
    return (isset($r['name']));
}
