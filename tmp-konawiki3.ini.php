<?php
// ----------------------------------------------------
// konawiki3.ini.php
// ----------------------------------------------------

// Basic
define("KONA3_WIKI_TITLE", "Konawiki3");
define("KONA3_WIKI_PRIVATE", TRUE); # TRUE or FALSE
define("KONA3_WIKI_SKIN", "def"); // def/single

// Admin Users "user1:password1,user2:password2"
define("KONA3_WIKI_USERS", "username:password,username2:password2");

// Directories
define("KONA3_DIR_ROOT", __DIR__);
define("KONA3_DIR_ENGINE", __DIR__.'/kona3engine');
define("KONA3_DIR_DATA", __DIR__.'/data');
define("KONA3_DIR_PRIVATE", __DIR__.'/private');
define("KONA3_DIR_CACHE", __DIR__.'/cache');
// Files
define("KONA3_DSN", "sqlite:".KONA3_DIR_PRIVATE."/data.sqlite");

// Setting
define("KONA3_SHOW_DATA_DIR", FALSE); // show local path 
define("KONA3_ALLPAGE_FOOTER", "#comment"); // footer
define("KONA3_ENC_PAGENAME", FALSE); // 日本語名をURLエンコードするか


