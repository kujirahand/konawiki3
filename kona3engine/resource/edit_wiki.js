// KonaWiki記法をMarkdownに変換する
function convertToMarkdown(text) {
    // 行頭にある「・xxx」を「- xxx」に置換
    console.log(text)
    const lines = text.split(/\n/)
    const convertedLines = []
    for (let line of lines) {
        // ### リストの変換
        // 行頭の「・・・」を「--- 」に置換
        if (line.match(/^・・・/)) {
            line = line.replace(/^・・・/, '--- ')
        }
        if (line.match(/^・・/)) {
            line = line.replace(/^・・/, '-- ')
        }
        // 行頭の「・」を「- 」に置換
        if (line.match(/^・/)) {
            line = line.replace(/^・/, '- ')
        }
        // 行頭の「---」を「    -」に置換
        if (line.match(/^---\s+(.+)$/)) {
            line = line.replace(/^---\s+(.+)$/, '    - $1')
        }
        // 行頭の「--」を「  -」に置換
        if (line.match(/^--\s+(.+)$/)) {
            line = line.replace(/^--\s+(.+)$/, '  - $1')
        }
        // タイトルの変換より前で！プラグインの変換
        if (line.match(/^\#[a-z]+/)) {
            line = line.replace(/^\#([a-z0-9_]+)/, '!!$1')
        }
        // ### リンクの変換
        // リンクを置換 `[[label:link]]` を `[label](link)` に置換
        line = line.replace(/\[\[([^:\]]+):([^\]]+)\]\]/g, '[$1]($2)')
        // リンクを置換 `[[label]]` を `[label](label)` に置換
        line = line.replace(/\[\[(.+?)\]\]/g, '[$1]($1)')
        // 行頭の「■」を「# 」に置換
        if (line.match(/^■/)) {
            line = line.replace(/^■\s*/, '# ')
        }
        if (line.match(/^●/)) {
            line = line.replace(/^●\s*/, '## ')
        }
        if (line.match(/^▲/)) {
            line = line.replace(/^▲\s*/, '### ')
        }
        // ソースコードの変換
        if (line.match(/^\{\{\{/)) {
            line = line.replace(/^\{{3,}/, (m) => {
                return "```"
            })
        }
        if (line.match(/^\}\}\}/)) {
            line = line.replace(/^\}{3,}/, (m) => {
                return "```"
            })
        }

        convertedLines.push(line)
    }
    return convertedLines.join('\n')
}