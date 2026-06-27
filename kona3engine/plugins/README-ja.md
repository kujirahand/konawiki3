# Konawiki3 プラグイン

Konawiki3には、Wikiの機能を簡単に拡張するためのプラグイン機構があります。
プラグインを作ることで、ページ部品の追加、独自マークアップの描画、外部サービスとの連携、
JavaScriptやフォームから使う小さなAPI風アクションの提供などができます。

## Wikiページでプラグインを使う

KonaNotationでは、行頭に `#pluginName(...)` と書くとプラグインを呼び出せます。

```text
■ KonaNotation plugin

#myplugin(arg1,arg2)
```

プラグインによっては、本文付きのブロックとして呼び出すこともできます。
KonaNotationでは、`{{{ ... }}}` ブロックの先頭にプラグイン呼び出しを書きます。
ブロック本文は、最後の引数としてプラグインに渡されます。

```text
● Block plugin

{{{#myplugin(arg2)
arg1
}}}
```

Markdownページでは、ブロックプラグイン呼び出しに `!!pluginName(...)` を使います。

```md
# Markdown

!!myplugin(arg1,arg2)
```

Markdownページでも、フェンス付きのプラグイン記法で本文を渡せます。

```md
## Block plugin

:::myplugin(arg2)
arg1
:::
```

フェンスには3個以上のコロンを使えます。本文に `:::` だけの行を含めたい場合は、
`::::` のように長いフェンスで開始し、開始時と同じ数以上のコロンだけの行で閉じます。

## プラグイン一覧

同梱プラグインは、このディレクトリで確認できます。

<https://github.com/kujirahand/konawiki3/tree/master/kona3engine/plugins>

自分のKonawiki3環境では、次のURLでプラグイン一覧ページを開けます。

```text
index.php?FrontPage&plugin&name=pluginlist
```

このページには、インストール済みプラグインと、各プラグインファイルから取得した短い説明が表示されます。

## プラグインを作成する

`kona3engine/plugins/` にPHPファイルを作成します。
ファイル名は、プラグイン名に `.inc.php` を付けた名前にします。

例えば、`myplugin` というプラグインを作る場合は、次のファイルを作成します。

```text
kona3engine/plugins/myplugin.inc.php
```

次に、`kona3plugins_<plugin_name>_execute` という名前の実行関数を定義します。
この関数は引数の配列を受け取り、ページに挿入するHTMLを返します。

```php
<?php
/**
 * 簡単なメッセージを表示します。
 * - [書式] #myplugin(message, className)
 * - [引数]
 * -- message ... 表示するテキスト。
 * -- className ... 任意のCSSクラス。
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

ページからは次のように使えます。

```text
#myplugin(Hello,notice)
```

この呼び出しは、`kona3plugins_myplugin_execute()` が返したHTMLとして表示されます。

## プラグイン名と関数名

Konawiki3は、次の規則でプラグイン名を解決します。

| プラグイン名 | ファイル名 | 実行関数 |
| ------------ | ---------- | -------- |
| `myplugin` | `myplugin.inc.php` | `kona3plugins_myplugin_execute` |
| `taglist` | `taglist.inc.php` | `kona3plugins_taglist_execute` |
| `sakuramml_sf` | `sakuramml_sf.inc.php` | `kona3plugins_sakuramml_sf_execute` |

ページ内で描画するプラグイン名には、英数字とアンダースコアを使うのが安全です。
これにより、プラグイン名とPHPの実行関数名を対応させやすくなります。
プラグインアクションのURLではハイフンも受け付けます。
Konawiki3がアクション関数を探すとき、ハイフンはアンダースコアに変換されます。

## 引数

引数は通常のPHP配列として渡されます。
パーサーはカンマで引数を分割します。

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

KonaNotationでは、全角チルダで区切る形式も使えます。

```text
#myplugin～arg1～arg2～arg3
```

## ブロック本文の引数

プラグインをソースブロックから呼び出すと、ブロック本文が引数配列の末尾に追加されます。

```text
{{{#myplugin(title)
This is the body text.
}}}
```

プラグインには次の配列が渡されます。

```php
[
    'title',
    "This is the body text.\n"
]
```

これは、コード、図、音楽記法、表など、複数行の内容を描画するプラグインに便利です。

```php
function kona3plugins_myplugin_execute($args) {
    $title = array_shift($args);
    $body = array_shift($args);

    $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $body = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');

    return "<section class='myplugin'><h3>{$title}</h3><pre>{$body}</pre></section>";
}
```

## 任意の初期化処理

プラグインには、`kona3plugins_<plugin_name>_init` という名前の初期化関数を定義できます。

```php
function kona3plugins_myplugin_init() {
    // 補助ファイルの読み込み、一度だけ必要な設定、リソース準備などを行います。
}
```

この関数が存在する場合、Konawiki3はプラグインを描画する前に呼び出します。

## プラグインアクション

フォーム、Ajaxリクエスト、ダウンロードなどのために、別エンドポイントが必要なプラグインもあります。
その場合は、同じプラグインファイル内に `kona3plugins_<plugin_name>_action` を定義します。

```php
function kona3plugins_myplugin_action() {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true]);
}
```

アクションは、`plugin` アクションとプラグイン名を指定して呼び出します。

```text
index.php?FrontPage&plugin&name=myplugin
```

プラグインHTMLからリンクやフォームの送信先を生成する場合は、`kona3getPageURL()` を使います。

```php
function kona3plugins_myplugin_execute($args) {
    global $kona3conf;

    $page = $kona3conf['page'];
    $url = kona3getPageURL($page, 'plugin', '', 'name=myplugin');
    $url = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    return "<a href='{$url}'>Open myplugin action</a>";
}
```

## セキュリティ上の注意

プラグインが返すHTMLは、そのままページに挿入されます。
ユーザーが制御できる値は、HTMLを返す前に必ずエスケープしてください。

- テキストや属性値には `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` を使います。
- ファイル名、パス、CSSクラス、URLは、使う前に検証します。
- プラグイン引数、`$_GET`、`$_POST`、Cookie、ページ本文の値を信用しないでください。
- フォームや状態を変更するアクションでは、必要に応じてログイン状態や編集トークンを確認します。
- ファイルアクセスは、Konawiki3が想定するディレクトリ内に限定します。

## プラグインを無効化する

プラグインは `plugin_disallow` 設定で無効化できます。
無効化されたプラグインは実行されません。

## 参考になる既存プラグイン

次の同梱プラグインは実装例として参考になります。

- `ls.inc.php`: Wikiページ一覧の表示と引数処理の例。
- `include.inc.php`: 別ページの取り込みと複数引数処理の例。
- `comment.inc.php`: 描画処理とアクション処理の両方を持つ例。
- `mermaid.inc.php`: ブロック本文を使う図表プラグインの例。
- `pluginlist.inc.php`: インストール済みプラグイン一覧と説明の取得例。
