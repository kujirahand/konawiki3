<?php
require_once __DIR__ . '/test_common.inc.php';

// Render-level test for the edit_screen flag (issue #153, PR #341 review):
// with edit_screen=true the site chrome (hamburger etc.) must disappear
// from the rendered edit page, and with false it must stay.

global $kona3conf, $page;
$page = 'FrontPage';

function test_render_edit_html($edit_screen)
{
    global $kona3conf;
    $kona3conf['edit_screen'] = $edit_screen;
    $params = [
        'page' => 'FrontPage',
        'page_title' => 'FrontPage',
        'page_url' => kona3getPageURL('FrontPage'),
        'action' => kona3getPageURL('FrontPage', 'edit'),
        'edit_token' => 'dummy_token',
        'a_hash' => 'dummy_hash',
        'txt' => "* Test\nbody",
        'page_mode' => '',
        'page_id' => 0,
        'git_enabled' => false,
        'ai_enabled' => false,
        'tags' => '',
        'history' => [],
        'page_file' => '',
        'mtime' => 0,
    ];
    ob_start();
    kona3template('edit.html', $params);
    return ob_get_clean();
}

$html_on = test_render_edit_html(true);
test_assert(__LINE__, strpos($html_on, 'id="edit_topbar"') !== false, 'render: edit_screen=true shows the topbar (#153)');
test_assert(__LINE__, strpos($html_on, 'id="edit_drawer"') !== false, 'render: edit_screen=true shows the drawer (#153)');
test_assert(__LINE__, strpos($html_on, 'id="hamburger_icon"') === false, 'render: edit_screen=true hides the site header (#153)');
test_assert(__LINE__, strpos($html_on, 'FrontPage') !== false, 'render: edit_screen=true shows the page name (#153)');

$html_off = test_render_edit_html(false);
test_assert(__LINE__, strpos($html_off, 'id="hamburger_icon"') !== false, 'render: edit_screen=false keeps the site header (#153)');

// restore the default for other tests
$kona3conf['edit_screen'] = false;
