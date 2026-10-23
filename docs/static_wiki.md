# 静的Wiki出力 仕様

- 関連Issue: [#267 静的サイト出力を主力にするように修正する](https://github.com/kujirahand/konawiki3/issues/267)
- 状態: 仕様策定中（Draft）

## 1. 目的と基本方針

- 閲覧するだけなら PHP を不要にする。Wiki の全公開ページを静的 HTML として `dist/` に書き出し、任意の静的ホスティング（Apache / nginx / GitHub Pages / Netlify / S3 など）で配信できるようにする。
- **編集・管理は従来どおり PHP 版で行い、閲覧は静的版を主とする**。PHP 版の閲覧機能（`show` アクション）は互換のため残す。
- 静的出力は既存の描画処理（パーサー・テンプレート・スキン・プラグイン）を再利用して生成し、PHP 版と見た目を揃える。描画ロジックを二重に持たない。
- ビルドは「未ログイン（匿名）ユーザーが見える内容」で行う。ログイン時だけ見える情報は一切出力しない。

## 2. 用語

| 用語 | 意味 |
| --- | --- |
| Wiki名 | ページの論理名。例: `使い方/基本操作`。`/` は階層区切り |
| 階層名 | Wiki名を `/` で分割した各要素。例: `使い方`、`基本操作` |
| 物理名 | 階層名をファイル名用にエンコードしたもの |
| 出力パス | `dist/` 配下の実ファイルのパス |
| 公開URL | 静的サイト上でページを指すURL |
| ベースURL | 静的サイトの公開ルート。設定 `static_base_url` |

## 3. 出力ディレクトリ構成

出力先は既定で `dist/`（設定 `static_outdir` で変更可）。Wikiの階層を維持し、各ページを `index.html` として出力する。

```text
dist/
├── index.html                 # FrontPage
├── 404.html                   # 存在しないページ用
├── sitemap.xml                # 公開ページ一覧（static_base_url が絶対URLの場合のみ）
├── はじめに/index.html
├── 使い方/
│   ├── index.html             # 「使い方」ページ（無ければディレクトリ一覧ページ）
│   └── 基本操作/index.html
└── _kona3/                    # システム予約領域（ページと衝突しない。5.3 参照）
    ├── skin/<skin名>/...      # スキンのCSS/JS/画像
    ├── resource/...           # kona3engine/resource のCSS/JS等
    └── files/...              # data/ 内の添付ファイル（画像・PDF等）
```

### Wiki名と出力パス・公開URLの対応

| Wiki名 | 出力ファイル | 公開URL（ベースURL `/` の場合） |
| --- | --- | --- |
| `FrontPage`（設定上のトップページ） | `dist/index.html` | `/` |
| `はじめに` | `dist/はじめに/index.html` | `/%E3%81%AF%E3%81%98%E3%82%81%E3%81%AB/` |
| `使い方/基本操作` | `dist/使い方/基本操作/index.html` | `/使い方/基本操作/`（実際は各階層を `rawurlencode`） |
| `開発/API仕様` | `dist/開発/API仕様/index.html` | `/開発/API仕様/`（同上） |

- FrontPage は `dist/index.html` のみに出力し、`dist/FrontPage/index.html` は出力しない。FrontPage へのリンクはすべてベースURLになる。
- 下位ページを持つページ（例: `使い方` と `使い方/基本操作`）は、`使い方/index.html` と `使い方/基本操作/index.html` として自然に共存する。

## 4. 物理名のエンコード規則

### 4.1 前処理

1. Wiki名を UTF-8 として検証する。不正なバイト列はエラー。
2. Unicode 正規化 NFC を適用する（`intl` 拡張の `Normalizer` が無い環境では正規化せず、衝突検出で補う）。macOS で作成された NFD のファイル名対策。
3. `/` で階層名に分割する。

### 4.2 階層名ごとの変換

次の文字を **`_HH`**（UTF-8 の各バイトを大文字16進2桁）に置き換える。それ以外（日本語を含む非ASCII文字、英数字、その他の記号）はそのまま使う。

| 対象 | 理由 |
| --- | --- |
| `_` | エスケープ文字自体。曖昧さ防止（`foo_bar` → `foo_5Fbar`） |
| `%` | URLデコードとの衝突防止 |
| `< > : " \ \| ? *` | Windows でファイル名に使えない |
| 制御文字 `0x00`–`0x1F`、`0x7F` | ファイル名・URLに不適 |
| 先頭の `.` | 隠しファイル化（`.htaccess` 等）や `.`/`..` との混同を防ぐ |
| 末尾の空白・`.` | Windows で除去される |
| 先頭の空白 | 視認性・誤操作防止 |

- Windows 予約名（`CON` `PRN` `AUX` `NUL` `COM1`–`COM9` `LPT1`–`LPT9`。大文字小文字を問わず、`CON.txt` のように拡張子付きも含む）に該当する場合は、**先頭1文字を `_HH` にエンコード**する（例: `CON` → `_43ON`）。
- 変換は単射（異なる階層名は必ず異なる物理名になる）であり、`_HH` を復号すれば元の階層名に戻せる。

### 4.3 拒否条件（ビルドエラー）

- 空の階層（`a//b`、先頭・末尾の `/`）
- 階層名が `.` または `..`
- 不正な UTF-8
- 物理名が 1 階層あたり **200 バイト**を超える
- 出力パス全体（`dist/` からの相対）が **1000 バイト**を超える

長すぎる場合に切り詰めやハッシュ化はしない（衝突や不可逆化を避けるため）。該当ページ名をエラーとして報告する。

### 4.4 変換例

| Wiki名 | 出力ファイル |
| --- | --- |
| `質問?回答` | `dist/質問_3F回答/index.html` |
| `A:B` | `dist/A_3AB/index.html` |
| `100%完成` | `dist/100_25完成/index.html` |
| `foo_bar` | `dist/foo_5Fbar/index.html` |
| `Windows/設定` | `dist/Windows/設定/index.html` |
| `.htaccess` | `dist/_2Ehtaccess/index.html` |
| `con` | `dist/_63on/index.html` |
| `末尾.` | `dist/末尾_2E/index.html` |

### 4.5 システム予約領域

`_` は常に `_HH` にエンコードされるため、**物理名が `_` の直後に16進2桁以外で始まるもの**はページから生成されることがない。この性質を利用し、`dist/_kona3/` をシステム予約領域（アセット・添付ファイル）とする。`404.html` や `sitemap.xml` などルート直下の単独ファイルは、ページが必ずディレクトリ（`<名前>/index.html`）になるため衝突しない。

## 5. 公開URL

- 公開URLは「ベースURL ＋ 物理パスの各階層を `rawurlencode()` して `/` で連結 ＋ 末尾 `/`」とする。
  - 例: `使い方/基本操作` → `{base}%E4%BD%BF%E3%81%84%E6%96%B9/%E5%9F%BA%E6%9C%AC%E6%93%8D%E4%BD%9C/`
  - 例: `foo_bar` → `{base}foo_5Fbar/`
- ベースURL（`static_base_url`）
  - 既定値は `/`。サブディレクトリ公開時は `/wiki/` や `https://example.com/wiki/` を指定する。
  - 末尾の `/` は自動補完する。
- ページ内アンカー（見出しリンク `#h...`）は公開URLの後ろに付ける。
- 末尾 `/` 形式のURLはWebサーバーのディレクトリインデックス（`index.html`）機能を前提とする。`file://` での直接閲覧は対象外。

## 6. 衝突検出

異なるWiki名が同じ出力先になる場合は、**上書きせずビルドエラー**とし、衝突したWiki名の組をすべて報告する。

- 比較キー: NFC正規化 → 物理パスを小文字化（`mb_strtolower`）したもの。
  - 大文字小文字を区別しないファイルシステム（Windows / macOS 既定）でも安全にする。
- 例: `About` と `about`、NFC と NFD で表記が異なる同名ページ。

## 7. ページ種別ごとの出力

| 種別 | 判定 | 出力 |
| --- | --- | --- |
| KonaNotation ページ | `.txt`（メタ `mode` 優先） | 通常ページとして HTML 化 |
| Markdown ページ | `.md`（メタ `mode` 優先） | 通常ページとして HTML 化 |
| ディレクトリのみ | ページファイルが無く、ディレクトリがある | `#ls` 相当の一覧ページを生成 |
| エイリアス | 本文に `!!alias(...)`、または `data/alias-show.json` に定義 | リダイレクト用 HTML（`<meta http-equiv="refresh">` ＋ `<link rel="canonical">` ＋ リンク）。循環・段数超過は警告し出力しない |
| 添付ファイル | 画像・PDF・Office 等（`allow_upload_ext` と `image_pattern` の拡張子） | `dist/_kona3/files/` 以下に階層を維持し、4章の規則でエンコードしてコピー。ページとしては出力しない |
| その他 | 上記以外 | 出力しない |

- ページの選択（`.txt`/`.md` 両方ある場合の優先度など）は PHP 版の `kona3show_detect_file()` と同じ規則に従う。
- `enc_pagename` が有効な環境では、`data/` 内のファイル名を復号してからWiki名として扱う。

## 8. リンクとURLの変換

静的出力中は `static_mode` フラグを立て、URLを生成する既存関数の結果を切り替える。

| 関数 / 箇所 | PHP版 | 静的版 |
| --- | --- | --- |
| `kona3getPageURL($page)`（`show`） | `index.php?Page` | `kona3getStaticURL($page)` |
| `kona3getPageURL($page, 'show')` 以外のアクション | `index.php?Page&edit` 等 | 出力しない（8.1） |
| `kona3getSkinURL()` | `index.php?file&skin` | `{base}_kona3/skin/<skin>/file` |
| `kona3getResourceURL()` | `index.php?file&resource` | `{base}_kona3/resource/file` |
| `ref` 等の添付リンク（`&data`） | `index.php?path&data` | `{base}_kona3/files/<encoded>` |
| `[[Wiki名]]`、`[[表示:Wiki名]]`、Markdownリンク | `index.php?Wiki名` | `kona3getStaticURL(Wiki名)` |
| パンくず（`kona3getPageTitleLink`） | 同上 | 同上 |

### 8.1 動的URLの扱い

- 静的版で、`show` 以外のアクション（`edit` `login` `search` `plugin` など）へのリンクは原則として生成しない。テンプレート側で `static_mode` のときは非表示にする。
- PHP版への導線（例: 「編集する」リンク）を残したい場合のために、設定 `static_php_url`（PHP版のURL）を用意する。指定時のみ、フッターに PHP 版へのリンクを出す。
- 存在しないページへのリンクは、`<span class="kona3_nolink">表示名</span>` として出力し、ビルド警告に記録する。

## 9. テンプレートの扱い

- `show.html` を流用し、`static_mode` のときは以下を出力しない。
  - 編集ボタン、ログインバー、文字数カウンタ
  - 編集トークンや CSRF トークンを含むフォーム
- `MenuBar`、`allpage_header`、`allpage_footer` はビルド時に展開して埋め込む。
- `sw.js`（Service Worker）は静的版では登録しない。
- 静的版専用の差分が大きくなった場合は `static_show.html` を別途設ける。

## 10. プラグインの扱い

プラグインは次の3分類で扱う。分類は各プラグインの定義に持たせず、既定の一覧（`kona3static.inc.php` 内）と設定 `static_plugin_disallow` で管理する。

| 分類 | 静的版での動作 | 対象（初期案） |
| --- | --- | --- |
| A. そのまま利用 | ビルド時に実行した結果をそのまま出力 | `code` `filecode` `csv` `tsv` `column` `footnote` `chapter` `include` `ls` `image` `ref` `mathjax` `tex` `mermaid`（描画のみ） `markdown` `md` `html` `br` `clear` `rem` `summary` `qrcode` `color_talk` など |
| B. ビルド時のスナップショット | ビルド時点の内容で固定して出力（更新はリビルド時） | `recent` `recent_meta` `tag` `tags` `taglist` `filelist` `countpages` |
| C. 静的版では無効 | 何も出力しない（または「この機能は静的版では利用できません」と表示） | `comment` `counter` `popular` `iflogin`（未ログイン側の内容のみ出力） `nako3`（実行系） `sakuramml_sf` `htmlshow`、その他 POST や Ajax を伴うもの |

- B 分類のプラグインを含むページは「揮発ページ」としてマニフェストに記録し、差分ビルドでも毎回再生成する（12.2 参照）。
- `tags` / `taglist` が生成するタグ一覧ページへのリンクは、静的版では `dist/_kona3/tags/` 以下に一覧ページを生成するか、リンクを外す（フェーズ3で決定）。
- ビルド中は `kona3isLogin()` が必ず FALSE を返すようにする。

## 11. 公開対象と除外

### 出力しないもの

- `wiki_private` が有効な Wiki 全体（ビルドを拒否する。強制する場合は CLI オプション `--force-private` を明示）
- `data/` 内の `.` で始まるファイル・ディレクトリ（`.meta/`、`.git/` など）
- システムファイル: `alias-show.json` などの設定用 JSON
- `private/`、`cache/`、`kona3engine/` の内容（アセットとして明示的にコピーするもの以外）
- 7章で「出力しない」とされた種別、10章 C 分類のプラグインの出力
- シンボリックリンク（たどらない）

### 個別ページの除外

- メタデータ（`.meta/ページ名.json`）に `"static": false` を指定したページは出力しない。
- 設定 `static_exclude`（Wiki名のプレフィックス一覧）に該当するページは出力しない。例: `["下書き/", "MenuBar"]`。
  - `MenuBar` は既定で除外する（各ページに埋め込まれるため単独ページは不要）。

除外ページへのリンクは「存在しないページへのリンク」と同じ扱いにする（8.1）。

## 12. ビルド

### 12.1 全体ビルド

1. 出力先の安全確認（14章）。
2. `dist.tmp/`（出力先と同じ親ディレクトリ内の一時ディレクトリ）を作る。
3. 公開対象ページを列挙し、物理パスを計算し、衝突を検出する（エラーがあればここで中止）。
4. 各ページを描画して書き出す。
5. アセット（スキン、resource、添付ファイル）をコピーする。
6. `404.html`、`sitemap.xml` を生成する。
7. 既存の `dist/` を `dist.old/` に、`dist.tmp/` を `dist/` にリネームし、`dist.old/` を削除する（削除済みページの残骸が残らず、公開中のファイルが中途半端な状態にならない）。

エラーが1件でもあれば `dist/` は更新しない。警告（存在しないページへのリンクなど）はビルドを止めない。

### 12.2 差分ビルド

- マニフェストを `cache/static_manifest.json` に保存する（`dist/` には置かない）。
  - 内容: Wiki名 → { 出力パス, 元ファイルの mtime とサイズ, 揮発フラグ }、およびビルド時の設定・スキン・エンジンバージョンのハッシュ。
- 次の場合は全体ビルドに切り替える。
  - 設定、スキン、エンジンバージョン、`MenuBar`、`allpage_header`/`allpage_footer` のいずれかが変化した
  - ページの追加・削除・リネームがあった（`ls` 等の一覧や存在しないページへのリンク表示が変わるため）
- それ以外は「内容が変わったページ」＋「揮発ページ」のみ再生成する。差分ビルドは `dist/` を直接更新する。

### 12.3 起動方法

| 方法 | 内容 |
| --- | --- |
| CLI | `php script/build_static.php [--full] [--outdir=dist] [--base-url=/]`。`just build` からも実行可能 |
| 管理画面 | 管理者のみ実行可能なアクション `static`。全体ビルド・差分ビルドのボタンと、直近の結果（エラー・警告一覧）を表示 |
| 自動ビルド | 設定 `static_auto_build` が有効なとき、ページ保存後に差分ビルドを実行する |

### 12.4 グローバル状態の扱い

既存の描画処理は `$kona3conf`（`css`、`js`、`header.tags`、`page` など）やプラグイン内の `static` 変数にページ単位の状態を積み上げる。複数ページを1プロセスで描画するため、次の対策をとる。

- ページごとに `$kona3conf` を開始時のスナップショットに戻してから描画する。
- `exit` / `header()` を呼ぶ処理（エイリアスのリダイレクト、`kona3error()` など）は、静的モードでは例外を投げて呼び出し元で処理する形に改める。
- 描画結果は出力バッファで受け取り、文字列として返す関数 `kona3show_render($page)` を `show` アクションから切り出す。`show` アクション自体もこの関数を使う。

## 13. 設定項目

| キー | 既定値 | 説明 |
| --- | --- | --- |
| `static_outdir` | `dist` | 出力先（インデックスディレクトリからの相対、または絶対パス） |
| `static_base_url` | `/` | 公開URLのベース |
| `static_auto_build` | `false` | ページ保存時に差分ビルドする |
| `static_exclude` | `["MenuBar"]` | 出力しないWiki名のプレフィックス |
| `static_plugin_disallow` | `[]` | 既定の C 分類に追加で無効化するプラグイン |
| `static_php_url` | `""` | PHP版のURL。指定時のみ静的版に PHP 版へのリンクを出す |

`dist/` はリポジトリの `.gitignore` に追加する。

## 14. セキュリティ

- 出力先の安全確認: `static_outdir` の実パスが、`data/`、`private/`、`kona3engine/`、インデックスディレクトリそのもの、またはそれらの親ディレクトリであれば拒否する（全削除・入れ替えを行うため）。
- すべての書き込み先について、実パスが出力先の配下にあることを確認する（パストラバーサル対策）。
- `data/` 内のシンボリックリンクはたどらない。
- 匿名ユーザー視点で描画し、ログイン情報・トークン・メールアドレス・設定値を出力しない。
- 添付ファイルは拡張子の許可リストに一致するものだけコピーする（`.php` 等は決してコピーしない）。
- 管理画面からのビルドは管理者のみ、CSRF トークン必須。

## 15. 実装構成

### 新規

- `kona3engine/kona3static.inc.php`
  - `kona3static_encodeName($name)` / `kona3static_decodeName($encoded)`: 4章の階層名エンコード
  - `kona3getStaticFile($wikiName)`: 出力パス（`dist/` からの相対）を返す。拒否条件ではエラー
  - `kona3getStaticURL($wikiName)`: 公開URLを返す
  - `kona3static_collisionKey($path)`: 衝突検出キー
  - `kona3static_build($options)`: 全体・差分ビルド
- `kona3engine/action/static.inc.php`: 管理画面用アクション
- `script/build_static.php`: CLI
- `kona3engine/tests/static_*.test.php`: テスト

### 既存の改修

- `kona3lib.inc.php`: `kona3getPageURL()`、`kona3getSkinURL()`、`kona3getResourceURL()`、`kona3getPageTitleLink()` の静的モード対応
- `action/show.inc.php`: `kona3show_render()` の切り出し、`exit` を伴う処理の例外化
- `plugins/ref.inc.php` ほか添付を扱うプラグイン: 静的URL対応
- `template/show.html`、`parts_header.html`、`parts_footer.html`: 静的モードでの表示切り替え
- `kona3conf.inc.php`: 13章の設定項目を追加
- `justfile`: `build` タスクを追加

## 16. テスト

| 観点 | 内容 |
| --- | --- |
| エンコード | 4.4 の変換例がすべて一致する。復号すると元に戻る |
| 予約名 | `CON` `con` `Com1` `NUL.txt` が回避される |
| 拒否 | 空の階層、`.`、`..`、不正な UTF-8、長すぎる名前がエラーになる |
| 衝突 | 大文字小文字違い、NFC/NFD 違いがエラーになる |
| URL | ベースURL `/`、`/wiki/`、`https://example.com/wiki/` で正しく生成される。FrontPage がベースURLになる |
| 出力 | 生成 HTML に `index.php?` が含まれない。編集ボタン・トークンが含まれない |
| リンク | `[[...]]` と Markdown リンク、パンくずが静的URLになる。存在しないページは `kona3_nolink` になる |
| 除外 | `.meta`、`wiki_private`、`"static": false`、`static_exclude` が出力されない |
| 安全性 | 出力先が `data/` などの場合に拒否される。出力先の外に書き込めない |
| 状態 | 2ページ連続で描画しても CSS/JS タグやページ名が混ざらない |
| 差分 | 変更ページと揮発ページだけが再生成される。ページ追加時は全体ビルドになる |

## 17. 実装フェーズ

1. **フェーズ1（基盤）**: 4〜6章のパス・URL規則と衝突検出、`kona3show_render()` の切り出し、URL生成関数の静的モード対応、1ページの出力、テスト
2. **フェーズ2（全体ビルド）**: 全ページ列挙、7章のページ種別、アセットと添付のコピー、除外、全体ビルド（原子的な入れ替え）、CLI、`404.html`
3. **フェーズ3（運用）**: 差分ビルド、管理画面、自動ビルド、プラグイン B 分類のスナップショット、`sitemap.xml`、README 更新
4. **フェーズ4（任意）**: クライアントサイド検索（ビルド時に検索用インデックス JSON を生成）、タグ一覧ページ

## 18. 未決事項

- 10章のプラグイン分類の最終確定（各プラグインの実装を確認して決める）
- タグ一覧ページを静的に生成するか（フェーズ3）
- `+` を含むページ名の扱い（S3 など一部のホスティングで `+` が空白に解釈される。必要なら 4.2 のエンコード対象に追加）
- `file://` で閲覧可能な相対URLモードを提供するか
- 1階層 200 バイト、全体 1000 バイトの長さ制限値の妥当性
