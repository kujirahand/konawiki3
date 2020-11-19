<?php
// ----------------------------------------------------
// konawiki3.ini.php
// ----------------------------------------------------

// Basic
define("KONA3_WIKI_TITLE", "Konawiki3");
define("KONA3_WIKI_PRIVATE", TRUE); # TRUE or FALSE
define("KONA3_WIKI_SKIN", "def"); // def/single

// Admin Users "user1:password1,user2:password2"
define("KONA3_WIKI_USERS", "##WIKI_USERS##");
define("KONA3_PASSWORD_SALT", "6pD0wa#sU4!0[mo@z5rm"); // Please modify before add users
define("KONA3_ADMIN_EMAIL", ""); // Please set your email
define("KONA3_ALLOW_ADD_USER", FALSE);

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
define("KONA3_ALLPAGE_HEADER", ""); 
define("KONA3_ALLPAGE_FOOTER", "#comment"); // footer
define("KONA3_ENC_PAGENAME", FALSE); // 日本語名をURLエンコードするか


