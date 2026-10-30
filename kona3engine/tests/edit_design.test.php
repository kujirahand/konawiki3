<?php
require_once __DIR__ . '/test_common.inc.php';

// Edit screen design refresh (issue #153): edit.css speaks the site
// design tokens (--k3-*) and gives the editor a primary save button,
// a save-state pill and a focus ring, instead of flat gray defaults.

$root = dirname(__DIR__);
$edit_css = file_get_contents($root . '/resource/edit.css');
$dark_css = file_get_contents($root . '/resource/darkmode.css');

test_assert(__LINE__, strpos($edit_css, 'var(--k3-color-accent') !== false, 'design: edit.css uses the accent token (#153)');
test_assert(__LINE__, strpos($edit_css, 'var(--k3-color-bg-subtle') !== false, 'design: edit.css uses the subtle background token (#153)');
test_assert(__LINE__, strpos($edit_css, '#save_btn') !== false, 'design: edit.css styles the save button as primary (#153)');
test_assert(__LINE__, strpos($edit_css, '.edit_save_state') !== false && strpos($edit_css, 'border-radius: 999px') !== false, 'design: save state is a pill (#153)');
test_assert(__LINE__, strpos($edit_css, 'textarea#edit_txt:focus') !== false, 'design: textarea has a focus style (#153)');
test_assert(__LINE__, strpos($edit_css, 'box-shadow: 0 0 0 3px') !== false, 'design: focus ring is defined (#153)');
test_assert(__LINE__, strpos($edit_css, 'border: 1px solid gray') === false, 'design: no flat gray button borders remain (#153)');
test_assert(__LINE__, strpos($dark_css, 'body.dark-theme #wikiedit #save_btn') !== false, 'design: dark mode styles the primary save button (#153)');
test_assert(__LINE__, strpos($dark_css, 'body.dark-theme .edit_save_state') !== false, 'design: dark mode styles the save-state pill (#153)');

// --- スッキリ方向への調整 (デザイン第2ラウンド) ---
test_assert(__LINE__, strpos($edit_css, 'box-shadow: 0 8px 24px') === false, 'design: the sheet has no drop shadow (#153 clean)');
test_assert(__LINE__, strpos($edit_css, 'max-width: 1120px') === false, 'design: the editor is not capped at the old 1120px (#153 wide)');
test_assert(__LINE__, strpos($edit_css, 'padding: 8px 26px 24px') !== false, 'design: the editor keeps the blueprint side padding (#153 wide)');
test_assert(__LINE__, strpos($edit_css, '.edit_save_state::before') !== false, 'design: save state is a dot plus text, not a filled pill (#153 clean)');
test_assert(__LINE__, strpos($edit_css, 'border-bottom: 2px solid var(--k3-color-accent') === false, 'design: topbar has no accent rule line (#153 clean)');

// --- プレビュー表示中は全幅 (幅キャップの例外) ---
test_assert(__LINE__, strpos($edit_css, '#wikiedit:has(#edit_main.preview-split)') !== false, 'design: split preview drops the width cap (#153 preview wide)');
test_assert(__LINE__, strpos($edit_css, '#wikiedit:has(#edit_main.preview-full)') !== false, 'design: full preview drops the width cap (#153 preview wide)');

// --- #wikimessage の 760px カラムを編集画面だけ外す ---
test_assert(__LINE__, strpos($edit_css, '#wikimessage.edit_screen') !== false, 'design: edit.css uncages #wikimessage on the edit screen (#153 split wide)');
test_assert(__LINE__, strpos($edit_css, '#wikimessage:has(> #wikiedit)') !== false, 'design: edit.css uncages #wikimessage via :has as a fallback (#153 split wide)');
$def_css = file_get_contents($root . '/resource/kona3def.css');
test_assert(__LINE__, strpos($def_css, 'max-width:760px') !== false, 'design: kona3def keeps the 760px column for other screens (#153 split wide)');
$edit_html_src = file_get_contents($root . '/template/edit.html');
test_assert(__LINE__, strpos($edit_html_src, 'id="wikimessage" class="edit_screen"') !== false, 'design: edit.html marks #wikimessage as the edit screen (#153 split wide)');
