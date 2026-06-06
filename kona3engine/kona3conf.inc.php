<?php

/**
 * KonaWiki3 - kona3conf.inc.php
 */

include_once __DIR__ . '/kona3lib.inc.php';
include_once __DIR__ . '/php_fw_simple/fw_database.lib.php';
include_once __DIR__ . '/kona3db.inc.php';

// --------------------
// Initialize config
// --------------------
function kona3conf_init()
{
    global $kona3conf;
    if (empty($kona3conf)) {
        $kona3conf = [];
    }
    kona3conf_setPHPConf();
    kona3conf_setWikiConf($kona3conf);
    kona3conf_checkDirs();
    kona3conf_setURI();
    kona3conf_setHeaderFooter();
    kona3conf_setTemplateEngine();
    kona3conf_setDatabase();
}

function kona3conf_setPHPConf()
{
    // Charset Setting
    mb_internal_encoding("UTF-8");
    mb_detect_encoding("UTF-8,SJIS,EUC-JP,JIS,ASCII");
    // Web interface
    if (php_sapi_name() !== 'cli') {
        ini_set('default_charset', 'UTF-8');
        header("Content-Type: text/html; charset=UTF-8");
    }
}

function kona3conf_setWikiConf(&$kona3conf)
{
    // default settings
    $defaultConf = kona3conf_getDefaultValues();
    $systemDefaultConf = [
        'KONAWIKI_VERSION' => KONAWIKI_VERSION,
        'plugin_alias' => [
            "画像" => "ref",
            "なでしこ" => "nako3",
        ],
    ];
    $defaultConf = array_merge($defaultConf, $systemDefaultConf);
    kona3conf_setDefault($kona3conf, $defaultConf);

    // -------------------------------------------
    // plugin diallow
    // -------------------------------------------
    $plugin_disallow_kv = [];
    $plugin_disallow_a = explode(",", $kona3conf['plugin_disallow']);
    foreach ($plugin_disallow_a as $name) {
        $plugin_disallow_kv[$name] = TRUE;
    }
    $kona3conf["plugin.disallow"] = $plugin_disallow_kv;
}

function kona3conf_getConfigItems()
{
    return [
        'Basic' => [
            'wiki_title' => ['label' => 'Wiki Title', 'default' => 'KonaWiki3', 'type' => 'string'],
            'session_name' => ['label' => 'Session Name', 'default' => 'kona3session', 'type' => 'string'],
            'admin_email' => ['label' => 'Admin Email', 'default' => 'admin@example.com', 'type' => 'string'],
            'wiki_private' => ['label' => 'Wiki Private', 'default' => TRUE, 'type' => 'bool'],
            'lang' => ['label' => 'Language', 'default' => 'ja', 'type' => 'select', 'items' => ['ja', 'en']],
            'skin' => ['label' => 'Skin name', 'default' => 'def', 'type' => 'select', 'items' => kona3conf_getSkinItems()],
            'allow_add_user' => ['label' => 'Allow Add User', 'default' => FALSE, 'type' => 'bool'],
            'def_text_ext' => ['label' => 'Default Text Ext', 'default' => 'txt', 'type' => 'select', 'items' => ['txt', 'md']],
            'bbs_admin_password' => ['label' => 'BBS Admin Password', 'default' => 'BBS#admin!PassWord!!', 'type' => 'string'],
        ],
        'Header/Footer' => [
            'allpage_header' => ['label' => 'All Page Header', 'default' => '', 'type' => 'string', 'note' => 'wiki text'],
            'allpage_footer' => ['label' => 'All Page Footer', 'default' => '', 'type' => 'string', 'note' => 'wiki text'],
            'analytics' => ['label' => 'Analytics Code', 'default' => '', 'type' => 'string', 'note' => 'html'],
        ],
        'Options' => [
            'FrontPage' => ['label' => 'FrontPage Name', 'default' => 'FrontPage', 'type' => 'string'],
            'plugin_disallow' => ['label' => 'Disabled Plugins', 'default' => 'html,htmlshow,filelist', 'type' => 'string', 'note' => "delimiter=','"],
            'show_counter' => ['label' => 'Show Counter', 'default' => FALSE, 'type' => 'bool'],
            'show_data_dir' => ['label' => 'Show Data Directory', 'default' => FALSE, 'type' => 'bool'],
            'data_dir_allow_pattern' => [
                'label' => 'Data Directory Allow Pattern',
                'default' => '(csv|json|xml|doc|docx|xls|xlsx|ppt|pptx|pdf|zip|gz|bz2|wav|mid|mp3|mp4|ogg|mmd|mermaid|yaml|yml)',
                'type' => 'string',
                'note' => 'Specify a pattern excluding images',
            ],
            'noanchor' => ['label' => 'No Anchor', 'default' => FALSE, 'type' => 'bool'],
            'enc_pagename' => ['label' => 'Encode Page Name', 'default' => FALSE, 'type' => 'bool'],
            'use_pdf_out' => ['label' => 'Use PDF Output', 'default' => FALSE, 'type' => 'bool'],
            'para_enabled_br' => ['label' => 'Paragraph BR', 'default' => TRUE, 'type' => 'bool'],
            'path_max_mkdir' => ['label' => 'Path Max Mkdir', 'default' => 3, 'type' => 'number', 'note' => '0:off, 1,2,3...:on'],
            'chmod_mkdir' => ['label' => 'Chmod Mkdir', 'default' => '0777', 'type' => 'string'],
            'max_edit_size' => ['label' => 'Max Edit Size', 'default' => 3, 'type' => 'number', 'note' => '0:no limit, unit=MB'],
            'max_search' => ['label' => 'Max Search', 'default' => 10, 'type' => 'number'],
            'max_search_level' => ['label' => 'Max Search Level', 'default' => 2, 'type' => 'number'],
        ],
        'Uploader' => [
            'image_pattern' => ['label' => 'Image Pattern', 'default' => '(png|jpg|jpeg|gif|bmp|ico|svg|webp)', 'type' => 'string'],
            'allow_upload' => ['label' => 'Allow Upload', 'default' => FALSE, 'type' => 'bool'],
            'allow_upload_ext' => [
                'label' => 'Allow Upload Ext',
                'default' => 'txt;md;pdf;csv;wav;mid;mp3;mp4;ogg;zip;gz;bz2;jpg;jpeg;png;gif;webp;svg;xml;json;ini;doc;docx;xls;xlsx;ppt;pptx',
                'type' => 'string',
            ],
            'upload_max_file_size' => ['label' => 'Upload Max File Size', 'default' => 1024 * 1024 * 5, 'type' => 'number', 'note' => 'bytes'],
        ],
        'AI' => [
            'openai_apikey' => ['label' => 'OpenAI API Key', 'default' => '', 'type' => 'string', 'note' => 'for ChatGPT'],
            'openai_apikey_model' => ['label' => 'OpenAI API Model', 'default' => 'gpt-4o-mini', 'type' => 'select', 'items' => ['gpt-4o-mini', 'gpt-4o']],
            'openai_api_basic_instruction' => ['label' => 'OpenAI Basic Instruction', 'default' => 'You are helpful AI assistant.', 'type' => 'string'],
            'mermaid_cli' => ['label' => 'Mermaid CLI', 'default' => '', 'type' => 'string'],
        ],
        'Discord' => [
            'discord_webhook_url' => ['label' => 'Discord Webhook URL', 'default' => '', 'type' => 'string'],
        ],
        'Git' => [
            'git_enabled' => ['label' => 'Git Enabled', 'default' => FALSE, 'type' => 'bool'],
            'git_branch' => ['label' => 'Git Branch', 'default' => 'main', 'type' => 'string'],
            'git_remote_repository' => ['label' => 'Git Remote Repository', 'default' => 'origin', 'type' => 'string'],
        ],
    ];
}

function kona3conf_getDefaultValues()
{
    $defaults = [];
    foreach (kona3conf_getConfigItems() as $items) {
        foreach ($items as $key => $item) {
            $defaults[$key] = $item['default'];
        }
    }
    return $defaults;
}

function kona3conf_getFlatConfigItems()
{
    $flatItems = [];
    foreach (kona3conf_getConfigItems() as $items) {
        foreach ($items as $key => $item) {
            $flatItems[$key] = $item;
        }
    }
    return $flatItems;
}

function kona3conf_getConfigFormItems($conf)
{
    $categories = kona3conf_getConfigItems();
    foreach ($categories as &$items) {
        foreach ($items as $key => &$item) {
            $item['name'] = $key;
            if (!isset($item['note'])) {
                $item['note'] = '';
            }
            $value = array_key_exists($key, $conf) ? $conf[$key] : $item['default'];
            $value = kona3conf_normalizeConfigValue($value, $item);
            $item['input_html'] = kona3conf_renderConfigInput($key, $item, $value);
        }
    }
    return $categories;
}

function kona3conf_renderConfigInput($key, $item, $value)
{
    $type = isset($item['type']) ? $item['type'] : 'string';
    $id = 'conf_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    $name = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
    $idEsc = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    if ($type === 'bool') {
        $selectedTrue = $value ? ' selected' : '';
        $selectedFalse = $value ? '' : ' selected';
        return "<select id=\"{$idEsc}\" name=\"{$name}\">" .
            "<option value=\"true\"{$selectedTrue}>true</option>" .
            "<option value=\"false\"{$selectedFalse}>false</option>" .
            "</select>";
    }
    if ($type === 'select') {
        $items = isset($item['items']) ? $item['items'] : [];
        $valueStr = (string)$value;
        if (!in_array($valueStr, $items, TRUE)) {
            $items[] = $valueStr;
        }
        $html = "<select id=\"{$idEsc}\" name=\"{$name}\">";
        foreach ($items as $option) {
            $optionStr = (string)$option;
            $optionEsc = htmlspecialchars($optionStr, ENT_QUOTES, 'UTF-8');
            $selected = ($optionStr === $valueStr) ? ' selected' : '';
            $html .= "<option value=\"{$optionEsc}\"{$selected}>{$optionEsc}</option>";
        }
        $html .= '</select>';
        return $html;
    }
    $inputType = ($type === 'number') ? 'number' : 'text';
    $valueEsc = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    return "<input type=\"{$inputType}\" id=\"{$idEsc}\" name=\"{$name}\" value=\"{$valueEsc}\">";
}

function kona3conf_normalizeConfigValue($value, $item)
{
    $type = isset($item['type']) ? $item['type'] : 'string';
    if (is_string($value)) {
        $value = trim($value);
    }
    if ($type === 'bool') {
        if (is_bool($value)) {
            return $value;
        }
        $valueLower = strtolower((string)$value);
        return in_array($valueLower, ['1', 'true', 'on', 'yes'], TRUE);
    }
    if ($type === 'number') {
        if ($value === '') {
            return $item['default'];
        }
        if (is_numeric($value)) {
            $number = (float)$value;
            return floor($number) == $number ? (int)$number : $number;
        }
        return $item['default'];
    }
    if ($type === 'select') {
        $valueStr = (string)$value;
        $items = isset($item['items']) ? $item['items'] : [];
        return in_array($valueStr, $items, TRUE) ? $valueStr : $item['default'];
    }
    return $value;
}

function kona3conf_getSkinItems()
{
    static $skinItems = NULL;
    if ($skinItems !== NULL) {
        return $skinItems;
    }
    $skins = ['def'];
    if (defined('KONA3_DIR_SKIN') && is_dir(KONA3_DIR_SKIN)) {
        $dirs = scandir(KONA3_DIR_SKIN);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            if (is_dir(KONA3_DIR_SKIN . '/' . $dir) && preg_match('/^[a-zA-Z0-9_\-]+$/', $dir)) {
                $skins[] = $dir;
            }
        }
    } else {
        $skins = array_merge($skins, ['single', 'nako3']);
    }
    $skins = array_values(array_unique($skins));
    sort($skins);
    $skinItems = $skins;
    return $skinItems;
}

function check_conf(&$conf, $key, $def)
{
    if (!isset($conf[$key])) {
        $conf[$key] = $def;
    }
}

function kona3conf_setDefault(&$kona3conf, $defaultValues)
{
    foreach ($defaultValues as $key => $value) {
        if (!isset($kona3conf[$key])) {
            $kona3conf[$key] = $value;
        }
    }
}

function kona3conf_checkDirs()
{
    // Directories
    if (!defined('KONA3_DIR_DATA')) {
        define('KONA3_DIR_DATA', dirname(__DIR__) . '/data');
    }
    if (!defined('KONA3_DIR_PRIVATE')) {
        define('KONA3_DIR_PRIVATE', dirname(__DIR__) . '/private');
    }
    if (!defined('KONA3_DIR_SKIN')) {
        define('KONA3_DIR_SKIN', dirname(__DIR__) . '/skin');
    }
    if (!defined('KONA3_DIR_CACHE')) {
        define('KONA3_DIR_CACHE', dirname(__DIR__) . '/cache');
    }
    if (!defined('KONA3_PAGE_ID_JSON')) {
        define("KONA3_PAGE_ID_JSON", KONA3_DIR_DATA . "/.kona3_page_id.json");
    }
}

function kona3conf_setURI()
{
    global $kona3conf;

    // robots
    if ($kona3conf['wiki_private']) {
        $kona3conf["robots"] = 'noindex';
    } else {
        $kona3conf["robots"] = ''; // or 'index,follow' (default is '')
    }

    // path
    $baseurl = ".";

    // URL
    $scheme = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/index.php';

    // go.php?
    if (basename($script) == 'go.php') {
        $script = dirname($script) . '/index.php';
    }

    // set default
    $urlValues = [
        'url.index' => "{$scheme}://{$host}{$script}",
        'url.data' => './data',
        'url.pub' => './pub',
        'path_resource' => KONA3_DIR_ENGINE . '/resource',
        'scriptname' => 'index.php',
    ];
    kona3conf_setDefault($kona3conf, $urlValues);

    // check
    $url_data = $kona3conf["url.data"];
    if (substr($url_data, strlen($url_data) - 1, 1) == '/') {
        $kona3conf["url.data"] = substr($url_data, 0, strlen($url_data) - 1);
    }
}

function kona3conf_setHeaderFooter()
{
    global $kona3conf;

    // javascript files
    check_conf($kona3conf, "header.tags", []); // additional header
    check_conf($kona3conf, 'js', [
        kona3getResourceURL('ajax_qq.js'),
        kona3getSkinURL('drawer.js', TRUE),
    ]);

    // css files
    check_conf($kona3conf, "css", [
        kona3getResourceURL('pure-min.css'),
        kona3getResourceURL('grids-responsive-min.css'),
        kona3getResourceURL('kona3def.css', TRUE), // default style
        kona3getResourceURL('darkmode.css', TRUE), // dark mode styles
        kona3getSkinURL('kona3.css', TRUE), // skin style
        kona3getSkinURL('drawer.css', TRUE),
    ]);

    // analytics
    if ($kona3conf['analytics'] != '') {
        $kona3conf['header.tags'][] = $kona3conf['analytics'];
    }
}

function kona3conf_setTemplateEngine()
{
    global $kona3conf;

    // Template engine
    global $DIR_TEMPLATE;
    global $DIR_TEMPLATE_CACHE;
    global $FW_TEMPLATE_PARAMS;
    global $FW_ADMIN_EMAIL;
    $DIR_TEMPLATE = KONA3_DIR_ENGINE . '/template';
    $DIR_TEMPLATE_CACHE = KONA3_DIR_CACHE;
    $FW_ADMIN_EMAIL = $kona3conf['admin_email'];
}

function kona3conf_setDatabase()
{
    $DIR_TEMPLATE = KONA3_DIR_ENGINE . '/template';

    // Database library
    $file_info_sqlite = KONA3_DIR_PRIVATE . '/info.sqlite';
    $old_file_info_sqlite = KONA3_DIR_DATA . '/.info.sqlite';
    if (!file_exists($file_info_sqlite)) { // rename old file
        // 古いDBファイルがあれば、新しいパスに移動する
        if (file_exists($old_file_info_sqlite)) {
            // ファイルを異動する前にpage_idを移行する
            kona3_conf_read_old_page_ids($old_file_info_sqlite);
            @kona3db_getPageId(kona3getConf("FrontPage"), FALSE);
            // ファイルを移動する
            @rename($old_file_info_sqlite, $file_info_sqlite);
        }
    }
    // main database
    database_set(
        $file_info_sqlite, // DB file path
        $DIR_TEMPLATE . '/info.sql', // SQL file path
        'main' // Name of DB
    );
    // subdb database
    database_set(
        KONA3_DIR_PRIVATE . '/subdb.sqlite',
        $DIR_TEMPLATE . '/subdb.sql',
        'subdb'
    );
    try {
        database_get('main');
    } catch (Exception $e) {
        echo "<pre><h1>DB ERROR</h1>\n";
        echo "[FILE] $file_info_sqlite\n";
        echo "<p><a href='https://github.com/kujirahand/konawiki3'>Please setup konawiki3.</a></p>";
        if (!is_dir(KONA3_DIR_PRIVATE)) {
            echo "<p>Please make `private` dir.</p>";
        }
        throw $e;
    }
}

// (旧v.3.3.11以前のDB対応) データベースファイルがあればそれを読み込む
function kona3_conf_read_old_page_ids($old_db_path)
{
    $kona3pageIds = [];
    // v3.3.11 以前のバージョンのデータベース
    if (file_exists($old_db_path)) {
        // load
        $pdo = new PDO("sqlite:$old_db_path");
        $r = $pdo->query("SELECT * FROM pages"); // 自動的にJSONに移行
        $kona3pageIds = [];
        foreach ($r as $v) {
            // ファイルの存在チェック
            $file = koan3getWikiFileText($v['name']);
            if (file_exists($file)) {
                // echo $v['name'] . " => " . $v['page_id'] . "\n";
                $kona3pageIds[$v['name']] = $v['page_id'];
            }
        }
        $pdo = null;
        // save
        kona3lock_save(KONA3_PAGE_ID_JSON, json_encode($kona3pageIds, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    return $kona3pageIds;
}
