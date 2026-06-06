<?php

/** mermaid notation
 * - [Usage] {{{#mermaid(caption,filename) ... }}}
 * caption --- キャプション
 * filename --- ブラウザで生成したSVGファイルを出力する
 */

function kona3plugins_mermaid_write_source($full_mmd, $text)
{
    $current_text = file_exists($full_mmd) ? file_get_contents($full_mmd) : false;
    if ($current_text === $text) {
        return false;
    }
    file_put_contents($full_mmd, $text);
    clearstatcache(true, $full_mmd);
    return true;
}

function kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg)
{
    clearstatcache(true, $full_mmd);
    clearstatcache(true, $full_svg);
    if (!file_exists($full_svg)) {
        return true;
    }
    if (!file_exists($full_mmd)) {
        return true;
    }
    return filemtime($full_mmd) > filemtime($full_svg);
}

function kona3plugins_mermaid_normalize_filename($filename)
{
    $filename = preg_replace("/^file=/", "", $filename);
    $filename = preg_replace("/\.svg$/", "", $filename);
    return preg_replace("/[^a-zA-Z0-9\-\_\.]/", "_", $filename);
}

function kona3plugins_mermaid_get_paths($page, $filename)
{
    $filename = kona3plugins_mermaid_normalize_filename($filename);
    if ($filename === "") {
        return false;
    }
    $pagePath = kona3getWikiFile($page);
    $pageDir = dirname($pagePath);
    return [
        'filename' => $filename,
        'full_svg' => $pageDir . "/" . $filename . ".svg",
        'full_mmd' => $pageDir . "/" . $filename . ".mmd",
    ];
}

function kona3plugins_mermaid_get_data_url($full_path)
{
    $url = str_replace(KONA3_DIR_DATA, '', $full_path);
    return "index.php?" . urlencode($url) . "&data";
}

function kona3plugins_mermaid_validate_svg($svg)
{
    $svg = trim($svg);
    if ($svg === "" || strlen($svg) > 1024 * 1024 * 5) {
        return false;
    }
    $svg_without_xml = preg_replace('/^\s*<\?xml[^>]*\?>\s*/i', '', $svg);
    if (!preg_match('/^<svg[\s>]/i', $svg_without_xml)) {
        return false;
    }
    $danger_patterns = [
        '/<script\b/i',
        '/<foreignObject\b/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
    ];
    foreach ($danger_patterns as $pattern) {
        if (preg_match($pattern, $svg)) {
            return false;
        }
    }
    return true;
}

function kona3plugins_mermaid_save_svg($full_svg, $svg)
{
    if (!kona3isLogin()) {
        return [
            'ok' => false,
            'status' => 'forbidden',
            'message' => 'Login is required.',
        ];
    }
    $svg = trim($svg);
    if (!kona3plugins_mermaid_validate_svg($svg)) {
        return [
            'ok' => false,
            'status' => 'invalid_svg',
            'message' => 'Invalid SVG data.',
        ];
    }
    $dir = dirname($full_svg);
    if (!is_dir($dir)) {
        return [
            'ok' => false,
            'status' => 'directory_not_found',
            'message' => 'Output directory was not found.',
        ];
    }
    $ok = file_put_contents($full_svg, $svg) !== false;
    clearstatcache(true, $full_svg);
    return [
        'ok' => $ok,
        'status' => $ok ? 'saved' : 'save_failed',
        'message' => $ok ? 'SVG saved.' : 'Failed to save SVG.',
    ];
}

function kona3plugins_mermaid_execute($args)
{
    global $kona3conf;
    $caption = "";
    $filename = "";
    $text = "";
    if (count($args) >= 3) {
        $caption = array_shift($args);
        $filename = array_shift($args); 
        $text = array_shift($args);
    } else if (count($args) >= 2) {
        $caption = array_shift($args);
        $text = array_shift($args);
    } else {
        $text = array_shift($args);
    }
    $head = '';
    //
    $text_html = htmlspecialchars($text);
    $plugkey = "plugins.mermaid.init";
    if (empty($kona3conf[$plugkey])) {
        $kona3conf[$plugkey] = 1;
        $head = <<<EOS
<script type="module">
  import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11.15.0/dist/mermaid.esm.min.mjs';
  mermaid.initialize({
    startOnLoad: false,
    securityLevel: 'strict',
    htmlLabels: false,
    flowchart: { htmlLabels: false },
    class: { htmlLabels: false },
    state: { htmlLabels: false }
  });
  const sanitizeMermaidSvgForSave = (svg) => {
    const clone = svg.cloneNode(true);
    clone.querySelectorAll('foreignObject').forEach((foreignObject) => {
      const text = (foreignObject.textContent || '').replace(/\s+/g, ' ').trim();
      const width = Number.parseFloat(foreignObject.getAttribute('width') || '0');
      const height = Number.parseFloat(foreignObject.getAttribute('height') || '0');
      const svgText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      svgText.setAttribute('x', String(width / 2));
      svgText.setAttribute('y', String(height / 2));
      svgText.setAttribute('text-anchor', 'middle');
      svgText.setAttribute('dominant-baseline', 'middle');
      svgText.setAttribute('class', 'nodeLabel');
      const lines = text.split(/\\n/).filter((line) => line !== '');
      if (lines.length <= 1) {
        svgText.textContent = text;
      } else {
        lines.forEach((line, index) => {
          const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
          tspan.setAttribute('x', String(width / 2));
          tspan.setAttribute('dy', index === 0 ? String(-(lines.length - 1) * 0.6) + 'em' : '1.2em');
          tspan.textContent = line;
          svgText.appendChild(tspan);
        });
      }
      foreignObject.replaceWith(svgText);
    });
    return clone;
  };
  const saveMermaidSvg = async (el) => {
    const url = el.dataset.saveUrl;
    if (!url || el.dataset.saveStarted) return;
    el.dataset.saveStarted = '1';
    const status = el.querySelector('.mermaid-ajax-status');
    const svg = el.querySelector('svg');
    if (!svg) {
      if (status) status.textContent = 'SVG取得失敗';
      return;
    }
    if (status) status.textContent = 'SVG保存中...';
    try {
      const params = new URLSearchParams();
      const sanitizedSvg = sanitizeMermaidSvgForSave(svg);
      params.append('svg', new XMLSerializer().serializeToString(sanitizedSvg));
      params.append('mermaid_svg', el.dataset.editToken || '');
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
      });
      const json = await res.json();
      if (!res.ok || !json.ok) {
        throw new Error(json.message || 'SVG保存に失敗しました');
      }
      if (status) status.textContent = 'SVG保存完了';
    } catch (err) {
      if (status) status.textContent = err.message;
      if (status) status.classList.add('error');
    }
  };
  window.addEventListener('DOMContentLoaded', async () => {
    await mermaid.run({ querySelector: '.mermaid' });
    document.querySelectorAll('.kona3-mermaid-async[data-save-url]').forEach(saveMermaidSvg);
  });
</script>
EOS;
    }
    // export SVGファイル
    $svg_link = "";
    $ajax_attr = "";
    $ajax_status = "";
    if ($filename) {
        $page = kona3getPage(); // 現在のページ名を取得
        $paths = kona3plugins_mermaid_get_paths($page, $filename);
        if ($paths === false) {
            $filename = "";
        } else {
            $filename = $paths['filename'];
            $full_svg = $paths['full_svg'];
            $full_mmd = $paths['full_mmd'];
            $url_svg = kona3plugins_mermaid_get_data_url($full_svg);
            $url_svg_html = htmlspecialchars($url_svg, ENT_QUOTES);
            $svg_link .= "<span><a href=\"{$url_svg_html}\" target=\"_blank\">(↓svg)</a></span>\n";
            if (kona3isLogin()) {
                kona3plugins_mermaid_write_source($full_mmd, $text);
            }
            $update = kona3plugins_mermaid_needs_regenerate($full_mmd, $full_svg);
            if ($update && kona3isLogin()) {
                $ajax_url = kona3getPageURL($page, "mermaid_ajax", "", kona3getURLParams(['file' => $filename]));
                $ajax_url_html = htmlspecialchars($ajax_url, ENT_QUOTES);
                $edit_token = htmlspecialchars(kona3_getEditToken('mermaid_svg'), ENT_QUOTES);
                $ajax_attr = " data-save-url=\"{$ajax_url_html}\" data-edit-token=\"{$edit_token}\"";
                $ajax_status = "<span class=\"mermaid-ajax-status\">SVG保存待ち</span>";
            }
        }
    }
    $caption_html = htmlspecialchars($caption, ENT_QUOTES);
    $body = <<<EOS
<!-- mermaid -->
<div class="svg kona3-mermaid-async"{$ajax_attr}>
  <pre class="mermaid">{$text_html}</pre>
  <div class="memo">{$caption_html} {$svg_link} {$ajax_status}</div>
</div>
<!-- /mermaid -->
EOS;
    $result = $head . "\n" . $body . "\n";
    return $result;
}
