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
