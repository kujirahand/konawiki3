<?php
define("KONAWIKI_VERSION", "3.3.15");

function kona3_version_int()
{
    // メジャーバージョンは省略して、マイナーバージョンとサブバージョンをつなげて返す
    // 3.3.11 => 311
    // 3.3.3  => 303
    $v = KONAWIKI_VERSION;
    $a = explode(".", $v);
    $v = $a[1] * 100 + $a[2];
    return intval($v);
}
