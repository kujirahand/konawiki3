<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/include.inc.php';

$txt_page = 'IncludeTestTxt_' . time();
$md_page = 'IncludeTestMd_' . time();
$txt_file = kona3getWikiFile($txt_page, true, '.txt');
$md_file = kona3getWikiFile($md_page, true, '.md');

file_put_contents($txt_file, "* Include Test Txt\n\nTXT body");
file_put_contents($md_file, "# Include Test Md\n\nMD body");

$txt_html = kona3plugins_include_execute([$txt_page]);
test_assert(__LINE__, strpos($txt_html, 'Include Test Txt') !== false, "include should render txt page");
test_assert(__LINE__, strpos($txt_html, 'TXT body') !== false, "include should include txt body");

$md_html = kona3plugins_include_execute([$md_page]);
test_assert(__LINE__, strpos($md_html, 'Include Test Md') !== false, "include should render md page");
test_assert(__LINE__, strpos($md_html, 'MD body') !== false, "include should include md body");

$both_html = kona3plugins_include_execute(["{$txt_page}\n{$md_page}"]);
test_assert(__LINE__, strpos($both_html, 'Include Test Txt') !== false, "include should render txt page in list");
test_assert(__LINE__, strpos($both_html, 'Include Test Md') !== false, "include should render md page in list");

$multi_arg_html = kona3plugins_include_execute([$txt_page, $md_page]);
test_assert(__LINE__, strpos($multi_arg_html, 'Include Test Txt') !== false, "include should render txt page from multiple args");
test_assert(__LINE__, strpos($multi_arg_html, 'Include Test Md') !== false, "include should render md page from multiple args");

@unlink($txt_file);
@unlink($md_file);

// ---------- #387: 拡張子付き指定 / 相対パス ----------
global $kona3conf;
$saved_page387 = isset($kona3conf['page']) ? $kona3conf['page'] : null;
$uniq387 = time() . '_' . getmypid();
$dir387 = 'IncludeDir_' . $uniq387;
$files387 = [];
$mk387 = function ($rel, $body) use (&$files387) {
    $f = KONA3_DIR_DATA . '/' . $rel;
    @mkdir(dirname($f), 0777, true);
    file_put_contents($f, $body);
    $files387[] = $f;
    return $f;
};

// 拡張子付きで指定しても取り込めること (従来は Page.md.md を探して失敗していた)
// (元のテストでファイルは削除済みなので、ここで作り直す)
$mk387($md_page . '.md', "# Include Test Md\n\nMD body");
$mk387($txt_page . '.txt', "* Include Test Txt\n\nTXT body");
$ext_md_html = kona3plugins_include_execute([$md_page . '.md']);
test_assert(__LINE__, strpos($ext_md_html, 'Include Test Md') !== false, "include should render md page specified with .md");
$ext_txt_html = kona3plugins_include_execute([$txt_page . '.txt']);
test_assert(__LINE__, strpos($ext_txt_html, 'Include Test Txt') !== false, "include should render txt page specified with .txt");
$slash_html = kona3plugins_include_execute(['/' . $md_page . '.md']);
test_assert(__LINE__, strpos($slash_html, 'Include Test Md') !== false, "include should render page specified with leading slash");

// data フォルダ以下のサブフォルダ (拡張子付き・なし)
$mk387($dir387 . '/ChildMd.md', "# Child Md 387\n\nchild md body");
$mk387($dir387 . '/ChildTxt.txt', "* Child Txt 387\n\nchild txt body");
test_assert(__LINE__, strpos(kona3plugins_include_execute([$dir387 . '/ChildMd.md']), 'Child Md 387') !== false, "include should render subdir md with .md");
test_assert(__LINE__, strpos(kona3plugins_include_execute([$dir387 . '/ChildMd']), 'Child Md 387') !== false, "include should render subdir md without ext");
test_assert(__LINE__, strpos(kona3plugins_include_execute([$dir387 . '/ChildTxt.txt']), 'Child Txt 387') !== false, "include should render subdir txt with .txt");

// そのWikiファイルからの相対パス (現在ページは $dir387/Main)
$mk387($dir387 . '/Main.txt', "* Main 387");
$mk387($dir387 . '/Sibling.md', "# Sibling 387\n\nsibling body");
$mk387($dir387 . '/Inner/Deep.md', "# Deep 387\n\ndeep body");
$kona3conf['page'] = $dir387 . '/Main';
test_assert(__LINE__, strpos(kona3plugins_include_execute(['Sibling']), 'Sibling 387') !== false, "include should resolve sibling by relative path");
test_assert(__LINE__, strpos(kona3plugins_include_execute(['Sibling.md']), 'Sibling 387') !== false, "include should resolve sibling with .md by relative path");
test_assert(__LINE__, strpos(kona3plugins_include_execute(['./Sibling']), 'Sibling 387') !== false, "include should resolve ./Sibling by relative path");
test_assert(__LINE__, strpos(kona3plugins_include_execute(['Inner/Deep']), 'Deep 387') !== false, "include should resolve Inner/Deep by relative path");
test_assert(__LINE__, strpos(kona3plugins_include_execute(['Inner/Deep.md']), 'Deep 387') !== false, "include should resolve Inner/Deep.md by relative path");

// 親フォルダへの相対パス (data の範囲内)
$mk387($dir387 . '/Deep2/Main2.txt', "* Main2 387");
$kona3conf['page'] = $dir387 . '/Deep2/Main2';
test_assert(__LINE__, strpos(kona3plugins_include_execute(['../Sibling']), 'Sibling 387') !== false, "include should resolve ../Sibling within data dir");

// data 直下と相対先の両方に同名がある場合は data 直下が優先されること
$both387 = 'IncludeBoth387_' . $uniq387;
$mk387($both387 . '.md', "# Root Both 387\n\nroot");
$mk387($dir387 . '/' . $both387 . '.md', "# Sub Both 387\n\nsub");
$both387_html = kona3plugins_include_execute([$both387]);
test_assert(__LINE__, strpos($both387_html, 'Root Both 387') !== false, "include should prefer data-root file over relative file");
test_assert(__LINE__, strpos($both387_html, 'Sub Both 387') === false, "include should not render relative file when data-root file exists");

// 入れ子 include では取り込み中のファイルからの相対パスになること
$mk387($dir387 . '/Inner/Leaf.txt', "* Leaf 387\n\nleaf body");
$mk387($dir387 . '/Inner/Mid.txt', "* Mid 387\n\n#include(Leaf)\n");
$kona3conf['page'] = 'FrontPage';
test_assert(__LINE__, strpos(kona3plugins_include_execute([$dir387 . '/Inner/Mid']), 'Leaf 387') !== false, "include should resolve nested include relative to the including file");

// data の外は取り込めないこと
$esc_html = kona3plugins_include_execute(['../../../etc/passwd']);
test_assert(__LINE__, strpos($esc_html, 'is not found') !== false, "include should not read files outside data dir");
$missing_md_html = kona3plugins_include_execute(['NoSuch387_' . $uniq387 . '.md']);
test_assert(__LINE__, strpos($missing_md_html, 'is not found') !== false, "include should report missing .md file as not found");

if ($saved_page387 === null) { unset($kona3conf['page']); } else { $kona3conf['page'] = $saved_page387; }
foreach ($files387 as $f) { @unlink($f); }
@rmdir(KONA3_DIR_DATA . '/' . $dir387 . '/Inner');
@rmdir(KONA3_DIR_DATA . '/' . $dir387 . '/Deep2');
@rmdir(KONA3_DIR_DATA . '/' . $dir387);
