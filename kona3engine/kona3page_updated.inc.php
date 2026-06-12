<?php
/**
 * KonaWiki3 Page Updated Hooks
 */

// Hook for Tag, Meta, and Alias syncing on page write
kona3addHook('write', function($page, $body, $options = []) {
    $is_delete = (trim($body) === "");
    $tags = isset($options['tags']) ? $options['tags'] : '';
    $page_mode = isset($options['page_mode']) ? $options['page_mode'] : '';
    $old_alias_target = isset($options['old_alias_target']) ? $options['old_alias_target'] : FALSE;
    $new_alias_target = isset($options['new_alias_target']) ? $options['new_alias_target'] : FALSE;

    // Load edit action if needed for alias sync helper
    if (function_exists('kona3edit_sync_aliases')) {
        kona3edit_sync_aliases($page, $old_alias_target, $new_alias_target);
    }

    if ($is_delete) {
        // Tag DB clear
        kona3tags_clearPageTags($page);
        
        // Remove meta file
        $metaFile = kona3db_getPageMetaFile($page);
        if (file_exists($metaFile)) {
            @unlink($metaFile);
        }
    } else {
        // Update Meta Info
        $meta = kona3db_loadPageMeta($page);
        if ($meta === null) {
            $meta = [];
        }
        if ($page_mode === 'Markdown' || $page_mode === 'KonaNotation') {
            $meta['mode'] = $page_mode;
        }
        if ($tags !== '') {
            $tagArray = array_map('trim', explode('/', $tags));
            // Limit tag length to 20 chars
            $tagArray = array_map(function($tag) {
                return mb_strlen($tag) > 20 ? mb_substr($tag, 0, 20) : $tag;
            }, $tagArray);
            $meta['tags'] = $tagArray;
        } else {
            $meta['tags'] = [];
        }
        kona3db_savePageMeta($page, $meta);
        
        // Update Tag DB
        kona3tags_updatePageTags($page, $meta['tags']);
    }
});

// Hook for Discord Webhook on page write
kona3addHook('write', function($page, $body, $options = []) {
    if (trim($body) === '') {
        return;
    }
    if (kona3getConf('discord_webhook_url', '') != '') {
        kona3postDiscordWebhook($page);
    }
});

// Hook for Git on page write
kona3addHook('write', function($page, $body, $options = []) {
    global $kona3conf;
    if (empty($kona3conf['git_enabled']) || !$kona3conf['git_enabled']) {
        return;
    }
    $i_mode = isset($options['i_mode']) ? $options['i_mode'] : 'form';
    $a_mode = isset($options['a_mode']) ? $options['a_mode'] : '';
    $edit_ext = isset($options['edit_ext']) ? $options['edit_ext'] : 'txt';
    $old_ext = isset($options['old_ext']) ? $options['old_ext'] : '';

    $shouldGit = ($i_mode == 'git') || ($a_mode == 'trygit') || ($a_mode == 'trywrite' && $i_mode == 'form');
    if ($shouldGit) {
        kona3git_commit_and_push($page, $edit_ext, $old_ext);
    }
});
