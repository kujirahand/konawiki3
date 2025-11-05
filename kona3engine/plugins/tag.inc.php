<?php
/** タグを追加する
 * - [書式] #tag(TAG1)
 * - [引数]
 * -- TAG1 ... タグ名
 * - [説明]
 * -- ページにタグを追加します。複数のタグを付ける場合は、複数回呼び出してください。
 * -- 例: #tag(PHP) #tag(プログラミング)
 */
function konawiki_plugin_tag($args) {
    global $kona3conf;
    
    $page = $kona3conf['page'];
    if (empty($args) || empty($args[0])) {
        return '<span style="color:red;">エラー: タグ名を指定してください</span>';
    }
    
    $tag = trim($args[0]);
    if ($tag === '') {
        return '<span style="color:red;">エラー: タグ名が空です</span>';
    }
    
    // タグを追加
    kona3tags_addPageTag($page, $tag);
    
    // タグをリンク付きで表示
    $tag_h = htmlspecialchars($tag);
    $url = kona3getPageURL($page) . '&plugin&name=tags&tag=' . urlencode($tag);
    
    return '<span class="kona3-tag"><a href="' . $url . '" title="このタグのページ一覧">🏷️' . $tag_h . '</a></span>';
}
