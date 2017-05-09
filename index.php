<?php

// set config
define("KONA3_WIKI_TITLE", "Konawiki3");
define("KONA3_WIKI_USERS", "kona3:pass3,kona2:pass2"); # admin users
define("KONA3_WIKI_PRIVATE", true); # true or false
define("KONA3_DIR_DATA", dirname(__FILE__).'/data');
define("KONA3_DIR_PRIVATE", dirname(__FILE__).'/private');
define("KONA3_DSN", "sqlite:".KONA3_PRIVATE_DIR."/data.sqlite");
define("KONA3_ALLPAGE_FOOTER", "#comment");
// Include kona3engine/index.inc.php
include "kona3engine/index.inc.php";

