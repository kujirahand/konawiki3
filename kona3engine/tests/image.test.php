<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/image.inc.php';

// --- #image plugin tests ---

$html = kona3plugins_image_execute([
    'https://example.com/image.php?id=123',
    '*caption',
]);
test_assert(__LINE__, strpos($html, "src='https://example.com/image.php?id=123'") !== false, '#image: URL without an image extension is rendered as an image');
test_assert(__LINE__, strpos($html, "<div class='memo'>caption</div>") !== false, '#image: caption is rendered');

$html = kona3plugins_image_execute([
    'javascript:alert(1)',
]);
test_assert(__LINE__, strpos($html, 'javascript:') === false, '#image: unsafe URL scheme is not output');
test_assert(__LINE__, strpos($html, '#image(javascript_alert') !== false, '#image: error uses the image plugin name');

// --- zoom (issue #245) ---
global $kona3conf;
unset($kona3conf['plugins.image.zoom.init']);

$html = kona3plugins_image_execute(['https://example.com/a.png']);
test_assert(__LINE__, strpos($html, "class='kona3-image-zoom'") !== false, '#image: image link has zoom class');
test_assert(__LINE__, strpos($html, 'kona3-image-zoom-overlay') !== false, '#image: zoom CSS/JS is output on first use');
test_assert(__LINE__, strpos($html, '__LABEL_') === false, '#image: zoom labels are all replaced');
$dl = lang('Image Download');
$cl = lang('Image Close');
test_assert(__LINE__, strpos($html, json_encode($dl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) !== false, '#image: zoom has localized download button');
test_assert(__LINE__, strpos($html, json_encode($cl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) !== false, '#image: zoom has localized close button');

// --- lang files have the image keys with emoji ---
foreach (['ja', 'en'] as $l) {
    $lang_data = [];
    include KONA3_DIR_ENGINE . "/lang/$l.inc.php";
    test_assert(__LINE__, !empty($lang_data['Image Download']) && !empty($lang_data['Image Close']), "lang/$l: image button labels exist");
    test_assert(__LINE__, strpos($lang_data['Image Download'], '⬇') !== false && strpos($lang_data['Image Close'], '❌') !== false, "lang/$l: image button labels have emoji");
}

$html = kona3plugins_image_execute(['https://example.com/b.png']);
test_assert(__LINE__, strpos($html, "class='kona3-image-zoom'") !== false, '#image: second image also zoomable');
test_assert(__LINE__, strpos($html, '<script>') === false, '#image: zoom script is output only once');

$html = kona3plugins_image_execute(['https://example.com/c.png', '@https://example.com/page']);
test_assert(__LINE__, strpos($html, "class='kona3-image-zoom'") === false, '#image: explicit @link disables zoom');
test_assert(__LINE__, strpos($html, "href='https://example.com/page'") !== false, '#image: explicit @link is kept');

$html = kona3plugins_ref_execute(['https://example.com/d.png']);
test_assert(__LINE__, strpos($html, 'kona3-image-zoom') === false, '#ref: no zoom');
