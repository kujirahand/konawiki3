# 「タグ」システムのドキュメント

## 概要

タグは、Wikiの各ページに複数のタグを付与して、分類・整理するためのシステムです。
一つのページに複数のタグを付与できます。タグはスラッシュ(`/`)で区切って入力します。

KonaWiki3のタグシステムは、以前はファイルベース（`data/.kona3_tag/*.json`）でしたが、検索高速化のために**SQLiteキャッシュベース（`private/tags.sqlite`）**に変更されました。これにより、多数のタグやページが存在する場合でも、高速にタグ検索やタグ一覧の表示が可能です。

## 使い方

### タグを追加する

編集画面で、タグ入力欄にタグをスラッシュ(`/`)区切りで入力します。

### タグ付きページ一覧を表示する

特定のタグを持つページ一覧を表示するには、`#tags(タグ名)` プラグインを使用します。

```text
#tags(PHP)
```

オプション:

- `sort=mtime` : 更新日時順（デフォルト）
- `sort=page` : ページ名順
- `limit=30` : 表示件数（デフォルト30）

例:

```text
#tags(PHP,sort=page,limit=50)
```

### 全タグ一覧を表示する

システムに登録されているすべてのタグを表示するには、`#taglist` プラグインを使用します。

```text
#taglist
```

### タグの表示と一覧へのリンク

タグが設定されているページの右下に `Tags: tag1/tag2` のようにタグ一覧が表示されます。
`Tags` のラベル部分をクリックすると、登録されているすべてのタグ一覧（Tag list）ページへ遷移します。

---

## キャッシュの更新と再構築

SQLiteのタグキャッシュは以下のタイミングで自動または手動で更新されます。

1. **ページ保存時の自動更新（ページ単位）**
   編集画面でページを保存した際、そのページのタグキャッシュのみが自動的に更新されます。内部的には `DELETE FROM tags WHERE page = ?` を実行した後に、現在のタグを順次 `INSERT` します。
2. **手動での一括再構築（全ページスキャン）**
   ログインユーザーには、`#taglist` プラグイン表示時に「更新」ボタンが表示されます。このボタンをクリックすると、`data/.meta/` ディレクトリ内のすべてのメタファイル（`.json`）を走査してタグ情報を読み込み、SQLiteキャッシュデータベースを完全に再構築します。

---

## データ保存形式

タグキャッシュ情報は、下記のデータベースに保存されます。

- **保存先**: `private/tags.sqlite`

### データベーススキーマ

`tags` テーブルの定義：

```sql
CREATE TABLE IF NOT EXISTS tags (
    tag TEXT,
    page TEXT,
    created_at INTEGER,
    updated_at INTEGER,
    PRIMARY KEY (tag, page)
);
CREATE INDEX IF NOT EXISTS idx_tag ON tags (tag);
CREATE INDEX IF NOT EXISTS idx_page ON tags (page);
```

なお、ページごとのオリジナルなタグ情報は、各ページのメタファイル（`data/.meta/{タイトル}.json`）に保存されています。
- [meta_info.md を参照](docs/meta_info.md)

---

## API関数

### kona3tags_updatePageTags($page, $tags)

指定したページのタグキャッシュを一括更新します（`DELETE` 後に `INSERT`）。

```php
kona3tags_updatePageTags('MyPage', ['PHP', 'SQLite']);
```

### kona3tags_rebuildAll()

すべてのメタファイルから `tags.sqlite` のキャッシュを完全に再構築します。

```php
kona3tags_rebuildAll();
```

### kona3tags_addPageTag($page, $tag)

ページにタグを単体で追加します。

```php
kona3tags_addPageTag('MyPage', 'PHP');
```

### kona3tags_removePageTag($page, $tag)

ページからタグを単体で削除します。

```php
kona3tags_removePageTag('MyPage', 'PHP');
```

### kona3tags_clearPageTags($page)

ページからすべてのタグキャッシュを削除します。

```php
kona3tags_clearPageTags('MyPage');
```

### kona3tags_getPageTags($page)

ページに設定されているタグの一覧を取得します。

```php
$tags = kona3tags_getPageTags('MyPage');
// => ['PHP', 'SQLite']
```

### kona3tags_getPages($tag, $sort = 'mtime', $limit = 30)

特定のタグを持つページ一覧を取得します。

```php
$pages = kona3tags_getPages('PHP', 'mtime', 10);
// => [
//   ['page' => 'Page1', 'mtime' => 1234567890],
//   ['page' => 'Page2', 'mtime' => 1234567891],
// ]
```

### kona3tags_getAllTags()

すべてのタグ一覧を取得します。

```php
$all_tags = kona3tags_getAllTags();
// => ['JavaScript', 'PHP', 'SQLite']
```

---

## テスト

タグシステムのテストは以下のコマンドで実行できます:

```bash
just test
```

タグ関係のテストファイル:
- `tests/kona3tags.test.php`: 基本的なCRUD操作テスト
- `tests/kona3tags_sqlite.test.php`: SQLite DB・インデックス直接検証用テスト
- `tests/kona3tags_migration.test.php`: メタデータからの再構築テスト
- `tests/edit_tags.test.php`: 編集アクション連動テスト
- `tests/tag_length_limit.test.php`: タグ最大長（20文字）テスト

---

## 注意事項

- タグ名に使用できる文字: 英数字、日本語、アンダースコア、ハイフン
- タグ名に使用できない文字: ドット(`.`)、スペース
- 大文字小文字は区別されます（`PHP` と `php` は別のタグ）
- ドット(`.`)はファイル拡張子等と混同するため、タグ名に使用できません
- タグの長さは最大20文字に制限され、これを超える場合は自動的に20文字に切り詰められます。
