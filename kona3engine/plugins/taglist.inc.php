<?php
/** 全てのタグ一覧を表示する
 * - [書式] #taglist
 * - [説明]
 * -- システムに登録されている全てのタグを一覧表示します。
 */
function konawiki_plugin_taglist($args) {
    $all_tags = kona3tags_getAllTags();
    
    if (empty($all_tags)) {
        return '<div class="kona3-taglist"><p>タグが登録されていません。</p></div>';
    }
    
    $html = '<div class="kona3-taglist">';
    $html .= '<h3>📚 全タグ一覧</h3>';
    $html .= '<ul class="tag-cloud">';
    
    global $kona3conf;
    $page = $kona3conf['page'];
    
    foreach ($all_tags as $tag) {
        $pages = kona3tags_load($tag);
        $count = count($pages);
        $tag_h = htmlspecialchars($tag);
        $url = kona3getPageURL($page) . '&plugin&name=tags&tag=' . urlencode($tag);
        
        $html .= '<li>';
        $html .= '<a href="' . $url . '" title="' . $count . '件のページ">';
        $html .= '🏷️' . $tag_h . ' <span class="tag-count">(' . $count . ')</span>';
        $html .= '</a>';
        $html .= '</li> ';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}
