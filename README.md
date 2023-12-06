# KonaWiki3

 - KonaWiki3 is Wiki clone.
 - [URL] http://kujirahand.com/konawiki3/

## KonaWiki3の目標

KonaWikiは原稿の執筆やマニュアルの作成、情報の共有に特化したシンプルなWikiです。
Wikiのデータはただのテキストファイルなので、Gitと連携して詳細な差分を取ることもできます。
開発者は何年もテキストエディタの代わりにKonaWiki3を使用しています。小
さくシンプルなKonawikiを使って大きな仕事を成し遂げましょう。

## セキュリティアップデートのお知らせ (2021/02/22)

Konawiki v3.2.3 より前のバージョンをご利用の方は最新版にアップデートしてください。
> JVNVU#99880454 / JVN#48194211 / CVE-2020-5670

## KonaWiki3 is simple.

It is very simple PHP wiki engine. (Looks like PukiWiki).

```
* header

text text text text

** header2

table:

| table | test
| aaa | bbb

list:

- item1
- item2
- item3

code:

{{{#code(js)
console.log('hello');
}}}
```

## 日本語Wiki記法のサポート

KonaWiki2に由来する、日本語Wiki記法をサポートしています。

```
■大見出し

text text text text

●中見出し

text text text text

▲小見出し

text text text text

リスト

・item1
・item2
・item3
```

# How to install Konawiki3

- 1. Install WebServer and PHP
- 2. Download
  - `git clone --recursive https://github.com/kujirahand/konawiki3.git`
  - OR `git clone https://github.com/kujirahand/konawiki3.git` AND `git submodule update --init --recursive`

## Install Library (Option)

When you want to use Git or Markdown, execute shell commands.

```sh
cd kona3engine
composer install
```

## Output PDF (Option)

 - 設定画面で PDF 出力をtrueに設定
 - 文字化けするときは、/vendor/fonts に拡張子が.ttfのフォントを配置してください。
 - 詳しくは[こちら](https://kujirahand.com/konawiki3/index.php?PDF%E5%87%BA%E5%8A%9B%E6%A9%9F%E8%83%BD)

## Git support (Option)

You can commit and push wiki diffs to your git repository.

```sh
# set your remote repository in `/data` dir
cd data
git remote add origin git@github.com:hoge/fuga.git
```

And set git_enabled to true at the config.

## Config page

First login by Admin User, and access config page.

```
[URI] index.php?go&editConf
```

## Plugin list

```
[URI] index.php?FrontPage&plugin&name=pluginlist
```
