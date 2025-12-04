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

```markdown
\`\`\`#pluginname(arg1, arg2)
arg3
\`\`\`
```

Or `:::` syntax:

```markdown
:::pluginname(arg1, arg2)
arg3
:::
```
