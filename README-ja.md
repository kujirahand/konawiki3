# Konawiki3 (日本語マニュアル)

Konawiki3は、原稿の執筆、マニュアルの作成、情報の共有に特化して設計されたシンプルなWikiです。

- Konawiki3はWikiクローンです。
- [URL] http://kujirahand.com/konawiki3/
- [English README](README.md)

## Konawiki3の目標

Wiki内のデータは**ただのプレーンテキスト**ファイルなので、Gitと連携して詳細な差分を追跡することができます。テキストエディタの代わりにKonawiki3を使用できます。この小さくてシンプルなKonawikiを使って、素晴らしいタスクを成し遂げましょう。

また、設定オプションにより、`KonaNotation` (Kona記法) だけでなく `Markdown` (マークダウン) でも保存が可能です。お好みの記法を使用してマークアップできます。

## テキストベースのWiki

`KonaNotation` と `Markdown` の両方をサポートするプレーンテキストファイルでページを記述できます。これにより、Gitを使用した変更の追跡や、コンテンツの効率的な管理が容易になります。

また、各ページの `KonaNotation` と `Markdown` は簡単に切り替えることができます。この柔軟性により、ニーズや好みに最適なマークアップ言語を選択できます。設定での切り替えに加え、ファイルの拡張子で切り替えることも可能です。`plain.txt` という名前のファイルは自動的に `KonaNotation` として扱われ、`plain.md` というファイルは自動的に Markdown として処理されます。

### KonaNotationの例 (*.txt)

Konawiki記法はシンプルなマークアップ言語です。以下に例を示します：

```text
* ヘッダー

テキスト テキスト テキスト テキスト

** ヘッダー2

テーブル:

| テーブル | テスト
| aaa | bbb

リスト:

- 項目1
- 項目2
- 項目3

コード:

{{{#code
console.log('hello');
}}}


プラグイン:

#comment
```

### KonaNotaionの日本語対応Wiki記法 (*.txt)

日本語特有の記法もサポートしています。

```text
■大見出し

テキスト テキスト テキスト テキスト

●中見出し

テキスト テキスト テキスト テキスト

▲小見出し

テキスト テキスト テキスト テキスト

リスト

・item1
・item2
・item3
```

### Markdownの例 (*.md)

Markdownは広く使用されているマークアップ言語です。以下に例を示します：

```markdown
# ヘッダー

テキスト テキスト テキスト テキスト

## ヘッダー2

テーブル:

| テーブル | テスト |
| --- | --- |
| aaa | bbb |

リスト:

- 項目1
- 項目2

\`\`\`js
console.log('hello');
\`\`\`

プラグイン:

!!comment
```

### AI執筆支援機能

AIによる執筆支援機能を実装しています。これには、テキストの要約、校正、文章の言い換え、続きの自動執筆などが含まれます。
[ChatGPTのAPIキー](https://platform.openai.com/api-keys)を取得し、Konawikiの設定に指定することで、AI支援機能にアクセスできます。

## ページ整理のためのタグシステム

ファイルベースのタグシステムが組み込まれています。ページに `#tag(タグ名)` を追加することで、カテゴリごとにページを整理できます。
タグを使用してページを分類し、`#tags(タグ名)` を使用して特定のタグが付いたページの一覧を表示します。

詳細については、[タグシステムドキュメント](docs/TAG_SYSTEM.md)を参照してください。

## Konawiki3のインストール方法

### Gitコマンドを使用する場合

1. WebサーバーとPHPをインストールする
2. リポジトリをクローンする

```sh
git clone https://github.com/kujirahand/konawiki3.git
```

### ホスティングサーバーにアップローダーを使用する場合

1. [リリース](https://github.com/kujirahand/konawiki3/releases)からKonawiki3のzipファイルをダウンロードする
2. 解凍する
3. パーミッションを変更する

```sh
chmod 766 data
chmod 766 cache
chmod 766 private
```

詳しくは、[インストール手順](https://kujirahand.com/konawiki3/index.php?install)も参照してください。

### Dockerを使用する場合 (ローカル環境)

ターミナルから以下のコマンドを実行すると起動します。PHPのローカルサーバー機能を利用してKonawiki3を実行します。

```sh
docker-compose up
```

## ライブラリのインストール (オプション)

Git保存機能を使用したい場合は、composerを使用してインストールします。

```sh
cd kona3engine
composer install
```

※ レンタルサーバーによっては、PHPスクリプトからのGit操作が制限されている場合があります。その場合はSSH経由でコミットするか、cron等で以下のように定期コミットを行ってください。

```sh
cd konawiki_path
git pull
git add ./data
git commit -a
git push
```

## 設定ページ

最初に管理者ユーザーでログインし、設定ページにアクセスします。

```text
[URI] index.php?go&editConf
```

## プラグイン一覧

```text
[URI] index.php?FrontPage&plugin&name=pluginlist
```

## PDF出力 (オプション)

1.設定ページにアクセスし、PDF出力を TRUE に設定する
2.文字化けする場合は、`./vendor/fonts` にフォント(`.ttf`)を配置する - [PDF出力マニュアル](https://kujirahand.com/konawiki3/index.php?PDF%E5%87%BA%E5%8A%9B%E6%A9%9F%E8%83%BD)

## Gitサポート (オプション)

Wikiの差分をコミットしてGitリポジトリにプッシュできます。

```sh
# dataディレクトリにリモートリポジトリを設定します
cd data
git remote add origin git@github.com:hoge/fuga.git
```

そして、設定で `git_enabled` を `true` に設定します。

## 開発向け

タスクランナーの `just` を使用しています。
テストを実行するには、`just test` を実行します。
