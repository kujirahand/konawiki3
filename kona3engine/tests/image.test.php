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
