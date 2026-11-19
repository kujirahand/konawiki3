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

echo "data_action.test.php: ALL OK\n";
