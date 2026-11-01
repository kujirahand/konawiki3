<?php
require_once __DIR__ . '/test_common.inc.php';

// Test for the edit top bar without wrapping (issue #365)
// - 一時保存/Git保存/ツールはアイコンボタン化し、トップバーを1行に収める

$root = dirname(__DIR__);
$edit_html = file_get_contents($root . '/template/edit.html');
$edit_css = file_get_contents($root . '/resource/edit.css');

// id を含む要素の開始タグ(button は終了タグまで)を返す
function topbar_tag($html, $id)
{
    $pos = strpos($html, 'id="' . $id . '"');
    if ($pos === false) {
        return '';
    }
    $head = substr($html, 0, $pos);
    $start = max((int)strrpos($head, '<input'), (int)strrpos($head, '<button'));
    $is_button = substr($html, $start, 7) === '<button';
    $end = $is_button ? strpos($html, '</button>', $pos) : strpos($html, '>', $pos);
    $len = $is_button ? $end - $start + strlen('</button>') : $end - $start + 1;
    return substr($html, $start, $len);
}

// --- 一時保存 ---
$temp = topbar_tag($edit_html, 'temporarily_save_btn');
test_assert(__LINE__, strpos($temp, '<button') === 0, 'temp save is a <button> (#365)');
test_assert(__LINE__, strpos($temp, "title=\"{{'Save temp'|lang}}\"") !== false, 'temp save has title (#365)');
test_assert(__LINE__, strpos($temp, "aria-label=\"{{'Save temp'|lang}}\"") !== false, 'temp save has aria-label (#365)');
test_assert(__LINE__, strpos($temp, '💾') !== false, 'temp save has the 💾 icon (#365)');
test_assert(__LINE__, strpos($temp, 'button-long') === false, 'temp save no longer uses button-long (#365)');
test_assert(__LINE__, strpos($temp, 'edit_icon_btn') !== false, 'temp save uses edit_icon_btn (#365)');
test_assert(__LINE__, strpos($temp, 'disabled') !== false, 'temp save keeps disabled (#365)');

// --- Git保存 ---
$git = topbar_tag($edit_html, 'git_save_btn');
test_assert(__LINE__, strpos($git, '<button') === 0, 'git save is a <button> (#365)');
test_assert(__LINE__, strpos($git, "title=\"{{'Save to Repo'|lang}}\"") !== false, 'git save has title (#365)');
test_assert(__LINE__, strpos($git, "aria-label=\"{{'Save to Repo'|lang}}\"") !== false, 'git save has aria-label (#365)');
test_assert(__LINE__, strpos($git, '<svg') !== false, 'git save has an inline svg (#365)');
test_assert(__LINE__, strpos($git, '{{ if !$git_enabled }} style="display: none;" {{ endif }}') !== false, 'git save keeps the git_enabled condition (#365)');

// --- ツール ---
$tools = topbar_tag($edit_html, 'tools_btn');
test_assert(__LINE__, strpos($tools, '🔧') !== false, 'tools button has the 🔧 icon (#365)');
test_assert(__LINE__, strpos($tools, 'aria-label="ツール"') !== false, 'tools button has aria-label (#365)');
test_assert(__LINE__, strpos($tools, 'title="ツール"') !== false, 'tools button has title (#365)');
test_assert(__LINE__, strpos($tools, 'onclick="toggleEditDrawer()"') !== false, 'tools button keeps onclick (#365)');
test_assert(__LINE__, strpos($tools, 'edit_icon_btn') !== false, 'tools button uses edit_icon_btn (#365)');

// --- 保存して表示 ---
$save = topbar_tag($edit_html, 'save_btn');
test_assert(__LINE__, strpos($save, 'type="submit"') !== false, 'save button stays type=submit (#365)');
test_assert(__LINE__, strpos($save, "value=\"{{'Save &amp; Show'|lang}}\"") !== false, 'save button stays a text button (#365)');

// --- CSS ---
test_assert(__LINE__, preg_match('/#edit_topbar\s*\{[^}]*flex-wrap:\s*nowrap;/', $edit_css) === 1, '#edit_topbar is nowrap (#365)');
test_assert(__LINE__, preg_match('/\.edit_icon_btn\s*\{[^}]*width:\s*40px;/', $edit_css) === 1, '.edit_icon_btn is 40px wide (#365)');
test_assert(__LINE__, strpos($edit_css, 'width: 16em') === false, 'edit.css no longer has width: 16em (#365)');
test_assert(__LINE__, strpos($edit_css, 'flex-basis: 100%') === false, 'edit.css no longer forces a second row (#365)');
test_assert(__LINE__, preg_match('/@media \(max-width: 640px\).*?\.edit_save_state \{\s*font-size: 0;/s', $edit_css) === 1, 'mobile save state shrinks to a status dot, not display:none (#365 review)');
