<?php

/** ページの別名を指定します。
 * - [書式] !!alias(ジャンプ先)
 */

function kona3plugins_alias_execute($args)
{
    $page = trim(array_shift($args));
    if ($page === '') {
        return '';
    }
    $page_h = kona3text2html($page);
    $url = kona3getPageURL($page, 'show');
    return "<a href='$url'>$page_h</a>";
}
