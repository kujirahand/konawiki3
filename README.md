# KonaWiki3

 - KonaWiki3 is Wiki clone.
 - [URL] http://kujirahand.com/konawiki3/


## セキュリティアップデートのお知らせ (2021/02/22)

Konawiki v3.2.3 より前のバージョンをご利用の方は最新版にアップデートしてください。
XSS/ディレクトリトラバーサルなどの脆弱性がありました。現在は修正されています。


## KonaWiki3の目標

KonaWikiは原稿の執筆やマニュアルの作成に特化したシンプルなWikiです。Wikiのデータはただのテキストファイルなので、Gitと連携して詳細な差分を取ることもできます。
開発者は何年もテキストエディタの代わりにKonaWiki3を使用しています。小さくシンプルなKonawikiを使って大きな仕事を成し遂げましょう。


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

- 1. Install WebServer and PHP7
- 2. Download
 - ``git clone https://github.com/kujirahand/konawiki3.git``

## Install Library (Option)

When you want to use Git or Markdown, execute shell commands.

```
cd kona3engine
composer install
```

## Git support (Option)

You can commit and push wiki diffs to your git repository.

```
# set your remote repository in data/
$ cd data
$ git remote add origin git@github.com:hoge/fuga.git
```

And set git_enabled to true at the config.

## Config page

First login by Admin User, and access config page.

```
[URI] index.php?go&editConf
```

