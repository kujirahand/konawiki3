<?php
require_once __DIR__ . '/test_common.inc.php';
require_once dirname(__DIR__) . '/action/plugin.inc.php';
require_once dirname(__DIR__) . '/plugins/sakuramml_sf.inc.php';

kona3_setPluginInfo("sakuramml_sf", "pid", 1);

$html = kona3plugins_sakuramml_sf_execute([
    'rows=4',
    'ドレミファソ',
]);

test_assert(__LINE__, strpos($html, 'sakuramml-libplayer@0.1.1/sakura-mml-player.js') !== FALSE, 'sakuramml_sf: libplayerをCDNから読み込む');
test_assert(__LINE__, strpos($html, 'index.php?FrontPage&plugin&name=sakuramml_sf&m=soundfont') !== FALSE, 'sakuramml_sf: SoundFontを同一オリジンのプラグインアクションから読み込む');
test_assert(__LINE__, strpos($html, 'compileMML') !== FALSE, 'sakuramml_sf: compileMMLでコンパイルする');
test_assert(__LINE__, strpos($html, 'loadSoundFont') !== FALSE, 'sakuramml_sf: loadSoundFontでSoundFontを読み込む');
test_assert(__LINE__, strpos($html, 'sakuramml_sf_btnCompile1') === FALSE, 'sakuramml_sf: コンパイルボタンを出力しない');
test_assert(__LINE__, strpos($html, 'sakuramml_sf_btnPlay1') !== FALSE, 'sakuramml_sf: 再生ボタンを出力する');
test_assert(__LINE__, strpos($html, 'rows="4"') !== FALSE, 'sakuramml_sf: rows指定を反映する');
test_assert(__LINE__, strpos($html, 'ドレミファソ') !== FALSE, 'sakuramml_sf: MMLを出力する');

$html2 = kona3plugins_sakuramml_sf_execute([
    'ver=0.1.0',
    'sf=https://example.com/test.sf2',
    '<test>',
]);

test_assert(__LINE__, strpos($html2, 'sakuramml-libplayer@0.1.0') === FALSE, 'sakuramml_sf: 2回目以降はヘッダを重複出力しない');
test_assert(__LINE__, strpos($html2, '&lt;test&gt;') !== FALSE, 'sakuramml_sf: MMLをHTMLエスケープする');

test_eq(__LINE__, kona3plugins_sakuramml_sf_getSoundFontProxyUrl('FrontPage'), 'index.php?FrontPage&plugin&name=sakuramml_sf&m=soundfont', 'sakuramml_sf: SoundFontプロキシURLを生成する');
test_assert(__LINE__, strpos(SAKURAMML_SF_SOUNDFONT_URL, 'https://sakuramml.com/player/fonts/TimGM6mb.sf2') === 0, 'sakuramml_sf: 元SoundFont URLを保持する');
test_assert(__LINE__, kona3plugins_sakuramml_sf_isHttpStatusOk(['HTTP/1.1 200 OK']), 'sakuramml_sf: SoundFont取得はHTTP 200を成功扱いにする');
test_assert(__LINE__, kona3plugins_sakuramml_sf_isHttpStatusOk(['HTTP/1.1 201 Created']), 'sakuramml_sf: HTTP 2xxを成功扱いにする');
test_assert(__LINE__, kona3plugins_sakuramml_sf_isHttpStatusOk(['HTTP/1.1 302 Found', 'Location: https://example.com/font.sf2', 'HTTP/1.1 200 OK']), 'sakuramml_sf: リダイレクト後の最後のHTTP 2xxを成功扱いにする');
test_assert(__LINE__, !kona3plugins_sakuramml_sf_isHttpStatusOk(['HTTP/1.1 404 Not Found']), 'sakuramml_sf: HTTP 404本文を成功扱いにしない');
test_assert(__LINE__, !kona3plugins_sakuramml_sf_isHttpStatusOk(['HTTP/1.1 500 Internal Server Error']), 'sakuramml_sf: HTTP 500本文を成功扱いにしない');
test_assert(__LINE__, !kona3plugins_sakuramml_sf_isHttpStatusOk(['HTTP/1.1 200 OK', 'HTTP/1.1 404 Not Found']), 'sakuramml_sf: 最後のHTTPステータスがエラーなら失敗扱いにする');
test_assert(__LINE__, kona3_action_plugin_isValidName('sakuramml_sf'), 'plugin action: アンダースコアを許可する');
test_assert(__LINE__, kona3_action_plugin_isValidName('test-plugin'), 'plugin action: ハイフンを許可する');
test_assert(__LINE__, !kona3_action_plugin_isValidName('../admin'), 'plugin action: パストラバーサルは許可しない');
test_eq(__LINE__, kona3_action_plugin_getActionFuncName('sakuramml_sf'), 'kona3plugins_sakuramml_sf_action', 'plugin action: アンダースコア付き関数名を生成する');
test_eq(__LINE__, kona3_action_plugin_getActionFuncName('test-plugin'), 'kona3plugins_test_plugin_action', 'plugin action: ハイフンを関数名ではアンダースコアに正規化する');
