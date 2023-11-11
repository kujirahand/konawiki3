<?php

/** 指定したページ一覧を取り込んで表示
 * - [USAGE] #page(pagename_list)
 * -- inlucde other pages (alias #include)
 * -- (ex) {{{#include(LF)name1(LF)name2(LF)name3(LF)}}}
 */

include_once dirname(__FILE__).'/include.inc.php';

function kona3plugins_page_execute($args) {
  return kona3plugins_include_execute($args);
}
