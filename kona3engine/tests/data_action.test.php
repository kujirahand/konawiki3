<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/data.inc.php';

// --- data action: extension checks are case-insensitive (#395) ---
// The ref plugin and the Markdown parser lowercase the extension before
// matching, but the data action matched the raw file name, so files
// like aaa.ZIP or PIC.JPG were rejected with 415 even though the
// extensions are allowed in the settings.

// image extension (lowercased) or ''
test_eq(__LINE__, kona3_action_data_imageExt('photo.jpg'), 'jpg', 'data: jpg is an image');
test_eq(__LINE__, kona3_action_data_imageExt('PIC.JPG'), 'jpg', 'data: JPG (uppercase) is an image');
test_eq(__LINE__, kona3_action_data_imageExt('icon.SVG'), 'svg', 'data: SVG (uppercase) is an image');
test_eq(__LINE__, kona3_action_data_imageExt('hoge/fuga/pic.WebP'), 'webp', 'data: WebP in a subfolder is an image');
test_eq(__LINE__, kona3_action_data_imageExt('doc.pdf'), '', 'data: pdf is not an image');
test_eq(__LINE__, kona3_action_data_imageExt('noext'), '', 'data: no extension is not an image');
test_eq(__LINE__, kona3_action_data_imageExt('fake.jpg.php'), '', 'data: .jpg.php is not an image');

// allow pattern (downloadable formats)
test_assert(__LINE__, kona3_action_data_isAllowed('aaa.zip'), 'data: zip is allowed');
test_assert(__LINE__, kona3_action_data_isAllowed('BBB.ZIP'), 'data: ZIP (uppercase) is allowed');
test_assert(__LINE__, kona3_action_data_isAllowed('hoge/fuga/data.Csv'), 'data: Csv (mixed case) is allowed');
test_assert(__LINE__, kona3_action_data_isAllowed('sheet.XLSX'), 'data: XLSX (uppercase) is allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('evil.php'), 'data: php is not allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('evil.PHP'), 'data: PHP (uppercase) is not allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('page.html'), 'data: html is not allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('archive.zip.php'), 'data: .zip.php is not allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('fake.zipx'), 'data: zipx is not allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('noext'), 'data: no extension is not allowed');

// custom patterns from the settings are honored (case-insensitively)
global $kona3conf;
$saved_allow = isset($kona3conf['data_dir_allow_pattern']) ? $kona3conf['data_dir_allow_pattern'] : null;
$saved_image = isset($kona3conf['image_pattern']) ? $kona3conf['image_pattern'] : null;
$kona3conf['data_dir_allow_pattern'] = '(xyz)';
$kona3conf['image_pattern'] = '(tif)';
test_assert(__LINE__, kona3_action_data_isAllowed('a.XYZ'), 'data: custom pattern xyz allows XYZ');
test_assert(__LINE__, !kona3_action_data_isAllowed('a.zip'), 'data: custom pattern xyz rejects zip');
test_eq(__LINE__, kona3_action_data_imageExt('a.TIF'), 'tif', 'data: custom image pattern tif matches TIF');
test_eq(__LINE__, kona3_action_data_imageExt('a.jpg'), '', 'data: custom image pattern tif rejects jpg');
if ($saved_allow === null) { unset($kona3conf['data_dir_allow_pattern']); } else { $kona3conf['data_dir_allow_pattern'] = $saved_allow; }
if ($saved_image === null) { unset($kona3conf['image_pattern']); } else { $kona3conf['image_pattern'] = $saved_image; }

// --- full-width characters and Japanese file names (#395) ---
// normalized extension
test_eq(__LINE__, kona3_action_data_ext('aaa.zip'), 'zip', 'data ext: zip');
test_eq(__LINE__, kona3_action_data_ext('aaa.ｚｉｐ'), 'zip', 'data ext: full-width ｚｉｐ becomes zip');
test_eq(__LINE__, kona3_action_data_ext('bbb．zip'), 'zip', 'data ext: full-width dot is a separator');
test_eq(__LINE__, kona3_action_data_ext('a.tar.GZ'), 'gz', 'data ext: last extension wins, lowercased');
test_eq(__LINE__, kona3_action_data_ext('noext'), '', 'data ext: no extension');
test_eq(__LINE__, kona3_action_data_ext('写真.JPG'), 'jpg', 'data ext: Japanese name with JPG');
// image / allowed with full-width extensions
test_eq(__LINE__, kona3_action_data_imageExt('画像.ＰＮＧ'), 'png', 'data: full-width ＰＮＧ is an image');
test_assert(__LINE__, kona3_action_data_isAllowed('aaa.ｚｉｐ'), 'data: full-width ｚｉｐ is allowed');
test_assert(__LINE__, kona3_action_data_isAllowed('bbb．zip'), 'data: full-width dot zip is allowed');
test_assert(__LINE__, kona3_action_data_isAllowed('日本語ファイル.zip'), 'data: Japanese name zip is allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('悪意.ｐｈｐ'), 'data: full-width ｐｈｐ is not allowed');
test_assert(__LINE__, !kona3_action_data_isAllowed('日本語.php'), 'data: Japanese name php is not allowed');
// file name validation: multibyte names pass, dangerous names fail
test_assert(__LINE__, kona3_action_data_isValidName('hoge/fuga/aaa.zip'), 'data name: plain path is valid');
test_assert(__LINE__, kona3_action_data_isValidName('日本語.zip'), 'data name: Japanese name is valid');
test_assert(__LINE__, kona3_action_data_isValidName('hoge/日本語ファイル.ZIP'), 'data name: Japanese path is valid');
test_assert(__LINE__, kona3_action_data_isValidName('my file.zip'), 'data name: space is valid');
test_assert(__LINE__, !kona3_action_data_isValidName(''), 'data name: empty is invalid');
test_assert(__LINE__, !kona3_action_data_isValidName('.hidden.zip'), 'data name: dot file is invalid');
test_assert(__LINE__, !kona3_action_data_isValidName('hoge/.git/config'), 'data name: dot segment is invalid');
test_assert(__LINE__, !kona3_action_data_isValidName('hoge/../secret.zip'), 'data name: .. segment is invalid');
test_assert(__LINE__, !kona3_action_data_isValidName('hoge\\fuga.zip'), 'data name: backslash is invalid');
test_assert(__LINE__, !kona3_action_data_isValidName("a\x01b.zip"), 'data name: control char is invalid');

echo "data_action.test.php: ALL OK\n";
