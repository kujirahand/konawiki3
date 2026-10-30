<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/edit.inc.php';

// Test for the live preview (issue #153, decided design: Zen Drawer +
// preview side-by-side mode, full-width fallback on narrow screens)

$root = dirname(__DIR__);
$edit_php = file_get_contents($root . '/action/edit.inc.php');
$edit_html = file_get_contents($root . '/template/edit.html');
$edit_js = file_get_contents($root . '/resource/edit.js');
$edit_css = file_get_contents($root . '/resource/edit.css');

// --- PHP: endpoint wiring ---
test_assert(__LINE__, function_exists('kona3edit_render_preview'), 'preview: render helper exists (#153)');
test_assert(__LINE__, function_exists('kona3edit_preview_ajax'), 'preview: ajax endpoint exists (#153)');
test_assert(__LINE__, strpos($edit_php, "\$q == 'preview'") !== false, 'preview: q=preview is dispatched (#153)');
test_assert(__LINE__, strpos($edit_php, 'kona3edit_checkEditToken($page, \'ajax\')') !== false, 'preview: endpoint checks the edit token (#153)');

// --- PHP: rendering follows the show pipeline ---
$html = kona3edit_render_preview("* 見出しテスト\n本文です", 'KonaNotation');
test_assert(__LINE__, strpos($html, '見出しテスト') !== false, 'preview: KonaNotation renders the heading text (#153)');
test_assert(__LINE__, strpos($html, '<h') !== false, 'preview: KonaNotation renders heading HTML (#153)');
$html_md = kona3edit_render_preview("# Markdown Title\n\nbody", 'Markdown');
test_assert(__LINE__, strpos($html_md, 'Markdown Title') !== false, 'preview: Markdown renders the title (#153)');
test_assert(__LINE__, strpos($html_md, '<h1') !== false, 'preview: Markdown renders h1 (#153)');
// raw HTML in the source stays escaped by the parser (same as show)
$html_xss = kona3edit_render_preview("<script>alert(1)</script>", 'KonaNotation');
test_assert(__LINE__, strpos($html_xss, '<script>alert(1)</script>') === false, 'preview: raw script tag is not passed through (#153)');

// --- HTML ---
test_assert(__LINE__, strpos($edit_html, 'id="edit_main"') !== false, 'preview: edit.html has #edit_main (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="edit_preview"') !== false, 'preview: edit.html has #edit_preview (#153)');
test_assert(__LINE__, strpos($edit_html, 'edit_preview_body page_body') !== false, 'preview: pane uses the page_body typography (#153)');
test_assert(__LINE__, strpos($edit_html, 'id="preview_btn"') !== false, 'preview: statusbar has #preview_btn (#153)');
test_assert(__LINE__, strpos($edit_html, 'プレビュー並行表示') !== false, 'preview: drawer has the side-by-side item (#153)');

// --- JS ---
foreach (['togglePreview', 'setPreviewMode', 'refreshPreview', 'schedulePreview'] as $fn) {
    test_assert(__LINE__, strpos($edit_js, 'function ' . $fn . '(') !== false, "preview: edit.js has {$fn}() (#153)");
}
test_assert(__LINE__, strpos($edit_js, "'q': 'preview'") !== false, 'preview: refreshPreview posts q=preview (#153)');
test_assert(__LINE__, strpos($edit_js, 'window.innerWidth < 800') !== false, 'preview: narrow screens fall back to full mode (#153)');
test_assert(__LINE__, strpos($edit_js, 'setTimeout(refreshPreview, 700)') !== false, 'preview: updates are debounced by 700ms (#153)');

// --- CSS ---
test_assert(__LINE__, strpos($edit_css, '#edit_main.preview-split') !== false, 'preview: split layout CSS exists (#153)');
test_assert(__LINE__, strpos($edit_css, '#edit_main.preview-full .editor') !== false, 'preview: full layout hides the editor (#153)');

// --- Review follow-ups (Copilot/agy, PR #341) ---
// invalid page_mode falls back to KonaNotation rendering
$html_bogus = kona3edit_render_preview("* 見出しテスト\n本文です", 'Bogus');
test_assert(__LINE__, strpos($html_bogus, '<h') !== false && strpos($html_bogus, '見出しテスト') !== false, 'preview: invalid page_mode falls back to KonaNotation (#341 review)');
// preview rendering must not increment the view counter (Copilot [high])
test_assert(__LINE__, strpos($edit_js, 'previewSeq') !== false, 'preview: edit.js guards response order with previewSeq (#341 review)');
test_assert(__LINE__, strpos($edit_js, "addEventListener('resize'") !== false, 'preview: layout follows window resize (#341 review)');
test_assert(__LINE__, strpos($edit_php, "kona3setConf('page_ext'") !== false, 'preview: ajax sets page_ext like the show action (#341 review)');
$counter_php = file_get_contents($root . '/plugins/counter.inc.php');
test_assert(__LINE__, strpos($counter_php, "kona3conf['preview_mode']") !== false, 'preview: counter plugin checks preview_mode (#341 review)');
test_assert(__LINE__, strpos($counter_php, 'function kona3plugins_counter_getValue(') !== false, 'preview: counter has a read-only getter (#341 review)');

global $kona3conf;
$orig_page = isset($kona3conf['page']) ? $kona3conf['page'] : 'FrontPage';
$ctest_page = 'TestPreviewCounter_' . time();
$kona3conf['page'] = $ctest_page;
$ctest_file = kona3getWikiFile($ctest_page);
kona3lock_save($ctest_file, "preview counter test");
$ctest_id = kona3db_getPageId($ctest_page, TRUE);
subdb_exec("INSERT INTO counter (page_id, value, mtime) VALUES (?,5,?)", [$ctest_id, time()]);
subdb_exec(
    "INSERT INTO counter_month (page_id, year, month, value, mtime) VALUES (?,?,?,?,?)",
    [$ctest_id, intval(date('Y')), intval(date('n')), 7, time()]
);
$chtml = kona3edit_render_preview("#counter", 'KonaNotation');
$chtml2 = kona3edit_render_preview("#counter", 'KonaNotation');
$cr = subdb_get1("SELECT value FROM counter WHERE page_id=?", [$ctest_id]);
test_eq(__LINE__, intval($cr['value']), 5, 'preview: counter total is not incremented by previews (#341 review)');
$cmr = subdb_get1(
    "SELECT value FROM counter_month WHERE page_id=? AND year=? AND month=?",
    [$ctest_id, intval(date('Y')), intval(date('n'))]
);
test_eq(__LINE__, intval($cmr['value']), 7, 'preview: monthly counter is not incremented by previews (#341 review)');
test_assert(__LINE__, strpos($chtml, '>5<') !== false, 'preview: counter shows the current value (#341 review)');
test_assert(__LINE__, empty($kona3conf['preview_mode']), 'preview: preview_mode flag is restored after rendering (#341 review)');
$kona3conf['page'] = $orig_page;

// --- max_edit_size の単位 (MB) の回帰テスト ---
// プレビューが常に 'Text is too large' になっていた不具合: MB の数値と
// バイト長を直接比較していた。読み込み側と同じバイト換算で判定する。
test_assert(__LINE__, function_exists('kona3edit_is_too_large'), 'preview: size-check helper exists (#153)');
$orig_max = isset($kona3conf['max_edit_size']) ? $kona3conf['max_edit_size'] : null;
$kona3conf['max_edit_size'] = 3; // 既定値 (3MB)
test_assert(__LINE__, kona3edit_is_too_large("短い本文です") === false, 'preview: a short text is not too large at 3MB (#153)');
test_assert(__LINE__, kona3edit_is_too_large(str_repeat('a', 1024 * 1024)) === false, 'preview: 1MB of text is not too large at 3MB (#153)');
test_assert(__LINE__, kona3edit_is_too_large(str_repeat('a', 3 * 1024 * 1024 + 1)) === true, 'preview: over 3MB is too large at 3MB (#153)');
$kona3conf['max_edit_size'] = 0; // 0 = 無制限
test_assert(__LINE__, kona3edit_is_too_large(str_repeat('a', 5 * 1024 * 1024)) === false, 'preview: max_edit_size=0 means no limit (#153)');
if ($orig_max === null) {
    unset($kona3conf['max_edit_size']);
    test_assert(__LINE__, kona3edit_is_too_large("短い本文です") === false, 'preview: an unset max_edit_size means no limit (#153)');
} else {
    $kona3conf['max_edit_size'] = $orig_max;
}

// --- プレビュー本文の組版 (表示画面のスタイルは #kona3_main 配下で届かない) ---
test_assert(__LINE__, strpos($edit_css, '.edit_preview_body') !== false, 'preview: edit.css styles the preview body (#153 typography)');
test_assert(__LINE__, strpos($edit_css, 'line-height: var(--k3-line-height-body') !== false, 'preview: preview body uses the body line-height token (#153 typography)');
$parts_header = file_get_contents($root . '/template/parts_header.html');
test_assert(__LINE__, strpos($parts_header, '.edit_preview_body { line-height') !== false, 'preview: user line-height setting also applies to the preview (#153 typography)');
$drawer_js_src = file_get_contents($root . '/resource/drawer.js');
test_assert(__LINE__, strpos($drawer_js_src, '.edit_preview_body { line-height') !== false, 'preview: drawer.js text-style injection covers the preview (#153 typography)');
