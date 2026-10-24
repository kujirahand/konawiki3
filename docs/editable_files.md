# 編集できるファイル形式

Issue #53 の仕様です。編集画面で直接編集できるファイル形式を明確にします。

## 仕様

直接編集できるファイル形式は **`.txt` と `.md` だけ** です。
リストは `kona3getEditableExtList()` (`kona3engine/kona3lib.inc.php`) が唯一の定義で、
判定は `kona3isEditableExt($ext)`、末尾の拡張子を取り除く処理は
`kona3stripEditableExt($page)` を使います。

| ページ名 | 保存されるファイル | 備考 |
|---|---|---|
| `FrontPage` (拡張子なし) | `FrontPage.txt` / `FrontPage.md` | 既存ファイル → メタ情報の mode → 設定 `def_text_ext` の順で決まる |
| `memo.txt` | `memo.txt` | そのまま直接編集する |
| `memo.md` | `memo.md` | そのまま直接編集する |
| `xxxx.php` | `xxxx.php.txt` / `xxxx.php.md` | `.php` はページ名の一部。別途 `.txt` / `.md` を付与する |
| `test.html` | `test.html.txt` / `test.html.md` | `.html` もページ名の一部 (従来は直接編集できたが、本仕様で対象外) |
| `dir/xxxx.php` | `dir/xxxx.php.txt` / `dir/xxxx.php.md` | 階層付きでも同じ規則 |

付与する拡張子が `.txt` と `.md` のどちらになるかは、
`koan3getWikiFileText()` の既存の解決規則によります
(既存ファイルがあればそのファイル、なければメタ情報の mode、
それもなければ設定 `def_text_ext`、既定は `md`)。
編集画面で記法 (Markdown / KonaNotation) を切り替えた場合は、
新規ページに限り、従来どおり記法に応じた拡張子で保存されます。

## 従来の挙動との違い

- 従来は `html, htm, json, css, yaml, yml, xml, tsv` も直接編集でき、
  それ以外 (`php` など) は `Invalid file extension.` エラーで保存できませんでした。
  本仕様では、直接編集は `.txt` / `.md` のみに統一し、
  それ以外の拡張子はエラーにせず `.txt` / `.md` を付与して保存します。
  そのため、実行可能なファイル (`xxxx.php` など) が `data/` に
  そのままの名前で作られることもありません。
- 編集画面は、同名の生ファイル (拡張子なしや `.php` など) が `data/` に
  あっても読み込みません。読み込む対象は `.txt` / `.md` ファイルだけです。
- メタ情報ファイル (`data/.meta/`) の名前から取り除く拡張子も
  `.txt` / `.md` だけに統一しました。従来は任意の拡張子を取り除いていたため、
  `xxxx.php` と `xxxx` のメタファイルが衝突していました
  (`xxxx.php` のメタは `xxxx.php.json` になります)。
  Git 連携でコミット対象ファイルを決める処理も同じ規則に統一しました。

## 既存データへの影響

- `.txt` / `.md` で保存されている通常のページには影響ありません。
- 従来、直接編集で作られた `data/*.html` などのファイルは、
  編集画面では開けなくなります (表示も従来どおり対象外です)。
  必要な場合は、ファイル名を `*.html.txt` / `*.html.md` に変えるか、
  内容を同名ページ (`*.html`) の編集画面へ移してください。
- `xxxx.php` のようなページ名で、従来の規則で保存されていたメタ情報は
  読み込まれなくなります (上記の衝突解消のため)。

## テスト

`kona3engine/tests/editable_ext.test.php` で、リストの内容、
`kona3getEditFile()` の解決 (直接編集 / 付与 / 既存ファイル優先 / 階層付き)、
メタファイル名の衝突解消、および `kona3_trywrite()` による
`xxxx.php` ページの実際の保存・再編集を検証しています。
