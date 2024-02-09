# KonaWiki3 (日本語マニュアル)

 - KonaWiki3 is Wiki clone.
 - [URL] http://kujirahand.com/konawiki3/

## KonaWiki3の目標

KonaWikiは原稿の執筆やマニュアルの作成、情報の共有に特化したシンプルなWikiです。
Wikiのデータはただのテキストファイルなので、Gitと連携して詳細な差分を取ることもできます。
開発者は何年もテキストエディタの代わりにKonaWiki3を使用しています。
小さくシンプルなKonawikiを使って大きな仕事を成し遂げましょう。

なお、設定オプションで、KonaWiki記法だけでなく、Markdownで保存することも可能です。
好きな記法を利用してマークアップできます。

## 日本語Wiki記法をサポートしています

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

## PukiWikiに似た記法を採用

とてもシンプルなPukiWikiっぽいWiki記法もサポート！

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

オプションで、Markdownに切り替えもできるので、好きな記法で書けるのがKonaWikiの良いところです！

## Konawiki3のインストールの仕方

Gitコマンドが使えるなら、コマンド一発で設置が可能ですし、
[releases](https://github.com/kujirahand/konawiki3/releases)からZIPでダウンロードしてFTPSなどでアップロードして動かすこともできます。
その際、テンプレートエンジンも一緒にアップロードするのをお忘れなく。

### Gitを使う方法

- 1. ApacheなどのWebサーバーとPHPをインストール
- 2. 以下のどちらかのコマンドを実行
  - 2-1. `git clone --recursive https://github.com/kujirahand/konawiki3.git`
  - 2-2. `git clone https://github.com/kujirahand/konawiki3.git` AND `git submodule update --init --recursive`

### Gitを使わない方法:

- 1. [KonaWiki3のreleaseからZIPをダウンロード](https://github.com/kujirahand/konawiki3/releases)
- 2. [テンプレートエンジンのZIPをダウンロード](https://github.com/kujirahand/php_fw_simple/releases)
- 3. テンプレートエンジンを解凍して `./kona3engine/fw_simple` に配置

下記のような構造になるようにします。

```
- index.php
- <data>
- <cache>
- <private>
+ <kona3engine>
    + <fw_simple>
      - README.md
      - index.lib.php
      ...
    - <action>
    - <template>
    - <lang>
    ...
```

### 共通

データファイルの直接アクセス保護のために `data/.htaccess` を配置するのをオススメします。

```
# access limitation
<Files *.txt>
Order deny,allow
Deny from all
</Files>
<Files *.md>
Order deny,allow
Deny from all
</Files>
```

## (オプション) Git保存機能を使う場合

Git保存機能を使う時は、composerを使ってインストールしてください。

```sh
cd kona3engine
composer install
```

ただし、レンタルサーバーによっては、うまく動かないことがあるので、その場合は、SSHからコミットしてください。
ローカルでは正しく動くものの、ロリポップなどでは動かなかったので、CRONで定期的にコミットするようにしています。

```sh
cd konawiki_path
git pull
git add ./data
git commit -a
git push
```

## (オプション) PDF機能

- 設定画面で PDF 出力をtrueに設定
- 文字化けするときは、/vendor/fonts に拡張子が.ttfのフォントを配置してください。
- 詳しくは[こちら](https://kujirahand.com/konawiki3/index.php?PDF%E5%87%BA%E5%8A%9B%E6%A9%9F%E8%83%BD)

## Gitと連携する場合

下記のようにして、dataディレクトリをGitの管理下に置くと便利かも。

```sh
# set your remote repository in `/data` dir
cd data
git remote add origin git@github.com:hoge/fuga.git
```

そして、設定画面で git_enabled をTRUEに設定してください。

## 設定ページ

最初に、管理ユーザーを設定したら、その後、設定ページにアクセスできます。

```
[URI] index.php?go&editConf
```

## Plugin list

```
[URI] index.php?FrontPage&plugin&name=pluginlist
```

## セキュリティアップデートのお知らせ (2021/02/22)

Konawiki v3.2.3 より前のバージョンをご利用の方は最新版にアップデートしてください。

> JVNVU#99880454 / JVN#48194211 / CVE-2020-5670
