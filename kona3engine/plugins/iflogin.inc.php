<?php
/**
 * - [書式] #iflogin(xxx)
 * - [引数]
 * -- code ... ログインの時だけ見えるメッセージ
 */
function kona3plugins_iflogin_execute($args) {
    $code = array_shift($args);
    if (kona3isLogin()) {
        $html = konawiki_parser_convert($code, FALSE);
        return "<!-- #iflogin -->{$html}<!-- end of #iflogin -->";
    }
    return "<!-- #iflogin - not login -->";
}

