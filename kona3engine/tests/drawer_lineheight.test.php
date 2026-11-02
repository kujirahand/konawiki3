<?php
require_once __DIR__ . '/test_common.inc.php';

// ドロワーの行間スライダーの既定値を本文の実測値に合わせる (issue #347)

$root = dirname(__DIR__);
$js = file_get_contents($root . '/resource/drawer.js');
$html = file_get_contents($root . '/template/parts_header.html');

// --- 既定値の実測 ---
test_assert(__LINE__, strpos($js, 'function detectDefaultLineHeight') !== false, 'drawer.js has detectDefaultLineHeight (#347)');
test_assert(__LINE__, strpos($js, 'getComputedStyle') !== false, 'drawer.js measures with getComputedStyle (#347)');
test_assert(__LINE__, strpos($js, 'override.disabled = true') !== false, 'drawer.js disables the override while measuring (#347)');
test_assert(__LINE__, strpos($js, 'override.disabled = false') !== false, 'drawer.js re-enables the override after measuring (#347)');
test_assert(__LINE__, strpos($js, 'DEFAULT_LINE_HEIGHT = detectDefaultLineHeight();') !== false, 'DEFAULT_LINE_HEIGHT is set from the measured value (#347)');
test_assert(__LINE__, strpos($js, 'const DEFAULT_LINE_HEIGHT = 1.7') === false, 'DEFAULT_LINE_HEIGHT is no longer a fixed const (#347)');
test_assert(__LINE__, strpos($js, 'let DEFAULT_LINE_HEIGHT = 1.7;') !== false, 'DEFAULT_LINE_HEIGHT keeps 1.7 as fallback (#347)');

// 実測は currentLineHeight の初期化より前
$detect_pos = strpos($js, 'DEFAULT_LINE_HEIGHT = detectDefaultLineHeight();');
$current_pos = strpos($js, 'let currentLineHeight = DEFAULT_LINE_HEIGHT;');
test_assert(__LINE__, $detect_pos !== false && $current_pos !== false && $detect_pos < $current_pos, 'measurement precedes currentLineHeight init (#347)');

// --- 0.05 刻み ---
test_assert(__LINE__, strpos($js, 'LINE_HEIGHT_STEP = 0.05') !== false, 'LINE_HEIGHT_STEP is 0.05 (#347)');
test_assert(__LINE__, strpos($js, 'function roundStep005') !== false, 'drawer.js has roundStep005 (#347)');
test_assert(__LINE__, strpos($js, 'function formatLineHeight') !== false, 'drawer.js has formatLineHeight (#347)');
test_assert(__LINE__, strpos($js, 'currentLineHeight.toFixed(1)') === false, 'currentLineHeight.toFixed(1) is gone (#347)');
test_assert(__LINE__, strpos($js, 'roundStep(Math.min(LINE_HEIGHT_MAX') === false, 'line height buttons no longer use roundStep (#347)');
test_assert(__LINE__, strpos($js, 'roundStep(Math.max(LINE_HEIGHT_MIN') === false, 'line height buttons no longer use roundStep (#347)');

// 既存テストが要求する CSS 注入文字列は維持
test_assert(__LINE__, strpos($js, "'#kona3_layout_show, #wikibody, .edit_preview_body { line-height: '") !== false, 'line-height CSS injection string is kept (#347)');

// --- テンプレート ---
test_assert(__LINE__, preg_match('/id="line_height_range"[^>]*step="0\.05"/', $html) === 1, 'line_height_range has step="0.05" (#347)');
test_assert(__LINE__, preg_match('/id="font_size_range"[^>]*step="0\.1"/', $html) === 1, 'font_size_range keeps step="0.1" (#347)');
