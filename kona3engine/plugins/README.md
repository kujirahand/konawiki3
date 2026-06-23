# Konawiki3 Plugins

Konawiki3 includes a plugin system that makes it easy to extend the wiki.
Plugins can add page components, render custom markup, integrate with external
services, or provide small API-like actions for JavaScript and forms.

- [日本語の説明](/kona3engine/plugins/README-ja.md)

## Using Plugins in Wiki Pages

In KonaNotation, call a plugin by writing `#pluginName(...)` at the beginning of
a line.

```text
■ KonaNotation plugin

#myplugin(arg1,arg2)
```

Some plugins can also receive a block body. In KonaNotation, write the plugin
call at the beginning of a `{{{ ... }}}` block. The block content is passed to
the plugin as the last argument.

```text
● Block plugin

{{{#myplugin(arg2)
arg1
}}}
```

In Markdown pages, use `!!pluginName(...)` for a block plugin call.

```md
# Markdown

!!myplugin(arg1,arg2)
```

Markdown pages can also pass a block body with the fenced plugin syntax.

```md
## Block plugin

:::myplugin(arg2)
arg1
:::
```

## Plugin List

You can browse the bundled plugins in this directory:

<https://github.com/kujirahand/konawiki3/tree/master/kona3engine/plugins>

You can also open the plugin list page in your own Konawiki3 installation:

```text
index.php?FrontPage&plugin&name=pluginlist
```

That page shows the installed plugins with the short description taken from
each plugin file.

## Creating a Plugin

Create a PHP file in `kona3engine/plugins/`. The file name must be the plugin
name followed by `.inc.php`.

For example, a plugin named `myplugin` should be saved as:

```text
kona3engine/plugins/myplugin.inc.php
```

Then define an execute function named `kona3plugins_<plugin_name>_execute`.
This function receives an array of arguments and must return the HTML that will
be inserted into the rendered page.

```php
<?php
/**
 * Displays a simple message.
 * - [Syntax] #myplugin(message, className)
 * - [Arguments]
 * -- message ... Text to display.
 * -- className ... Optional CSS class.
 */

function kona3plugins_myplugin_execute($args) {
    $message = array_shift($args);
    $class_name = array_shift($args);

    if ($message === null || $message === '') {
        $message = 'Hello from myplugin.';
    }
    if ($class_name === null || $class_name === '') {
        $class_name = 'myplugin';
    }

    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $class_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $class_name);

    return "<div class='{$class_name}'>{$message}</div>";
}
```

You can use the plugin from a page like this:

```text
#myplugin(Hello,notice)
```

The result is the HTML returned by `kona3plugins_myplugin_execute()`.

## Plugin Names and Function Names

Konawiki3 resolves plugin names with the following convention:

| Plugin name | File name | Execute function |
| ----------- | --------- | ---------------- |
| `myplugin` | `myplugin.inc.php` | `kona3plugins_myplugin_execute` |
| `taglist` | `taglist.inc.php` | `kona3plugins_taglist_execute` |
| `sakuramml_sf` | `sakuramml_sf.inc.php` | `kona3plugins_sakuramml_sf_execute` |

For page-rendered plugins, use letters, numbers, and underscores in plugin
names. This keeps the plugin name compatible with the PHP execute function
name. Plugin action URLs also accept hyphens, and hyphens are converted to
underscores when Konawiki3 looks up an action function.

## Arguments

Arguments are passed as a plain PHP array. The parser splits arguments by
commas.

```text
#myplugin(arg1,arg2,arg3)
```

```php
function kona3plugins_myplugin_execute($args) {
    $arg1 = array_shift($args);
    $arg2 = array_shift($args);
    $arg3 = array_shift($args);

    return htmlspecialchars("{$arg1} / {$arg2} / {$arg3}", ENT_QUOTES, 'UTF-8');
}
```

KonaNotation also supports the full-width tilde form:

```text
#myplugin～arg1～arg2～arg3
```

## Block Body Arguments

When a plugin is called through a source block, the block body is appended to
the argument array.

```text
{{{#myplugin(title)
This is the body text.
}}}
```

The plugin receives:

```php
[
    'title',
    "This is the body text.\n"
]
```

This is useful for plugins that render code, diagrams, music notation, tables,
or other multi-line content.

```php
function kona3plugins_myplugin_execute($args) {
    $title = array_shift($args);
    $body = array_shift($args);

    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $body = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

    return "<section class='myplugin'><h3>{$title}</h3><pre>{$body}</pre></section>";
}
```

## Optional Initialization

A plugin may define an initialization function named
`kona3plugins_<plugin_name>_init`.

```php
function kona3plugins_myplugin_init() {
    // Load helper files, register one-time settings, or prepare resources.
}
```

If the function exists, Konawiki3 calls it before rendering the plugin.

## Plugin Actions

Some plugins need a separate endpoint for forms, AJAX requests, downloads, or
other actions. Define `kona3plugins_<plugin_name>_action` in the same plugin
file.

```php
function kona3plugins_myplugin_action() {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
}
```

Call the action through the `plugin` action with the plugin name:

```text
index.php?FrontPage&plugin&name=myplugin
```

Use `kona3getPageURL()` when generating links or form actions from plugin HTML.

```php
function kona3plugins_myplugin_execute($args) {
    global $kona3conf;

    $page = $kona3conf['page'];
    $url = kona3getPageURL($page, 'plugin', '', 'name=myplugin');
    $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    return "<a href='{$url}'>Open myplugin action</a>";
}
```

## Security Notes

Plugin output is inserted directly into the page. Escape any user-controlled
text before returning HTML.

- Use `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` for text and attributes.
- Validate file names, paths, CSS classes, and URLs before using them.
- Do not trust values from plugin arguments, `$_GET`, `$_POST`, cookies, or page
  text.
- For form or state-changing actions, check login state and edit tokens where
  appropriate.
- Keep file access inside the expected Konawiki3 directories.

## Disabling Plugins

Plugins can be disabled through the `plugin_disallow` configuration. Disabled
plugins are not executed.

## Existing Plugins as Examples

These bundled plugins are useful references:

- `ls.inc.php`: lists wiki pages and shows argument handling.
- `include.inc.php`: includes another page and handles multiple arguments.
- `comment.inc.php`: shows a plugin with both rendering and action handling.
- `mermaid.inc.php`: shows a block-body plugin for diagrams.
- `pluginlist.inc.php`: lists installed plugins and reads plugin descriptions.
