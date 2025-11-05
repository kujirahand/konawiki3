# KonaWiki3 開発ガイド for AI Agents

このドキュメントは、AIエージェントがKonaWiki3の開発を支援する際の参考資料です。

## プロジェクト概要

KonaWiki3は、ファイルベースの軽量Wikiエンジンです。PHP製で、Konawiki記法とMarkdownの両方をサポートしています。

- **言語**: PHP
- **データ保存**: ファイルベース (テキストファイル)
- **特徴**: Git連携、AI支援機能、シンプルな設計

## ディレクトリ構造

```
konawiki3/
├── index.php                    # エントリーポイント
├── kona3dir.def.php            # ディレクトリマッピング (自動生成)
├── kona3engine/                # コアエンジン
│   ├── index.inc.php           # エンジン初期化
│   ├── action/                 # アクションハンドラー
│   ├── template/               # HTMLテンプレート
│   ├── plugins/                # プラグイン
│   ├── lang/                   # 言語ファイル
│   └── php_fw_simple/          # テンプレートエンジン
├── data/                       # Wikiページデータ (.txt, .md)
├── private/                    # 設定ファイル (JSON-PHP)
├── cache/                      # コンパイル済みテンプレート
└── skin/                       # テーマ/スキン
```

## アーキテクチャ

### リクエストフロー

1. **エントリー**: `index.php` → `kona3dir.def.php` をロード
2. **初期化**: `kona3engine/index.inc.php` → `kona3index_main()`
3. **URL解析**: `kona3lib_parseURI()` でアクションとページ名を抽出
4. **実行**: `kona3lib_execute()` で対応するアクションを実行
5. **レンダリング**: テンプレートエンジンでHTMLを生成

### アクションシステム

アクションは `kona3engine/action/` に配置され、URL経由で呼び出されます。

**URL形式**: `index.php?PageName&action&status`

#### 主要アクション一覧

| ファイル | アクション | 説明 |
|---------|-----------|------|
| `show.inc.php` | `show` | ページ表示 (デフォルト) |
| `edit.inc.php` | `edit` | ページ編集 |
| `admin.inc.php` | `admin` | 管理画面 |
| `login.inc.php` | `login` | ログイン |
| `logout.inc.php` | `logout` | ログアウト |
| `signup.inc.php` | `signup` | ユーザー登録 |
| `search.inc.php` | `search` | ページ検索 |
| `attach.inc.php` | `attach` | ファイル添付 |
| `pdf.inc.php` | `pdf` | PDF出力 |
| `print.inc.php` | `print` | 印刷用表示 |
| `plugin.inc.php` | `plugin` | プラグイン管理 |
| `text.inc.php` | `text` | テキスト出力 |
| `data.inc.php` | `data` | データ管理 |
| `resource.inc.php` | `resource` | リソース配信 |
| `skin.inc.php` | `skin` | スキン管理 |
| `user.inc.php` | `user` | ユーザー管理 |
| `users.inc.php` | `users` | ユーザー一覧 |
| `emailLogs.inc.php` | `emailLogs` | メールログ |
| `editConf.inc.php` | `editConf` | 設定編集 |
| `new.inc.php` | `new` | 新規ページ作成 |
| `go.inc.php` | `go` | リダイレクト処理 |
| `update_page_history.inc.php` | `update_page_history` | 履歴更新 |

#### アクションの作成方法

```php
<?php
// kona3engine/action/myaction.inc.php

function kona3_action_myaction() {
    global $kona3conf;
    $page = $kona3conf["page"];
    
    // アクション処理
    $data = [
        'title' => 'My Action',
        'content' => 'Hello, World!'
    ];
    
    // テンプレートレンダリング
    kona3template('myaction.html', $data);
}
```

### テンプレートシステム

テンプレートは `kona3engine/template/` と `cache/` に配置されます。

#### 主要テンプレート一覧

| ファイル | 用途 |
|---------|------|
| `show.html` | ページ表示 |
| `edit.html` | ページ編集フォーム |
| `login.html` | ログインフォーム |
| `signup.html` | ユーザー登録フォーム |
| `signup_verify.html` | メール認証画面 |
| `admin_conf.html` | 設定画面 |
| `admin_user.html` | ユーザー管理 |
| `search.html` | 検索結果 |
| `attach.html` | 添付ファイル |
| `print.html` | 印刷用 |
| `user.html` | ユーザープロフィール |
| `users.html` | ユーザー一覧 |
| `message.html` | メッセージ表示 |
| `parts_header.html` | ヘッダー部品 |
| `parts_footer.html` | フッター部品 |
| `white.html` | 空白ページ |
| `emailLogs.html` | メールログ |

#### テンプレート構文

```php
{{$variable}}                    // 変数出力
{{if $condition}}...{{/if}}      // 条件分岐
{{for $items as $item}}...{{/for}} // ループ
{{include file.html}}            // ファイル読み込み
```

#### テンプレートの呼び出し

```php
// kona3lib.inc.php の kona3template() を使用
kona3template('mytemplate.html', [
    'title' => 'タイトル',
    'body' => 'コンテンツ'
]);
```

### プラグインシステム

プラグインは `kona3engine/plugins/` に配置され、Wiki記法内で使用されます。

**構文**: `{{{#pluginname(args)}}}`

#### プラグインの作成

```php
<?php
// kona3engine/plugins/myplugin.inc.php

function konawiki_plugin_myplugin($args) {
    // $args は引数配列
    $text = isset($args[0]) ? $args[0] : '';
    
    // HTML を返す
    return "<div class='my-plugin'>{$text}</div>";
}
```

#### プラグイン設定

- **エイリアス**: `$kona3conf['plugin_alias']` で別名を設定
- **無効化**: `$kona3conf['plugin_disallow']` で禁止リストを設定

### パーサーエンジン

`kona3parser.inc.php` と `kona3parser_md.inc.php` で実装されています。

#### サポートする記法

**Konawiki記法**:
```
■大見出し
●中見出し
▲小見出し
・リスト
```

**PukiWiki風記法**:
```
* 見出し
** 中見出し
- リスト
```

**Markdown**:
ページが `.md` 拡張子の場合、Markdownとして解析されます。

#### パース処理

```php
// トークン化 → レンダリング
$tokens = konawiki_parser_parse($text);
$html = konawiki_parser_render($tokens);
```

### 設定システム

設定は `private/kona3conf.json.php` に保存されます。

**形式**: JSON + PHP保護ヘッダー
```php
<?php die(); ?>
{"key": "value", ...}
```

#### 主要設定項目

- `git_enabled`: Git連携の有効化
- `openai_apikey`: OpenAI API キー (AI機能用)
- `allow_upload_ext`: 許可するアップロード拡張子
- `plugin_alias`: プラグインエイリアス
- `plugin_disallow`: 禁止プラグインリスト
- `session_name`: セッション名

#### 設定の読み込み

```php
global $kona3conf;
$value = $kona3conf['key'];
```

### データベース層

`kona3db.inc.php` でファイルベースとSQLiteを併用しています。

- **ページデータ**: `data/` にテキストファイルとして保存
- **メタデータ**: `private/info.sqlite` に保存 (ページID、履歴など)

#### ファイル操作

```php
// ロック付き保存/読み込み (排他制御)
kona3lock_save($filepath, $data);
$data = kona3lock_load($filepath);

// ページIDの取得/作成
$page_id = kona3db_getPageId($page_name, $create = TRUE);
```

### セキュリティ

- **認証**: セッションベース (`kona3login.inc.php`)
- **パストラバーサル対策**: `kona3lib_parseURI()` でチェック
- **ファイルアップロード制限**: `allow_upload_ext` 設定
- **XSS対策**: テンプレートエンジンで自動エスケープ
- **CSRF対策**: フレーム保護ヘッダー (`X-Frame-Options: SAMEORIGIN`)

## 開発タスク

### 新しいアクションの追加

1. `kona3engine/action/newaction.inc.php` を作成
2. `kona3_action_newaction()` 関数を実装
3. 対応する `kona3engine/template/newaction.html` を作成
4. URL `index.php?PageName&newaction` でアクセス

### 新しいプラグインの追加

1. `kona3engine/plugins/myplugin.inc.php` を作成
2. `konawiki_plugin_myplugin($args)` 関数を実装
3. Wiki記法で `{{{#myplugin(args)}}}` を使用

### テンプレートの編集

- オリジナル: `kona3engine/template/*.html`
- キャッシュ: `cache/*.html.php` (自動生成)
- スキン別: `skin/*/action.html.php` (優先される)

### AI機能の拡張

`kona3ai.inc.php` にChatGPT API のラッパーが実装されています。

```php
// AI プロンプトの実行
$response = kona3ai_chat($system_prompt, $user_prompt);
```

言語別プロンプトは `kona3engine/lang/ja-ai_prompt.md` などに記載されています。

## コーディング規約

### ファイル命名

- アクション: `action/actionname.inc.php`
- プラグイン: `plugins/pluginname.inc.php`
- テンプレート: `template/actionname.html`
- ページデータ: `data/PageName.txt` または `data/PageName.md`

### 関数命名

- アクション: `kona3_action_actionname()`
- プラグイン: `konawiki_plugin_pluginname($args)`
- ライブラリ: `kona3lib_functionname()`

### グローバル変数

```php
global $kona3conf;  // 設定配列
global $page;       // 現在のページ名
```

## テストの方法

`kona3engine/tests/` にユニットテストがあります。
下記のコマンドを実行してテストできます。

```sh
cd kona3engine/tests/
./test.sh
```

### テストを作成する手順

`tests/` に新しいテストファイル(hoge.test.php)を作成します。そして、下記のように記述します。

```php
<?php
require_once __DIR__ . '/test_common.inc.php';

$cond_a = "a";
$cond_b = "a";
test_eq(__LINE__, $cond_a, $cond_b, "test_eq example");
```

### ローカル開発

```bash
# Docker Compose で起動
docker-compose up

# ブラウザで http://localhost:8080 にアクセス
```

### ファイルパーミッション

```bash
chmod 766 data cache private
```

### 初期設定

1. `index.php?go&editConf` で管理画面にアクセス
2. 管理者ユーザーを作成
3. 設定を変更

## 参考リンク

- **プロジェクトサイト**: http://kujirahand.com/konawiki3/
- **GitHubリポジトリ**: https://github.com/kujirahand/konawiki3
- **日本語マニュアル**: README-ja.md
- **英語マニュアル**: README.md

## トラブルシューティング

### プラグイン一覧の確認

`index.php?FrontPage&plugin&name=pluginlist`

### 設定の直接編集

`private/kona3conf.json.php` (PHP部分の後にJSON形式)

### キャッシュのクリア

```bash
rm -rf cache/*
```

### Git連携の有効化

設定で `git_enabled` を `true` に設定

## まとめ

KonaWiki3はシンプルなアーキテクチャを持つファイルベースのWikiです。アクション、プラグイン、テンプレートの3つの主要な拡張ポイントを理解すれば、容易にカスタマイズできます。

AIエージェントとして開発を支援する際は:

1. **アクション追加**: 新機能は `kona3engine/action/` に実装
2. **UI変更**: テンプレートは `kona3engine/template/` を編集
3. **マークアップ拡張**: プラグインを `kona3engine/plugins/` に追加
4. **設定変更**: `private/kona3conf.json.php` を直接編集または管理画面から変更

既存のコードパターンに従い、シンプルさを保ちながら機能を追加してください。
