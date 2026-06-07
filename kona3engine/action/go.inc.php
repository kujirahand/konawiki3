<?php

/** KonaWiki3 go */
function kona3_action_go()
{
    global $kona3conf;
    $url = kona3go_getRedirectURL($kona3conf['page']);
    header("location: $url");
    echo "<a href='$url'>JUMP</a>";
    exit;
}

function kona3go_getRedirectURL($page)
{
    $page = trim($page);
    if ($page === '') {
        return 'index.php';
    }
    return 'index.php?' . urlencode($page) . '&show';
}
