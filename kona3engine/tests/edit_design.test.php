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
test_assert(__LINE__, strpos($edit_css, 'max-width: 1120px') === false, 'design: the editor is full width like the Zen Drawer blueprint (#153 wide)');
test_assert(__LINE__, strpos($edit_css, 'padding: 8px 26px 24px') !== false, 'design: the editor keeps the blueprint side padding (#153 wide)');
test_assert(__LINE__, strpos($edit_css, '.edit_save_state::before') !== false, 'design: save state is a dot plus text, not a filled pill (#153 clean)');
test_assert(__LINE__, strpos($edit_css, 'border-bottom: 2px solid var(--k3-color-accent') === false, 'design: topbar has no accent rule line (#153 clean)');
