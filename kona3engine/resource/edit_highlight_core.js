'use strict';

function k3hlEscape(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function k3hlInitialState() {
    return { fence: null, konaBlock: false };
}

function k3hlSpan(className, value) {
    return '<span class="' + className + '">' + value + '</span>';
}

function k3hlLineSpan(className, value) {
    return k3hlSpan('k3hl-line ' + className, value);
}

function k3hlCloneState(state) {
    var src = state || {};
    var fence = src.fence
        ? { ch: src.fence.ch, len: src.fence.len }
        : null;

    return {
        fence: fence,
        konaBlock: src.konaBlock === true
    };
}

function k3hlFindPluginEnd(text, openParenIndex) {
    var depth = 0;
    var i;

    for (i = openParenIndex; i < text.length; i += 1) {
        if (text.charAt(i) === '(') {
            depth += 1;
        } else if (text.charAt(i) === ')') {
            depth -= 1;
            if (depth === 0) {
                return i;
            }
        }
    }

    return -1;
}

function k3hlKonaInline(text, depth) {
    var result = '';
    var i = 0;
    var limit = typeof depth === 'number' ? depth : 0;

    if (limit >= 10) {
        return k3hlEscape(text);
    }

    while (i < text.length) {
        var rest = text.slice(i);
        var end;
        var middle;
        var separator;
        var match;

        if (rest.slice(0, 2) === '[[') {
            end = text.indexOf(']]', i + 2);
            if (end !== -1) {
                middle = text.slice(i + 2, end);
                separator = middle.indexOf('>');
                result += k3hlSpan('k3hl-mark', '[[');

                if (separator === -1) {
                    result += k3hlSpan('k3hl-link', k3hlEscape(middle));
                } else {
                    result += k3hlSpan(
                        'k3hl-link',
                        k3hlEscape(middle.slice(0, separator))
                    );
                    result += k3hlSpan('k3hl-mark', '&gt;');
                    result += k3hlSpan(
                        'k3hl-url',
                        k3hlEscape(middle.slice(separator + 1))
                    );
                }

                result += k3hlSpan('k3hl-mark', ']]');
                i = end + 2;
                continue;
            }
        }

        match = rest.match(/^&([A-Za-z0-9_]+)\(/);
        if (match) {
            var openParenIndex = i + match[0].length - 1;
            end = k3hlFindPluginEnd(text, openParenIndex);

            if (end !== -1) {
                result += k3hlEscape('&');
                result += k3hlSpan('k3hl-plugin', k3hlEscape(match[1]));
                result += k3hlSpan('k3hl-mark', '(');
                result += k3hlEscape(text.slice(openParenIndex + 1, end));
                result += k3hlSpan('k3hl-mark', ')');

                i = end + 1;
                if (text.charAt(i) === ';') {
                    result += k3hlSpan('k3hl-mark', ';');
                    i += 1;
                }
                continue;
            }
        }

        if (rest.slice(0, 2) === '``') {
            end = text.indexOf('``', i + 2);
            if (end !== -1) {
                result += k3hlSpan('k3hl-mark', '``');
                result += k3hlSpan(
                    'k3hl-strong2',
                    k3hlKonaInline(text.slice(i + 2, end), limit + 1)
                );
                result += k3hlSpan('k3hl-mark', '``');
                i = end + 2;
                continue;
            }
        }

        if (rest.slice(0, 2) === "''") {
            end = text.indexOf("''", i + 2);
            if (end !== -1) {
                result += k3hlSpan('k3hl-mark', "''");
                result += k3hlSpan(
                    'k3hl-strong',
                    k3hlKonaInline(text.slice(i + 2, end), limit + 1)
                );
                result += k3hlSpan('k3hl-mark', "''");
                i = end + 2;
                continue;
            }
        }

        if (rest.slice(0, 2) === '%%') {
            end = text.indexOf('%%', i + 2);
            if (end !== -1) {
                result += k3hlSpan('k3hl-mark', '%%');
                result += k3hlSpan(
                    'k3hl-red',
                    k3hlKonaInline(text.slice(i + 2, end), limit + 1)
                );
                result += k3hlSpan('k3hl-mark', '%%');
                i = end + 2;
                continue;
            }
        }

        if (text.charAt(i) === '{') {
            end = text.indexOf('}', i + 1);
            if (end !== -1) {
                middle = text.slice(i + 1, end);
                separator = middle.indexOf('|');

                if (separator !== -1) {
                    result += k3hlSpan('k3hl-mark', '{');
                    result += k3hlEscape(middle.slice(0, separator));
                    result += k3hlSpan('k3hl-mark', '|');
                    result += k3hlEscape(middle.slice(separator + 1));
                    result += k3hlSpan('k3hl-mark', '}');
                    i = end + 1;
                    continue;
                }
            }
        }

        match = rest.match(
            /^(https?|ftp):\/\/[\w\d.#$%&()\-=_~^|,./?+!\[\]@]+/
        );
        if (match) {
            result += k3hlSpan('k3hl-url', k3hlEscape(match[0]));
            i += match[0].length;
            continue;
        }

        result += k3hlEscape(text.charAt(i));
        i += 1;
    }

    return result;
}

function k3hlIsAlphaNumeric(ch) {
    return typeof ch === 'string' && /^[A-Za-z0-9]$/.test(ch);
}

function k3hlValidUnderscoreDelimiter(text, index, length) {
    var before = index > 0 ? text.charAt(index - 1) : '';
    var after = index + length < text.length
        ? text.charAt(index + length)
        : '';

    return !(k3hlIsAlphaNumeric(before) && k3hlIsAlphaNumeric(after));
}

function k3hlFindMarkdownDelimiter(text, marker, start) {
    var index = text.indexOf(marker, start);

    while (index !== -1) {
        if (
            marker.charAt(0) !== '_' ||
            k3hlValidUnderscoreDelimiter(text, index, marker.length)
        ) {
            return index;
        }
        index = text.indexOf(marker, index + 1);
    }

    return -1;
}

function k3hlMarkdownDelimited(text, index, marker, className, depth) {
    if (
        marker.charAt(0) === '_' &&
        !k3hlValidUnderscoreDelimiter(text, index, marker.length)
    ) {
        return null;
    }

    var end = k3hlFindMarkdownDelimiter(
        text,
        marker,
        index + marker.length
    );

    if (end === -1 || end === index + marker.length) {
        return null;
    }

    return {
        html:
            k3hlSpan('k3hl-mark', k3hlEscape(marker)) +
            k3hlSpan(
                className,
                k3hlMarkdownInline(
                    text.slice(index + marker.length, end),
                    depth + 1
                )
            ) +
            k3hlSpan('k3hl-mark', k3hlEscape(marker)),
        end: end + marker.length
    };
}

function k3hlMarkdownLink(text, index) {
    var image = text.slice(index, index + 2) === '![';
    var bracketStart = image ? index + 1 : index;

    if (text.charAt(bracketStart) !== '[') {
        return null;
    }

    var bracketEnd = text.indexOf(']', bracketStart + 1);
    if (bracketEnd === -1 || text.charAt(bracketEnd + 1) !== '(') {
        return null;
    }

    var parenEnd = text.indexOf(')', bracketEnd + 2);
    if (parenEnd === -1) {
        return null;
    }

    var html = '';
    if (image) {
        html += k3hlSpan('k3hl-mark', '!');
    }

    html += k3hlSpan('k3hl-mark', '[');
    html += k3hlSpan(
        'k3hl-link',
        k3hlEscape(text.slice(bracketStart + 1, bracketEnd))
    );
    html += k3hlSpan('k3hl-mark', ']');
    html += k3hlSpan('k3hl-mark', '(');
    html += k3hlSpan(
        'k3hl-url',
        k3hlEscape(text.slice(bracketEnd + 2, parenEnd))
    );
    html += k3hlSpan('k3hl-mark', ')');

    return { html: html, end: parenEnd + 1 };
}

function k3hlMarkdownInline(text, depth) {
    var result = '';
    var i = 0;
    var limit = typeof depth === 'number' ? depth : 0;

    if (limit >= 10) {
        return k3hlEscape(text);
    }

    while (i < text.length) {
        var ch = text.charAt(i);
        var rest = text.slice(i);
        var match;
        var end;
        var token;

        if (ch === '`') {
            match = rest.match(/^`+/);
            var ticks = match[0];
            end = text.indexOf(ticks, i + ticks.length);

            if (end !== -1) {
                result += k3hlSpan(
                    'k3hl-code',
                    k3hlSpan('k3hl-mark', ticks) +
                    k3hlEscape(text.slice(i + ticks.length, end)) +
                    k3hlSpan('k3hl-mark', ticks)
                );
                i = end + ticks.length;
                continue;
            }
        }

        if (ch === '[' || rest.slice(0, 2) === '![') {
            token = k3hlMarkdownLink(text, i);
            if (token) {
                result += token.html;
                i = token.end;
                continue;
            }
        }

        if (rest.slice(0, 2) === '**') {
            token = k3hlMarkdownDelimited(
                text,
                i,
                '**',
                'k3hl-strong',
                limit
            );
            if (token) {
                result += token.html;
                i = token.end;
                continue;
            }

            result += k3hlEscape('**');
            i += 2;
            continue;
        }

        if (rest.slice(0, 2) === '__') {
            token = k3hlMarkdownDelimited(
                text,
                i,
                '__',
                'k3hl-strong',
                limit
            );
            if (token) {
                result += token.html;
                i = token.end;
                continue;
            }

            result += k3hlEscape('__');
            i += 2;
            continue;
        }

        if (
            ch === '*' &&
            text.charAt(i - 1) !== '*' &&
            text.charAt(i + 1) !== '*'
        ) {
            token = k3hlMarkdownDelimited(
                text,
                i,
                '*',
                'k3hl-em',
                limit
            );
            if (token) {
                result += token.html;
                i = token.end;
                continue;
            }
        }

        if (
            ch === '_' &&
            text.charAt(i - 1) !== '_' &&
            text.charAt(i + 1) !== '_'
        ) {
            token = k3hlMarkdownDelimited(
                text,
                i,
                '_',
                'k3hl-em',
                limit
            );
            if (token) {
                result += token.html;
                i = token.end;
                continue;
            }
        }

        if (rest.slice(0, 2) === '~~') {
            token = k3hlMarkdownDelimited(
                text,
                i,
                '~~',
                'k3hl-del',
                limit
            );
            if (token) {
                result += token.html;
                i = token.end;
                continue;
            }

            result += k3hlEscape('~~');
            i += 2;
            continue;
        }

        match = rest.match(/^<https?:\/\/[^<>\s]+>/);
        if (match) {
            result += k3hlSpan('k3hl-url', k3hlEscape(match[0]));
            i += match[0].length;
            continue;
        }

        match = rest.match(/^https?:\/\/[^\s<]+/);
        if (match) {
            result += k3hlSpan('k3hl-url', k3hlEscape(match[0]));
            i += match[0].length;
            continue;
        }

        result += k3hlEscape(ch);
        i += 1;
    }

    return result;
}

function k3hlTable(line, inlineFunction) {
    var result = '';
    var start = 0;
    var i;

    for (i = 0; i < line.length; i += 1) {
        if (line.charAt(i) === '|') {
            result += inlineFunction(line.slice(start, i), 0);
            result += k3hlSpan('k3hl-mark', '|');
            start = i + 1;
        }
    }

    result += inlineFunction(line.slice(start), 0);
    return result;
}

function k3hlTokenizeKonaLine(line, state) {
    var nextState = k3hlCloneState(state);
    var match;
    var level;
    var content;

    if (nextState.konaBlock) {
        if (line.trim() === '}}}') {
            nextState.konaBlock = false;
            return {
                html: k3hlLineSpan(
                    'k3hl-code',
                    k3hlSpan('k3hl-fence', k3hlEscape(line))
                ),
                state: nextState
            };
        }

        return {
            html: k3hlLineSpan('k3hl-code', k3hlEscape(line)),
            state: nextState
        };
    }

    if (line.slice(0, 3) === '{{{') {
        nextState.konaBlock = true;
        return {
            html: k3hlLineSpan(
                'k3hl-code',
                k3hlSpan('k3hl-fence', k3hlEscape(line))
            ),
            state: nextState
        };
    }

    match = line.match(/^(\*+)(.*)$/);
    if (match && match[2] !== '') {
        level = Math.min(match[1].length, 6);
        return {
            html: k3hlLineSpan(
                'k3hl-h' + level,
                k3hlSpan('k3hl-mark', k3hlEscape(match[1])) +
                k3hlKonaInline(match[2], 0)
            ),
            state: nextState
        };
    }

    match = line.match(/^([■□●○▲△▼▽])(.*)$/);
    if (match) {
        if (match[1] === '■' || match[1] === '□') {
            level = 1;
        } else if (match[1] === '●' || match[1] === '○') {
            level = 2;
        } else if (match[1] === '▲' || match[1] === '△') {
            level = 3;
        } else {
            level = 4;
        }

        return {
            html: k3hlLineSpan(
                'k3hl-h' + level,
                k3hlSpan('k3hl-mark', k3hlEscape(match[1])) +
                k3hlKonaInline(match[2], 0)
            ),
            state: nextState
        };
    }

    if (/^-{2,}$/.test(line)) {
        return {
            html: k3hlSpan('k3hl-mark', k3hlEscape(line)),
            state: nextState
        };
    }

    match = line.match(/^([\-・]+)(.*)$/);
    if (match) {
        return {
            html:
                k3hlSpan(
                    'k3hl-mark k3hl-list',
                    k3hlEscape(match[1])
                ) +
                k3hlKonaInline(match[2], 0),
            state: nextState
        };
    }

    match = line.match(/^([+＋]+)(.*)$/);
    if (match) {
        return {
            html:
                k3hlSpan(
                    'k3hl-mark k3hl-list',
                    k3hlEscape(match[1])
                ) +
                k3hlKonaInline(match[2], 0),
            state: nextState
        };
    }

    if (line.charAt(0) === '|') {
        return {
            html: k3hlTable(line, k3hlKonaInline),
            state: nextState
        };
    }

    if (/^[ \t]/.test(line)) {
        return {
            html: k3hlLineSpan('k3hl-code', k3hlEscape(line)),
            state: nextState
        };
    }

    match = line.match(/^(>|＞)(.*)$/);
    if (match) {
        return {
            html: k3hlLineSpan(
                'k3hl-quote',
                k3hlSpan('k3hl-mark', k3hlEscape(match[1])) +
                k3hlKonaInline(match[2], 0)
            ),
            state: nextState
        };
    }

    if (line.slice(0, 2) === '//') {
        return {
            html: k3hlSpan('k3hl-comment', k3hlEscape(line)),
            state: nextState
        };
    }

    match = line.match(/^([#♪])([A-Za-z0-9_]+)(.*)$/);
    if (match) {
        return {
            html:
                k3hlSpan('k3hl-mark', k3hlEscape(match[1])) +
                k3hlSpan('k3hl-plugin', k3hlEscape(match[2])) +
                k3hlEscape(match[3]),
            state: nextState
        };
    }

    return {
        html: k3hlKonaInline(line, 0),
        state: nextState
    };
}

function k3hlTokenizeMarkdownLine(line, state) {
    var nextState = k3hlCloneState(state);
    var match;
    var fenceMatch;
    var marker;
    var level;
    var body;
    var closingMatch;

    if (nextState.fence) {
        var closePattern = new RegExp(
            '^ {0,3}' +
            (nextState.fence.ch === '`' ? '`' : '~') +
            '{' +
            nextState.fence.len +
            ',}[ \\t]*$'
        );

        if (closePattern.test(line)) {
            nextState.fence = null;
            return {
                html: k3hlSpan('k3hl-fence', k3hlEscape(line)),
                state: nextState
            };
        }

        return {
            html: k3hlLineSpan('k3hl-code', k3hlEscape(line)),
            state: nextState
        };
    }

    fenceMatch = line.match(/^( {0,3})(`{3,}|~{3,})(.*)$/);
    if (fenceMatch) {
        marker = fenceMatch[2];
        nextState.fence = {
            ch: marker.charAt(0),
            len: marker.length
        };
        return {
            html: k3hlSpan('k3hl-fence', k3hlEscape(line)),
            state: nextState
        };
    }

    match = line.match(/^( {0,3})(#{1,6})(\s+|$)(.*)$/);
    if (match) {
        level = match[2].length;
        body = match[3] + match[4];
        closingMatch = body.match(/^(.*?)(\s+#+\s*)$/);

        if (closingMatch) {
            body =
                k3hlMarkdownInline(closingMatch[1], 0) +
                k3hlSpan(
                    'k3hl-mark',
                    k3hlEscape(closingMatch[2])
                );
        } else {
            body = k3hlMarkdownInline(body, 0);
        }

        return {
            html: k3hlLineSpan(
                'k3hl-h' + level,
                k3hlEscape(match[1]) +
                k3hlSpan('k3hl-mark', match[2]) +
                body
            ),
            state: nextState
        };
    }

    match = line.match(/^( {0,3})(>+)(.*)$/);
    if (match) {
        return {
            html: k3hlLineSpan(
                'k3hl-quote',
                k3hlEscape(match[1]) +
                k3hlSpan('k3hl-mark', k3hlEscape(match[2])) +
                k3hlMarkdownInline(match[3], 0)
            ),
            state: nextState
        };
    }

    match = line.match(/^(\s*)([-*+]|\d{1,9}[.)])(\s+)(.*)$/);
    if (match) {
        return {
            html:
                k3hlSpan(
                    'k3hl-mark k3hl-list',
                    k3hlEscape(match[1] + match[2] + match[3])
                ) +
                k3hlMarkdownInline(match[4], 0),
            state: nextState
        };
    }

    if (/^ {0,3}([-*_])(\s*\1){2,}\s*$/.test(line)) {
        return {
            html: k3hlSpan('k3hl-mark', k3hlEscape(line)),
            state: nextState
        };
    }

    if (/^\s*\|/.test(line)) {
        return {
            html: k3hlTable(line, k3hlMarkdownInline),
            state: nextState
        };
    }

    return {
        html: k3hlMarkdownInline(line, 0),
        state: nextState
    };
}

function k3hlTokenizeLine(line, mode, state) {
    var value = String(line);
    var hasCarriageReturn =
        value.length > 0 && value.charAt(value.length - 1) === '\r';
    var source = hasCarriageReturn
        ? value.slice(0, value.length - 1)
        : value;
    var currentState = k3hlCloneState(state || k3hlInitialState());
    var result;

    if (mode === 'Markdown') {
        result = k3hlTokenizeMarkdownLine(source, currentState);
    } else {
        result = k3hlTokenizeKonaLine(source, currentState);
    }

    if (hasCarriageReturn) {
        result.html += '\r';
    }

    return result;
}

function k3hlRender(text, mode) {
    var source = String(text);
    var lines = source.split('\n');
    var state = k3hlInitialState();
    var output = [];
    var actualMode = mode === 'Markdown'
        ? 'Markdown'
        : 'KonaNotation';
    var i;
    var tokenized;

    for (i = 0; i < lines.length; i += 1) {
        tokenized = k3hlTokenizeLine(lines[i], actualMode, state);
        output.push(tokenized.html);
        state = tokenized.state;
    }

    var html = output.join('\n');
    if (source.slice(-1) === '\n') {
        html += ' ';
    }

    return html;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        k3hlEscape: k3hlEscape,
        k3hlInitialState: k3hlInitialState,
        k3hlTokenizeLine: k3hlTokenizeLine,
        k3hlRender: k3hlRender
    };
}
