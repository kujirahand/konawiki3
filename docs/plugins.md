# プラグイン仕様

KonaWiki3 のプラグインは、Wiki 記法の中から呼び出せる PHP の拡張機能です。

## 配置場所と検索順序

| 順序 | ディレクトリ | 用途 |
|------|--------------|------|
| 1 | `kona3engine/plugins/*.inc.php` | 標準プラグイン |
| 2 | `data/.plugins/*.inc.php` | ローカルプラグイン |

- プラグイン名から、まず標準プラグインを探します。見つからない場合のみ `data/.plugins/` を探します。
- 同名のプラグインがある場合は**標準プラグインが優先**されます。ローカルプラグインで標準プラグインを上書きすることはできません。
- `data/.plugins/` は別リポジトリで管理し、`data/.plugins` へ clone / symlink する運用ができます。

## ファイル名とプラグイン名

- ファイル名は `プラグイン名.inc.php` です。
- プラグイン名に含まれる `/` と `.` は取り除かれます(パストラバーサル対策)。
- 日本語などの名前は `urlencode` した名前がファイル名になります。`%` は関数名では `_` に置換されます。
- `plugin_alias` 設定で別名を付けられます。
- `plugin_disallow` 設定(カンマ区切り)に名前を書くと、標準・ローカルを問わず無効になります。

## 関数

プラグインファイルには次の関数を定義します(`NAME` はプラグイン名)。

| 関数 | 必須 | 説明 |
|------|------|------|
| `kona3plugins_NAME_execute($args)` | Wikiページから使う場合 | HTML 文字列を返す。`$args` は引数の配列 |
| `kona3plugins_NAME_init()` | 任意 | ファイル読み込み直後に一度呼ばれる |
| `kona3plugins_NAME_action()` | URL から使う場合 | `index.php?Page&plugin&name=NAME` で呼ばれる |

プラグイン名の `-` は関数名では `_` に置き換えます。

## 例

`data/.plugins/hello.inc.php`:

```php
<?php
/** 挨拶を表示するプラグイン
 * - [書式] #hello(名前)
 */
function kona3plugins_hello_execute($args) {
    $name = isset($args[0]) ? $args[0] : 'World';
    return "<p>Hello, " . htmlspecialchars($name) . "!</p>";
}
```

Wiki ページでの使用:

```text
#hello(KonaWiki)
```

Markdown では `!!hello(KonaWiki)` も使えます。

## セキュリティ上の注意

- ローカルプラグインは PHP コードとして実行されます。信頼できるものだけを `data/.plugins/` に置いてください。
- 出力に利用者の入力を含める場合は `htmlspecialchars` でエスケープしてください。
- `data/.plugins/` への書き込み権限は管理者に限定してください。

## プラグイン一覧

`index.php?FrontPage&plugin&name=pluginlist` に標準プラグインとローカルプラグインの両方が表示されます。
先頭コメントの `/** ...` 1行目が説明として使われます。
