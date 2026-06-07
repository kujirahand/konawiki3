<?php
/** 全てのタグ一覧を表示する
 * - [書式] #taglist
 * - [説明]
 * -- システムに登録されている全てのタグを一覧表示します。
 */
function kona3plugins_taglist_execute($args) {
    global $kona3conf;
    $page = $kona3conf['page'];
    
    $html = '<div class="kona3-taglist">';
    
    $btn = '';
    if (kona3isLogin()) {
        $action_url = kona3getPageURL($page, 'plugin');
        $edit_token = kona3_getEditToken('edit_token', FALSE);
        $label = lang('Update', 'Update');
        $btn = ' <span style="font-size: 0.6em; font-weight: normal; margin-left: 10px; vertical-align: middle; display: inline-block;">' .
               '<form method="post" action="' . $action_url . '" style="display: inline;">' .
               '<input type="hidden" name="name" value="tags">' .
               '<input type="hidden" name="mode" value="update">' .
               '<input type="hidden" name="edit_token" value="' . htmlspecialchars($edit_token) . '">' .
               '<button type="submit" class="pure-button button-secondary" style="padding: 2px 6px; font-size: 11px;">🔄 ' . htmlspecialchars($label) . '</button>' .
               '</form>' .
               '</span>';
    }
    
    $html .= '<h3>📚 Tag list' . $btn . '</h3>';
    
    $all_tags = kona3tags_getAllTags();
    
    if (empty($all_tags)) {
        $html .= '<p>タグが登録されていません。</p>';
        $html .= '</div>';
        return $html;
    }
    
    $html .= '<ul class="tag-cloud">';
    
    foreach ($all_tags as $tag) {
        $pages = kona3tags_load($tag);
        $count = count($pages);
        $tag_h = htmlspecialchars($tag);
        $url = kona3getPageURL($page, 'plugin', '', 'name=tags&tag=' . urlencode($tag));
        
        $html .= '<span class="tag-item">';
        $html .= '<a href="' . $url . '" title="' . $count . '件のページ">';
        $html .= '🏷️ ' . $tag_h . ' <span class="tag-count">(' . $count . ')</span>';
        $html .= '</a>';
        $html .= '</span> ';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}

function kona3plugins_taglist_action() {
    $code = kona3plugins_taglist_execute([]);
    kona3showMessage("Tag list", $code, 'white.html');
}
