'use strict';

const assert = require('assert');
const {
    k3hlEscape,
    k3hlInitialState,
    k3hlTokenizeLine,
    k3hlRender
} = require('../../resource/edit_highlight_core.js');

const span = (className, value) =>
    `<span class="${className}">${value}</span>`;

const lineSpan = (className, value) =>
    span(`k3hl-line ${className}`, value);

const cases = [
    [
        'KonaNotation',
        '*見出し',
        lineSpan(
            'k3hl-h1',
            span('k3hl-mark', '*') + '見出し'
        )
    ],
    [
        'KonaNotation',
        '*******深い',
        lineSpan(
            'k3hl-h6',
            span('k3hl-mark', '*******') + '深い'
        )
    ],
    [
        'KonaNotation',
        '*',
        '*'
    ],
    [
        'KonaNotation',
        '●中見出し',
        lineSpan(
            'k3hl-h2',
            span('k3hl-mark', '●') + '中見出し'
        )
    ],
    [
        'KonaNotation',
        '---',
        span('k3hl-mark', '---')
    ],
    [
        'KonaNotation',
        '-項目',
        span('k3hl-mark k3hl-list', '-') + '項目'
    ],
    [
        'KonaNotation',
        '・・項目',
        span('k3hl-mark k3hl-list', '・・') + '項目'
    ],
    [
        'KonaNotation',
        '＋項目',
        span('k3hl-mark k3hl-list', '＋') + '項目'
    ],
    [
        'KonaNotation',
        '|a|b|',
        span('k3hl-mark', '|') +
            'a' +
            span('k3hl-mark', '|') +
            'b' +
            span('k3hl-mark', '|')
    ],
    [
        'KonaNotation',
        ' code',
        lineSpan('k3hl-code', ' code')
    ],
    [
        'KonaNotation',
        '>引用',
        lineSpan(
            'k3hl-quote',
            span('k3hl-mark', '&gt;') + '引用'
        )
    ],
    [
        'KonaNotation',
        '// comment',
        span('k3hl-comment', '// comment')
    ],
    [
        'KonaNotation',
        '#plugin(arg)',
        span('k3hl-mark', '#') +
            span('k3hl-plugin', 'plugin') +
            '(arg)'
    ],
    [
        'KonaNotation',
        "[[表示>https://example.com]]",
        span('k3hl-mark', '[[') +
            span('k3hl-link', '表示') +
            span('k3hl-mark', '&gt;') +
            span('k3hl-url', 'https://example.com') +
            span('k3hl-mark', ']]')
    ],
    [
        'KonaNotation',
        '[[未完',
        '[[未完'
    ],
    [
        'KonaNotation',
        '&note(a(\'\'b\'\'));;',
        '&amp;' +
            span('k3hl-plugin', 'note') +
            span('k3hl-mark', '(') +
            "a(''b'')" +
            span('k3hl-mark', ')') +
            span('k3hl-mark', ';') +
            ';'
    ],
    [
        'KonaNotation',
        "''太字''",
        span('k3hl-mark', "''") +
            span('k3hl-strong', '太字') +
            span('k3hl-mark', "''")
    ],
    [
        'KonaNotation',
        "'''x'''",
        span('k3hl-mark', "''") +
            span('k3hl-strong', "'x") +
            span('k3hl-mark', "''") +
            "'"
    ],
    [
        'KonaNotation',
        '``強調2``',
        span('k3hl-mark', '``') +
            span('k3hl-strong2', '強調2') +
            span('k3hl-mark', '``')
    ],
    [
        'KonaNotation',
        '%%赤%%',
        span('k3hl-mark', '%%') +
            span('k3hl-red', '赤') +
            span('k3hl-mark', '%%')
    ],
    [
        'KonaNotation',
        '{漢字|かんじ}',
        span('k3hl-mark', '{') +
            '漢字' +
            span('k3hl-mark', '|') +
            'かんじ' +
            span('k3hl-mark', '}')
    ],
    [
        'KonaNotation',
        'https://example.com/a?b=1',
        span('k3hl-url', 'https://example.com/a?b=1')
    ],
    [
        'KonaNotation',
        "''未完",
        "''未完"
    ],
    [
        'KonaNotation',
        '{{{#code\n*見出しではない\n}}}\n*見出し',
        lineSpan(
            'k3hl-code',
            span('k3hl-fence', '{{{#code')
        ) +
            '\n' +
            lineSpan('k3hl-code', '*見出しではない') +
            '\n' +
            lineSpan(
                'k3hl-code',
                span('k3hl-fence', '}}}')
            ) +
            '\n' +
            lineSpan(
                'k3hl-h1',
                span('k3hl-mark', '*') + '見出し'
            )
    ],
    [
        'Markdown',
        '# 見出し',
        lineSpan(
            'k3hl-h1',
            span('k3hl-mark', '#') + ' 見出し'
        )
    ],
    [
        'Markdown',
        '### 見出し ###',
        lineSpan(
            'k3hl-h3',
            span('k3hl-mark', '###') +
                ' 見出し' +
                span('k3hl-mark', ' ###')
        )
    ],
    [
        'Markdown',
        '#7 テスト',
        '#7 テスト'
    ],
    [
        'Markdown',
        '> 引用',
        lineSpan(
            'k3hl-quote',
            span('k3hl-mark', '&gt;') + ' 引用'
        )
    ],
    [
        'Markdown',
        '- 項目',
        span('k3hl-mark k3hl-list', '- ') + '項目'
    ],
    [
        'Markdown',
        '12) 項目',
        span('k3hl-mark k3hl-list', '12) ') + '項目'
    ],
    [
        'Markdown',
        '**太字**',
        span('k3hl-mark', '**') +
            span('k3hl-strong', '太字') +
            span('k3hl-mark', '**')
    ],
    [
        'Markdown',
        '__太字__',
        span('k3hl-mark', '__') +
            span('k3hl-strong', '太字') +
            span('k3hl-mark', '__')
    ],
    [
        'Markdown',
        '*斜体*',
        span('k3hl-mark', '*') +
            span('k3hl-em', '斜体') +
            span('k3hl-mark', '*')
    ],
    [
        'Markdown',
        '_斜体_',
        span('k3hl-mark', '_') +
            span('k3hl-em', '斜体') +
            span('k3hl-mark', '_')
    ],
    [
        'Markdown',
        'snake_case_name',
        'snake_case_name'
    ],
    [
        'Markdown',
        '**閉じていない',
        '**閉じていない'
    ],
    [
        'Markdown',
        '~~削除~~',
        span('k3hl-mark', '~~') +
            span('k3hl-del', '削除') +
            span('k3hl-mark', '~~')
    ],
    [
        'Markdown',
        '`code **raw**`',
        span(
            'k3hl-code',
            span('k3hl-mark', '`') +
                'code **raw**' +
                span('k3hl-mark', '`')
        )
    ],
    [
        'Markdown',
        '``a`b``',
        span(
            'k3hl-code',
            span('k3hl-mark', '``') +
                'a`b' +
                span('k3hl-mark', '``')
        )
    ],
    [
        'Markdown',
        '[text](https://example.com)',
        span('k3hl-mark', '[') +
            span('k3hl-link', 'text') +
            span('k3hl-mark', ']') +
            span('k3hl-mark', '(') +
            span('k3hl-url', 'https://example.com') +
            span('k3hl-mark', ')')
    ],
    [
        'Markdown',
        '![alt](image.png)',
        span('k3hl-mark', '!') +
            span('k3hl-mark', '[') +
            span('k3hl-link', 'alt') +
            span('k3hl-mark', ']') +
            span('k3hl-mark', '(') +
            span('k3hl-url', 'image.png') +
            span('k3hl-mark', ')')
    ],
    [
        'Markdown',
        '<https://example.com>',
        span('k3hl-url', '&lt;https://example.com&gt;')
    ],
    [
        'Markdown',
        'https://example.com/a',
        span('k3hl-url', 'https://example.com/a')
    ],
    [
        'Markdown',
        '---',
        span('k3hl-mark', '---')
    ],
    [
        'Markdown',
        '|a|**b**|',
        span('k3hl-mark', '|') +
            'a' +
            span('k3hl-mark', '|') +
            span('k3hl-mark', '**') +
            span('k3hl-strong', 'b') +
            span('k3hl-mark', '**') +
            span('k3hl-mark', '|')
    ],
    [
        'Markdown',
        '```js\n# 見出し\n**太字**\n```\n# 見出し',
        span('k3hl-fence', '```js') +
            '\n' +
            lineSpan('k3hl-code', '# 見出し') +
            '\n' +
            lineSpan('k3hl-code', '**太字**') +
            '\n' +
            span('k3hl-fence', '```') +
            '\n' +
            lineSpan(
                'k3hl-h1',
                span('k3hl-mark', '#') + ' 見出し'
            )
    ],
    [
        'Markdown',
        '~~~\na\n~~~~',
        span('k3hl-fence', '~~~') +
            '\n' +
            lineSpan('k3hl-code', 'a') +
            '\n' +
            span('k3hl-fence', '~~~~')
    ],
    [
        'Markdown',
        '\\*通常文字',
        '\\*通常文字'
    ],
    [
        'UnknownMode',
        '*Kona扱い',
        lineSpan(
            'k3hl-h1',
            span('k3hl-mark', '*') + 'Kona扱い'
        )
    ]
];

for (const [mode, input, expected] of cases) {
    assert.strictEqual(
        k3hlRender(input, mode),
        expected,
        `${mode}: ${JSON.stringify(input)}`
    );
}

assert.strictEqual(
    k3hlEscape('<script>&</script>'),
    '&lt;script&gt;&amp;&lt;/script&gt;'
);

assert.strictEqual(
    k3hlRender('<script>&', 'KonaNotation'),
    '&lt;script&gt;&amp;'
);

assert.strictEqual(
    k3hlRender('# <script>&', 'Markdown'),
    lineSpan(
        'k3hl-h1',
        span('k3hl-mark', '#') + ' &lt;script&gt;&amp;'
    )
);

assert.strictEqual(
    k3hlRender('a\r\nb\r\n', 'Markdown'),
    'a\r\nb\r\n '
);

assert.deepStrictEqual(
    k3hlInitialState(),
    { fence: null, konaBlock: false }
);

const originalState = k3hlInitialState();
const opened = k3hlTokenizeLine('```', 'Markdown', originalState);
assert.deepStrictEqual(originalState, {
    fence: null,
    konaBlock: false
});
assert.deepStrictEqual(opened.state, {
    fence: { ch: '`', len: 3 },
    konaBlock: false
});

function decodeAndStrip(html) {
    return html
        .replace(/<\/?span(?: class="[^"]*")?>/g, '')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
}

function expectedPlainText(input) {
    return input.endsWith('\n') ? input + ' ' : input;
}

function assertInvariant(input, mode) {
    const html = k3hlRender(input, mode);

    assert.strictEqual(
        decodeAndStrip(html),
        expectedPlainText(input),
        `invariant ${mode}: ${JSON.stringify(input)}`
    );

    const tags = html.match(/<\/?[^>]+>/g) || [];
    for (const tag of tags) {
        assert.ok(
            /^<\/?span(?: class="[^"]*")?>$/.test(tag),
            `unexpected tag: ${tag}`
        );
    }
}

const invariantInputs = [
    '',
    '\n',
    '\r\n',
    '<script>alert("&")</script>',
    '* ** *** ****',
    "'''x''' ''a %%b%%''",
    '[[a>b]] [[broken',
    '&plugin((a), b);',
    '{base|ruby}',
    'https://example.com/a?x=1&y=2',
    '# title ###',
    '#7 not-heading',
    '**bold _nested_**',
    'snake_case_name',
    '`<tag>&`',
    '```js\n# title\n</span>&\n```',
    '{{{#plugin\n* title\n}}}',
    '|a|b|c|',
    '・-・item',
    '> quote',
    '\\* escaped-ish',
    'a\r\n# b\r\n'
];

for (const input of invariantInputs) {
    assertInvariant(input, 'KonaNotation');
    assertInvariant(input, 'Markdown');
}

let seed = 0x13579bdf;

function random32() {
    seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
    return seed;
}

const alphabet = [
    'a', 'Z', '0', ' ', '\t', '\n', '\r',
    '*', '_', '~', '`', "'", '%', '#', '>',
    '<', '&', '[', ']', '(', ')', '{', '}',
    '|', '/', '\\', '-', '+', '=', '.', ':',
    ';', '!', '?', '■', '●', '▲', '▼', '・',
    '＋', '＞', '♪', '日', '本', '語'
];

for (let n = 0; n < 1000; n += 1) {
    const length = random32() % 160;
    let input = '';

    for (let i = 0; i < length; i += 1) {
        input += alphabet[random32() % alphabet.length];
    }

    assertInvariant(input, 'KonaNotation');
    assertInvariant(input, 'Markdown');
}

const noThrowInputs = [
    null,
    undefined,
    0,
    false,
    '',
    '*'.repeat(10000),
    '`'.repeat(10000),
    '_'.repeat(10000),
    '['.repeat(10000),
    '&x(' + '('.repeat(1000) + ')'.repeat(1000) + ');',
    '{{{\n' + '# x\n'.repeat(100) + '}}}',
    '```\n' + '**x**\n'.repeat(100) + '```'
];

for (const input of noThrowInputs) {
    assert.doesNotThrow(() => {
        k3hlRender(input, 'KonaNotation');
        k3hlRender(input, 'Markdown');
    });
}

// --- Fence length rules (a shorter fence does not close a longer one) ---
assert.strictEqual(
    decodeAndStrip(k3hlRender('````\n```\ninside\n````\nout', 'Markdown')),
    '````\n```\ninside\n````\nout'
);
assert.ok(k3hlRender('````\n```\ninside\n````\nout', 'Markdown').includes('k3hl-code">```'));
assert.ok(!k3hlRender('~~~\n``` x\n~~~\nout', 'Markdown').includes('k3hl-h'));
console.log('edit_highlight_core.test.js: all tests passed');
