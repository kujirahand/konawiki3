# Konawiki3 Markdown Documentation

Konawiki3 supports saving pages in `Markdown` format. When a page is saved with a `.md` extension, it is treated as a Markdown file. This allows users to utilize the familiar Markdown syntax for formatting their content.

## Konawiki Markdown Features

- Standard Markdown syntax is supported, including headings, lists, links, images, code blocks, and more.
- Extended features such as plugins.

### Using Plugins in Markdown

Plugins can be used within Markdown files using the following syntax:

```markdown
!!pluginname(arg1, arg2, arg3, ...)
```

Or using code block syntax:

````markdown
```#pluginname(arg1, arg2)
arg3
```
````

Or `:::` syntax:

```markdown
:::pluginname(arg1, arg2)
arg3
:::
```

The colon fence can use three or more colons. This is useful when the plugin
body itself contains a `:::` line. Close the block with a line that has at
least as many colons as the opening fence.

```markdown
::::pluginname(arg1, arg2)
arg3
:::
arg4
::::
```

### HTML Comments

HTML comments (`<!-- ... -->`) can be used as notes that are not rendered.
They are removed from the output, so they never appear on the page (not even
as escaped text).

```markdown
<!-- This is a comment. It will not be displayed. -->
Visible text <!-- inline comment --> continues here.
```

Comments may span multiple lines. Everything between `<!--` and `-->` is
ignored, including Markdown markup, so a multi-line comment can be used
to temporarily disable a whole section:

```markdown
<!--
# This heading is disabled
- and so is this list
-->
```

Text after `-->` on the same line is rendered as usual. An unterminated
comment runs to the end of the page. Inside fenced code blocks and inline
code spans, `<!-- ... -->` is treated as literal code and stays visible.
Writing `\<!--` also shows the marker as literal text instead of starting
a comment.

A multi-line comment can also start at the end of a heading, list item,
quote or table row; the following lines are then hidden until `-->`.
Inline code spans may use multiple backticks (for example ``code`` or
`` ` ``), which makes it possible to write the comment syntax itself as
code: ``<!--``.
