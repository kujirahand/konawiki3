<?php
// ----------------------------------------------------
// konawiki3.ini.php
// ----------------------------------------------------

// Basic
define("KONA3_WIKI_TITLE", "Konawiki3");
define("KONA3_WIKI_PRIVATE", TRUE); # TRUE or FALSE
define("KONA3_WIKI_SKIN", "def"); // def/single

// Users
define("KONA3_WIKI_USERS", "kona3:pass3,kona2:pass2"); # admin users

// Directories
define("KONA3_DIR_ENGINE", dirname(__FILE__).'/kona3engine');
define("KONA3_DIR_DATA", dirname(__FILE__).'/data');
define("KONA3_DIR_PRIVATE", dirname(__FILE__).'/private');
// Files
define("KONA3_DSN", "sqlite:".KONA3_DIR_PRIVATE."/data.sqlite");

// Setting
define("KONA3_SHOW_DATA_DIR", FALSE); // show local path 
define("KONA3_ALLPAGE_FOOTER", "#comment"); // footer
define("KONA3_ENC_PAGENAME", FALSE); // 日本語名をURLエンコードするか


