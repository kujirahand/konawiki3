<?php
/** 画像を表示するプラグイン
 * URLの拡張子にかかわらず、指定されたリソースを画像として表示する。
 * - [書式] #image(URL, w=400, h=300, 400x300, @link, *caption)
 * - [引数] #ref と同じ
 * - 画像をクリックすると拡大表示する(右上に[ダウンロード][閉じる]ボタン)。@link 指定時はそのリンク先へ移動する。
 */

require_once __DIR__ . '/ref.inc.php';

function kona3plugins_image_execute($args) {
    return kona3plugins_ref_render($args, TRUE, 'image', TRUE);
}
