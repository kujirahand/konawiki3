<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/plugins/ref.inc.php';

// --- #ref plugin security tests ---

$html = kona3plugins_ref_execute([
    'https://example.com/image.jpg?a=1&b=2',
    '@javascript:alert(1)',
    '*<b onclick=alert(1)>caption</b>',
]);
test_assert(__LINE__, strpos($html, 'javascript:') === false, '#ref: javascript link option is not output');
test_assert(__LINE__, strpos($html, "href='https://example.com/image.jpg?a=1&amp;b=2'") !== false, '#ref: unsafe link option falls back to image URL');
test_assert(__LINE__, strpos($html, "src='https://example.com/image.jpg?a=1&amp;b=2'") !== false, '#ref: image src URL is escaped');
test_assert(__LINE__, strpos($html, '<b onclick=') === false, '#ref: caption HTML tag is not output');
test_assert(__LINE__, strpos($html, '&lt;b onclick=alert(1)&gt;caption&lt;/b&gt;') !== false, '#ref: caption is escaped');

$html = kona3plugins_ref_execute([
    'https://example.com/image.jpg',
    "@' onclick='alert(1)",
]);
test_assert(__LINE__, strpos($html, 'onclick=') === false, '#ref: quoted link option cannot inject attributes');
test_assert(__LINE__, strpos($html, "href='https://example.com/image.jpg'") !== false, '#ref: invalid quoted link falls back to image URL');

$html = kona3plugins_ref_execute([
    'https://example.com/file.pdf?a=1&b=2',
]);
test_assert(__LINE__, strpos($html, "href='https://example.com/file.pdf?a=1&amp;b=2'") !== false, '#ref: non-image href URL is escaped');
test_assert(__LINE__, strpos($html, 'https://example.com/file.pdf?a=1&amp;b=2') !== false, '#ref: non-image fallback caption is escaped');

$html = kona3plugins_ref_execute([
    'javascript:alert(1).jpg',
]);
test_assert(__LINE__, strpos($html, 'javascript:') === false, '#ref: unsafe image URL scheme is not output');
test_assert(__LINE__, strpos($html, '#ref(javascript:alert') === false, '#ref: error output escapes unsafe URL');
