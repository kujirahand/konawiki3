<?php
header('X-Frame-Options: SAMEORIGIN');

require_once KONA3_DIR_ENGINE . '/kona3login.inc.php';
require_once KONA3_DIR_ENGINE . '/kona3conf.inc.php';
require_once KONA3_DIR_ENGINE . '/kona3lib.inc.php';
require_once __DIR__ . '/editConf.inc.php';

// get admin user file
function kona3getFile_kona3adminuser_json_php()
{
    return KONA3_DIR_PRIVATE . '/kona3adminuser.json.php';
}

// default admin action
function kona3_action_admin()
{
    // check admin file
    $kona3adminuser_json_php = kona3getFile_kona3adminuser_json_php();
    if (file_exists($kona3adminuser_json_php)) {
        kona3_action_editConf();
        return;
    }
    // show admin page
    kona3setup_check_admin_user();
}

function kona3_404()
{
    header("404 not found");
    echo "<html><body><h1>404 not found:</h1><div><a href='index.php'>index</a></div></body></html>";
}


// Auto setup
function konawiki3_setup()
{
    kona3setup_check_kona3dir_def();
    kona3setup_check_dirs();
    kona3setup_check_admin_user();
    kona3setup_config();
}

function kona3setup_config()
{
    // editConfig page
    if (!kona3isAdmin()) {
        echo "<html><body><a href='index.php?go&login'>Please Login.</a></body></html>";
        exit;
    }
    $editTokenKey = 'kona3setup_config';
    $file_conf = KONA3_DIR_PRIVATE . '/kona3conf.json.php';
    $conf = jsonphp_load($file_conf, []);
    kona3conf_init($conf);
    // check arguments
    $q = empty($_REQUEST['q']) ? '' : $_REQUEST['q'];
    if ($q == '') {
        // show template
        $conf['edit_token'] = kona3_getEditToken($editTokenKey, TRUE);
        if (isset($_GET['admin'])) {
            $conf['admin_email'] = $_GET['admin'];
        }
        kona3template('admin_conf.html', $conf);
        exit;
    }
    if ($q == 'save') {
        // check token
        if (!kona3_checkEditToken($editTokenKey)) {
            $backMsg = lang('Please go back and resubmit the form.');
            $sessionMsg = lang("If it doesn't work no matter how many times you try, please initialize the session.");
            kona3error(
                lang("Invalid Token"),
                "<a href='javascript:history.back()'>{$backMsg}</a><br>{$sessionMsg}"
            );
            exit;
        } else {
            unset($conf['edit_key']);
        }
        // admin password?
        if (!empty($_POST['admin_pw1']) && !empty($_POST['admin_pw2']) && !empty($_POST['admin_email'])) {
            $userid = $_POST['admin_email'];
            $pw1 = $_POST['admin_pw1'];
            $pw2 = $_POST['admin_pw2'];
            if ($pw1 != $pw2) {
                kona3setup_error('The master passwords do not match. [<a href="javascript:history.back()">Back</a>]');
                exit;
            }
            kona3sestup_admin_write_pw($userid, $pw1);
        }
        unset($_POST['admin_pw1']); // unset admin_pw1 and admin_pw2
        unset($_POST['admin_pw2']);
        // save
        foreach ($conf as $key => $def) {
            $v = isset($_POST[$key]) ? $_POST[$key] : $def;
            if (is_string($v)) {
                $v = trim($v);
                if (strtolower($v) === 'true') {
                    $v = TRUE;
                }
                if (strtolower($v) === 'false') {
                    $v = FALSE;
                }
            }
            $conf[$key] = $v;
        }
        // check parameters
        if (strpos($conf['FrontPage'], '/') !== FALSE) {
            kona3setup_error('FrontPage could not include "/".');
            exit;
        }
        if (trim($conf['FrontPage']) == '') {
            $conf['FrontPage'] = 'FrontPage';
        }
        if (preg_match('#[^a-zA-Z0-9\_\-]#', $conf['skin'])) {
            kona3setup_error('Skin name could not include path flag "/" and special chars.');
            exit;
        }
        // save
        jsonphp_save($file_conf, $conf);
        kona3setup_showMessage('<h1>Saved</h1><p><a href="./index.php">Go to FrontPage.</a></p>');
        exit;
    }
    echo "unknown parameter [q]";
    exit;
}

function kona3setup_error($msg)
{
    if (function_exists('template_render')) {
        kona3showMessage('Error', $msg);
    } else {
        echo "<html><body><h1 style='color:red;'>$msg</h1></body></html>";
    }
    exit;
}
function kona3setup_showMessage($msg)
{
    if (function_exists('template_render')) {
        kona3showMessage('Setting', $msg);
    } else {
        echo "<html><body><div style='color:blue;'>$msg</div></body></html>";
    }
    exit;
}

function kona3sestup_admin_write_pw($userid, $pw)
{
    $file_kona3users_json = kona3getFile_kona3adminuser_json_php();
    $salt = konawiki3_gen_pw(255);
    $hash = kona3getHash($pw, $salt); // convert to hash
    jsonphp_save($file_kona3users_json, [
        $userid => [
            'hash' => $hash,
            'salt' => $salt,
        ]
    ]);
}

function kona3setup_check_admin_user()
{
    $file_kona3users_json = kona3getFile_kona3adminuser_json_php();
    if (file_exists($file_kona3users_json)) {
        return TRUE;
    }

    // check $q (setup mode)
    $q = empty($_POST['q']) ? '' : $_POST['q'];
    if ($q == '') {
        kona3template('admin_user.html', []);
        exit;
    }

    if ($q == 'save') {
        $pw = trim(empty($_POST['pw']) ? '' : $_POST['pw']);
        $pw2 = trim(empty($_POST['pw2']) ? '' : $_POST['pw2']);
        if ($pw != $pw2) {
            echo "The master passwords do not match.";
            exit;
        }
        $userid = trim(empty($_POST['email']) ? '' : $_POST['email']);
        kona3sestup_admin_write_pw($userid, $pw);
        kona3login($userid, $userid, 'admin', $userid);
        $userid_ = urldecode($userid);
        $success = lang('Success!');
        $FrontPageMsg = lang('Please access the `FrontPage`.');
        echo "<h1>{$success} <a href='index.php?admin={$userid_}'>$FrontPageMsg</a></h1>";
        exit;
    }
    echo 'q param error';
    exit;
}

// easy password
function konawiki3_gen_pw($no)
{
    $ch = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_#=|!%&+*@~.";
    $pw = "";
    for ($i = 0; $i < $no; $i++) {
        $pw .= substr($ch, rand(0, strlen($ch) - 1), 1);
    }
    return $pw;
}

function kona3setup_check_kona3dir_def()
{
    $file_kona3dir_def = KONA3_DIR_INDEX . '/kona3dir.def.php';
    if (!file_exists($file_kona3dir_def)) {
        if (is_writable(KONA3_DIR_INDEX)) {
            // auto generate kona3dir.def.php
            $tmp = file_get_contents(KONA3_DIR_ENGINE . '/template/template-kona3dir.def.php');
            file_put_contents($file_kona3dir_def, $tmp);
        }
    }
}

function kona3setup_check_dirs()
{
    if (!defined('KONA3_DIR_CACHE')) {
        define('KONA3_DIR_CACHE', dirname(__DIR__) . '/cache');
    }
    // check writable
    $dirs = [
        KONA3_DIR_CACHE,
        KONA3_DIR_PRIVATE,
        KONA3_DIR_DATA
    ];
    // check dirs
    $help_chmod = '';
    foreach ($dirs as $dir) {
        if (!is_writable($dir)) {
            $help_chmod .= "$ chmod +w \"$dir\"<br/>\n";
        }
    }
    // show help
    if ($help_chmod != '') {
        kona3_setup_help(
            "Please change directories permission.<br>" .
                "<div class='code'><code>{$help_chmod}</code></div>"
        );
        exit;
    }
    // check config error
    if (KONA3_DIR_PRIVATE == KONA3_DIR_DATA || strpos(KONA3_DIR_PRIVATE, KONA3_DIR_DATA) !== FALSE) {
        kona3_setup_help(
            "KONA3_DIR_PRIVATE and KONA3_DIR_DATA should be different directories.<br>" .
                "<div class='code'><code>KONA3_DIR_PRIVATE = " . KONA3_DIR_PRIVATE . "<br>" .
                "KONA3_DIR_DATA = " . KONA3_DIR_DATA . "</code></div>"
        );
        exit;
    }
}

// help function
function kona3_setup_help($msg)
{
    $url = 'https://kujirahand.com/konawiki3/index.php?install';
    echo '<!DOCTYPE html><html><body>';
    echo '<style>.code{ padding:0.5em; line-height:1.2em; font-size:1em; background-color: black; color:white; }</style>';
    echo "<h1>Please setup KonaWiki3.</h1>";
    echo "<p style='color:red;'>$msg</p>";
    echo "<p><a href='$url'>How to Install?</a></p>";
    echo '</body></html>';
    exit;
}
