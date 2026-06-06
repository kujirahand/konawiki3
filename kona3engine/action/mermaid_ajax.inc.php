<?php
include_once dirname(dirname(__FILE__)) . '/kona3lib.inc.php';
include_once dirname(dirname(__FILE__)) . '/plugins/mermaid.inc.php';

function kona3_action_mermaid_ajax()
{
    if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        kona3_action_mermaid_ajax_json([
            'ok' => false,
            'status' => 'method_not_allowed',
            'message' => 'POST method is required.',
        ], 405);
    }
    if (!kona3isLogin()) {
        kona3_action_mermaid_ajax_json([
            'ok' => false,
            'status' => 'forbidden',
            'message' => 'Login is required.',
        ], 403);
    }

    if (!kona3_checkEditToken('mermaid_svg')) {
        kona3_action_mermaid_ajax_json([
            'ok' => false,
            'status' => 'invalid_token',
            'message' => 'Invalid edit token.',
        ], 403);
    }

    $page = kona3getPage();
    $filename = kona3param('file', '');
    $paths = kona3plugins_mermaid_get_paths($page, $filename);
    if ($paths === false) {
        kona3_action_mermaid_ajax_json([
            'ok' => false,
            'status' => 'bad_request',
            'message' => 'Invalid filename.',
        ], 400);
    }
    if (!file_exists($paths['full_mmd'])) {
        kona3_action_mermaid_ajax_json([
            'ok' => false,
            'status' => 'source_not_found',
            'message' => 'MMD source file was not found.',
        ], 404);
    }

    $lock_file = $paths['full_svg'] . '.lock';
    $lock_fp = fopen($lock_file, 'c');
    if ($lock_fp) {
        flock($lock_fp, LOCK_EX);
    }
    $result = kona3plugins_mermaid_save_svg($paths['full_svg'], kona3post('svg', ''));
    if ($lock_fp) {
        flock($lock_fp, LOCK_UN);
        fclose($lock_fp);
    }
    $result['svg_url'] = kona3plugins_mermaid_get_data_url($paths['full_svg']);
    kona3_action_mermaid_ajax_json($result, $result['ok'] ? 200 : 500);
}

function kona3_action_mermaid_ajax_json($data, $status_code = 200)
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
