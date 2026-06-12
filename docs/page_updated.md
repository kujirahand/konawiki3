# ページ更新フックシステム (write event)

KonaWiki3 では、ページの保存・更新・削除時に様々な後続処理（タグ更新、メタデータ保存、Discord連携、Git連携など）をフック（イベント・リスナー）経由で実行する仕組みを提供しています。

---

## 概要

これまでは保存アクション (`edit.inc.php`) の中に各処理が直接書き込まれていましたが、フックシステムが導入されたことで、特定のイベントに紐づく処理を外部から追加・拡張できるようになりました。

イベントの登録は [kona3engine/kona3page_updated.inc.php](../kona3engine/kona3page_updated.inc.php) で行われ、KonaWiki3 の初期化時に自動で読み込まれます。

---

## フック API

共通ライブラリ [kona3engine/kona3lib.inc.php](../kona3engine/kona3lib.inc.php) にて、以下のグローバル関数が提供されています。

### `kona3addHook($hookName, $callback)`
フックに対してコールバック関数を登録します。

- **`$hookName`** (`string`): イベント名。
- **`$callback`** (`callable`): トリガー時に呼び出されるコールバック関数。

### `kona3triggerHook($hookName, ...$args)`
登録されたフックを順番にすべて実行します。

- **`$hookName`** (`string`): トリガーするイベント名。
- **`$args`**: コールバックに渡される引数。

---

## `write` イベント（ページ更新）

ページが保存・更新、あるいは内容が空になり削除された場合に発生するイベントです。

### コールバック関数の仕様

```php
function($page, $body, $options = [])
```

- **`$page`** (`string`): 更新されたページ名。
- **`$body`** (`string`): 保存された本文（ページ削除時は空文字 `""` ）。
- **`$options`** (`array`): 更新時のコンテキスト情報（以下を参照）。

#### `$options` のキー詳細

| キー | 型 | 説明 |
| :--- | :--- | :--- |
| `i_mode` | `string` | 保存リクエストのモード。 `'form'` (通常の編集フォーム画面保存), `'ajax'` (一時保存・オートセーブ), `'git'` (Git連携による保存時) など。 |
| `a_mode` | `string` | 保存処理のアクションモード。 `'trywrite'` または `'trygit'`。 |
| `edit_ext` | `string` | 保存されるファイルの拡張子（例: `'txt'`, `'md'`）。 |
| `tags` | `string` | 編集画面から送信されたタグ文字列（ `/` 区切り）。 |
| `page_mode` | `string` | ページのパーサモード（ `'Markdown'` または `'KonaNotation'` ）。 |
| `old_alias_target` | `string\|false` | 編集前に設定されていた別名（エイリアス）のターゲット。 |
| `new_alias_target` | `string\|false` | 編集後に新しく設定された別名（エイリアス）のターゲット。 |

---

## デフォルトで登録されているフック

[kona3engine/kona3page_updated.inc.php](../kona3engine/kona3page_updated.inc.php) では、初期状態で以下の3つのフックが `write` イベントに登録されています。

### 1. タグ・メタデータ・エイリアス同期フック
ページの作成・更新・削除に応じて、以下の処理を実行します。
- メタ情報（`.meta/ページ名.json`）の作成・更新、およびページ削除時のメタファイル削除
- タグDBの更新、および削除時のタグのクリア
- 本文内に含まれる `!!alias(対象)` の差分検知に基づくエイリアス同期

### 2. Discord Webhook 送信フック
- `discord_webhook_url` が設定されている場合に実行されます。
- ページの更新（削除時は除く）を検知し、自動的に Discord チャンネルへ非同期で通知を行います。

### 3. Git 連携フック
- `git_enabled` が `true` の場合に実行されます。
- `i_mode` が `git` もしくは通常フォーム保存時など、特定の条件下において、更新されたファイルを自動で Git コミットし、リモートリポジトリへプッシュします。
- 処理に失敗した場合は `Exception` をスローし、編集画面にエラーレスポンスを返します。

---

## 独自のフックを追加する

KonaWiki3 のプラグインなどから、ページ更新時に独自の処理を実行させたい場合は、 `kona3addHook` を使ってコールバックを登録できます。

### 登録例 (プラグインなどでの実装例)

```php
// ページ更新時に外部のロギングシステムに通知する例
kona3addHook('write', function($page, $body, $options = []) {
    // 削除時はログに記録するだけ
    if (trim($body) === '') {
        my_custom_log("Page deleted: " . $page);
        return;
    }

    // 更新時はページ名と文字数を記録
    $char_count = mb_strlen($body);
    my_custom_log("Page updated: {$page} ({$char_count} chars)");
});
```
