<?php
require_once __DIR__ . '/test_common.inc.php';

// 編集画面からの画像アップロード (issue #393)

global $kona3conf, $page;
$page = 'FrontPage';

require_once dirname(__DIR__) . '/action/edit.inc.php';

// --- kona3edit_upload_sanitize_name
test_eq(__LINE__, kona3edit_upload_sanitize_name('../evil.png'), 'evil.png', 'sanitize: path traversal removed');
test_eq(__LINE__, kona3edit_upload_sanitize_name('my pic<1>.png'), 'my_pic_1_.png', 'sanitize: special chars replaced');
test_eq(__LINE__, kona3edit_upload_sanitize_name(''), '', 'sanitize: empty stays empty');
test_eq(__LINE__, kona3edit_upload_sanitize_name('photo(1).png'), 'photo_1_.png', 'sanitize: parentheses replaced');
test_eq(__LINE__, kona3edit_upload_sanitize_name('a,b.png'), 'a_b.png', 'sanitize: comma replaced');

// --- kona3edit_upload_svg_is_safe
$svg_safe_file = tempnam(sys_get_temp_dir(), 'kona3edit_svg_');
file_put_contents($svg_safe_file, '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>');
test_assert(__LINE__, kona3edit_upload_svg_is_safe($svg_safe_file), 'svg: plain svg accepted');
@unlink($svg_safe_file);
$svg_script_file = tempnam(sys_get_temp_dir(), 'kona3edit_svg_');
file_put_contents($svg_script_file, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
test_assert(__LINE__, !kona3edit_upload_svg_is_safe($svg_script_file), 'svg: script rejected');
@unlink($svg_script_file);
$svg_event_file = tempnam(sys_get_temp_dir(), 'kona3edit_svg_');
file_put_contents($svg_event_file, '<svg onload="alert(1)"></svg>');
test_assert(__LINE__, !kona3edit_upload_svg_is_safe($svg_event_file), 'svg: event handler rejected');
@unlink($svg_event_file);
$svg_entity_file = tempnam(sys_get_temp_dir(), 'kona3edit_svg_');
file_put_contents($svg_entity_file, '<svg xmlns="http://www.w3.org/2000/svg"><a href="java&#115;cript:alert(1)"><rect width="10" height="10"/></a></svg>');
test_assert(__LINE__, !kona3edit_upload_svg_is_safe($svg_entity_file), 'svg: entity-encoded javascript URI rejected');
@unlink($svg_entity_file);
$svg_notxml_file = tempnam(sys_get_temp_dir(), 'kona3edit_svg_');
file_put_contents($svg_notxml_file, 'this is not xml');
test_assert(__LINE__, !kona3edit_upload_svg_is_safe($svg_notxml_file), 'svg: non-XML rejected');
@unlink($svg_notxml_file);
$svg_root_file = tempnam(sys_get_temp_dir(), 'kona3edit_svg_');
file_put_contents($svg_root_file, '<html><body>x</body></html>');
test_assert(__LINE__, !kona3edit_upload_svg_is_safe($svg_root_file), 'svg: non-svg root rejected');
@unlink($svg_root_file);

// --- kona3edit_upload_is_allowed
$kona3conf['allow_upload_ext'] = 'txt;md;pdf;csv;wav;mid;mp3;mp4;ogg;zip;gz;bz2;jpg;jpeg;png;gif;webp;svg;xml;json;ini;doc;docx;xls;xlsx;ppt;pptx';
test_assert(__LINE__, kona3edit_upload_is_allowed('pdf') === true, 'allowed: pdf');
test_assert(__LINE__, kona3edit_upload_is_allowed('csv') === true, 'allowed: csv');
test_assert(__LINE__, kona3edit_upload_is_allowed('xlsx') === true, 'allowed: xlsx');
test_assert(__LINE__, kona3edit_upload_is_allowed('png') === true, 'allowed: png');
test_assert(__LINE__, kona3edit_upload_is_allowed('php') === false, 'allowed: php rejected');
test_assert(__LINE__, kona3edit_upload_is_allowed('exe') === false, 'allowed: exe rejected');

// --- kona3edit_upload_is_allowed_image
test_assert(__LINE__, kona3edit_upload_is_allowed_image('png') === true, 'image: png');
test_assert(__LINE__, kona3edit_upload_is_allowed_image('pdf') === false, 'image: pdf is not an image');

// --- kona3edit_upload_unique_path
$tmpdir = sys_get_temp_dir() . '/kona3_upload_test_' . getmypid() . '_' . mt_rand();
mkdir($tmpdir);
file_put_contents($tmpdir . '/a.png', 'x');
file_put_contents($tmpdir . '/a_1.png', 'x');
test_eq(__LINE__, basename(kona3edit_upload_unique_path($tmpdir, 'a.png')), 'a_2.png', 'unique: serial number added');
test_eq(__LINE__, basename(kona3edit_upload_unique_path($tmpdir, 'b.png')), 'b.png', 'unique: new name unchanged');
unlink($tmpdir . '/a.png');
unlink($tmpdir . '/a_1.png');
rmdir($tmpdir);

// --- static checks
$engine = dirname(__DIR__);
$src_php = file_get_contents($engine . '/action/edit.inc.php');
$src_html = file_get_contents($engine . '/template/edit.html');
$src_js = file_get_contents($engine . '/resource/edit.js');
test_assert(__LINE__, strpos($src_php, "\$q == 'upload'") !== false, 'static: upload dispatch exists');
test_assert(__LINE__, strpos($src_php, 'kona3edit_upload_ajax') !== false, 'static: upload handler exists');
test_assert(__LINE__, strpos($src_php, 'kona3edit_upload_is_allowed(') !== false, 'static: handler checks kona3edit_upload_is_allowed');
test_assert(__LINE__, strpos($src_php, "'Login is required.'") !== false, 'static: handler requires login');
test_assert(__LINE__, strpos($src_html, 'id="file_upload_btn"') !== false, 'static: upload button in template');
test_assert(__LINE__, strpos($src_html, "'File Upload' | lang") !== false, 'static: upload button label');
test_assert(__LINE__, strpos($src_html, 'id="file_upload_file"') !== false, 'static: file input in template');
test_assert(__LINE__, strpos($src_html, '$upload_enabled') !== false, 'static: template uses upload_enabled');
test_assert(__LINE__, strpos($src_js, 'fileUploadOnClick') !== false, 'static: js fileUploadOnClick');
test_assert(__LINE__, strpos($src_js, '#file_upload_file') !== false, 'static: js binds #file_upload_file');
test_assert(__LINE__, strpos($src_js, 'insertFileRef(') !== false, 'static: js insertFileRef');
test_assert(__LINE__, strpos($src_js, "q: 'upload'") !== false || strpos($src_js, "'q', 'upload'") !== false, 'static: js posts q=upload');
test_assert(__LINE__, strpos($src_js, '!!ref(') !== false, 'static: js markdown ref markup');

// --- render check
function test_render_edit_upload($upload_enabled)
{
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
        'upload_enabled' => $upload_enabled,
        'tags' => '',
        'history' => [],
        'page_file' => '',
        'mtime' => 0,
    ];
    ob_start();
    kona3template('edit.html', $params);
    return ob_get_clean();
}

$html_on = test_render_edit_upload(true);
test_assert(__LINE__, strpos($html_on, 'id="file_upload_btn"') !== false,'render: upload_enabled=true shows the button');
$html_off = test_render_edit_upload(false);
test_assert(__LINE__, strpos($html_off, 'id="file_upload_btn"') === false,'render: upload_enabled=false hides the button');
