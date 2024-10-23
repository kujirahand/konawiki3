# php_fw_simple

simple framework for php

# これは何？

とてもシンプルなPHPフレームワークです。
以下のWebアプリを作るために、作成したものです。

 - [俳句投稿サイト「あやめ」](https://haiku.uta.pw/)
 - [KonaWiki2](https://kujirahand.com/konawiki2/)
 - [KonaWiki3](https://kujirahand.com/konawiki3/)
 - [なでしこ3貯蔵庫](https://n3s.nadesi.com)

フレームワーク使うほどではないけど、何かちょっと最小最低限の仕組みの中で作りたいという場合に最適です。
何が入っているかと言うと、以下のものが入っています。

 - MVCのようなもの
 - テンプレートエンジン
 - データベース(SQLite)を手軽に使うラッパー


# 想定するディレクトリのレイアウト

```
<root>
+- index.php ... メインファイル
+ <action> ... アクションを配置
+ <template> ... テンプレートを配置
+ <cache> ... テンプレートのキャッシュが保存される
+ <php_fw_simple> ... 本ライブラリを丸ごとコピー
```

## 作成する項目

そして、php_fw_simple/index.php にリンク

```file:index.php
<?php
// ディレクトリを指定
$DIR_ACTION = __DIR__.'/action';
$DIR_TEMPLATE = __DIR__.'/template';
$DIR_TEMPLATE_CACHE = __DIR__.'/cache';
// ライブラリを取り込む
require_once 'php_fw_simple/index.lib.php';
```

## アクションの作成

アクションは、上記で指定した $DIR_ACTION に 「(アクション名).action.php」という名前のファイルを作成します。
そして、そのファイルの中で、「action_(アクション名)_default」という関数を定義します。

## テンプレートの使用

アクションの中で以下のように書いて任意のテンプレートを表示するようにすると、MVCっぽくなります。

```
template_render('ファイル名', ["パラメータ" => 値]);
```

テンプレートで使える値は、[こちらのコード](./fw_template_engine.lib.php) を確認してください。

## サンプルと具体例

- https://github.com/kujirahand/konawiki3/
- https://github.com/kujirahand/nako3storage/






