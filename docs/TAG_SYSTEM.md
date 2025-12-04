# タグシステムのドキュメント

## 概要

KonaWiki3のタグシステムは、SQLiteベースからファイルベースに変更されました。
これにより、よりシンプルで保守しやすいタグ管理が可能になります。

## 使い方

### タグを追加する

編集画面で、タグ入力欄にタグをスラッシュ(`/`)区切りで入力します。

### タグ付きページ一覧を表示する

特定のタグを持つページ一覧を表示するには、`#tags(タグ名)` プラグインを使用します。

```
#tags(PHP)
```

オプション:
- `sort=mtime` : 更新日時順（デフォルト）
- `sort=page` : ページ名順
- `limit=30` : 表示件数（デフォルト30）

例:
```
#tags(PHP,sort=page,limit=50)
```

### 全タグ一覧を表示する

システムに登録されているすべてのタグを表示するには、`#taglist` プラグインを使用します。

```
#taglist
```

## データ保存形式

タグ情報は、下記の2カ所に保存されます。

1. `data/.kona3_tag/{タグ名}.json` ディレクトリにJSON形式で保存されます。
2. 各ページのタグ情報は、`{タイトル}.meta.json` ファイルに保存されます。

`data/.kona3_tag/{タグ名}.json` ディレクトリ構造例:

```text
data/.kona3_tag/
  ├── PHP.json
  ├── プログラミング.json
  └── Web開発.json
```

`data/.kona3_tag/{タグ名}.json` JSONファイルの内容:

```json
[
  {
    "page": "PageName1",
    "page_id": 123,
    "mtime": 1234567890
  },
  {
    "page": "PageName2",
    "page_id": 124,
    "mtime": 1234567891
  }
]
```

ページごとのタグ情報は、`{タイトル}.meta.json` ファイルに保存されます。

- [meta_info.md を参照](docs/meta_info.md)

## 旧バージョンの　SQLiteからの移行

### 自動移行

初回起動時に `data/.kona3_tag/` ディレクトリが存在しない場合、自動的にSQLiteの `tags` テーブルからデータを移行します。

移行処理は以下の手順で実行されます:

1. SQLiteの `tags` テーブルからすべてのタグデータを読み込む
2. タグごとにJSONファイルを作成
3. SQLiteの `tags` テーブルを空にする

### 手動確認

移行が正常に完了したか確認するには:

```bash
# タグディレクトリを確認
ls -la data/.kona3_tag/

# SQLiteのtagsテーブルが空になっているか確認
sqlite3 private/info.sqlite "SELECT COUNT(*) FROM tags;"
```

## API関数

### kona3tags_addPageTag($page, $tag)

ページにタグを追加します。

```php
kona3tags_addPageTag('MyPage', 'PHP');
```

### kona3tags_removePageTag($page, $tag)

ページからタグを削除します。

```php
kona3tags_removePageTag('MyPage', 'PHP');
```

### kona3tags_clearPageTags($page)

ページからすべてのタグを削除します。

```php
kona3tags_clearPageTags('MyPage');
```

### kona3tags_getPageTags($page)

ページに設定されているタグの一覧を取得します。

```php
$tags = kona3tags_getPageTags('MyPage');
// => ['PHP', 'プログラミング']
```

### kona3tags_getPages($tag, $sort = 'mtime', $limit = 30)

特定のタグを持つページ一覧を取得します。

```php
$pages = kona3tags_getPages('PHP', 'mtime', 10);
// => [
//   ['page' => 'Page1', 'page_id' => 1, 'mtime' => 1234567890],
//   ['page' => 'Page2', 'page_id' => 2, 'mtime' => 1234567891],
// ]
```

### kona3tags_getAllTags()

すべてのタグ一覧を取得します。

```php
$all_tags = kona3tags_getAllTags();
// => ['PHP', 'JavaScript', 'Python']
```

## テスト

タグシステムのテストは以下のコマンドで実行できます:

```bash
just test
```

## 注意事項

- タグ名に使用できる文字: 英数字、日本語、アンダースコア、ハイフン
- タグ名に使用できない文字: ドット(`.`)、スペース
- タグファイル名は安全のため、特殊文字はアンダースコアに変換されます
- 大文字小文字は区別されます（`PHP` と `php` は別のタグ）
- ドット(`.`)はファイル拡張子と混同するため使用できません

## 互換性

- 旧バージョンのSQLiteベースのタグデータは自動的に移行されます
- `kona3db_writePage()` の `$tags` パラメータは互換性のため残されていますが、使用されません
- 既存の `#tags()` プラグインはそのまま動作します（内部実装が変更されただけ）
