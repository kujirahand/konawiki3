# KonaWiki3 スキン作成ガイド

KonaWiki3 では、CSS を中心としたシンプルな仕組みで Wiki の見た目（スキン）をカスタマイズ・追加できます。
本ドキュメントでは、スキンの基本的な仕組みから、作成手順、ダークモード対応、そしてサンプルスキン `aqua` の実装例について解説します。

---

## 1. スキンシステムの概要

KonaWiki3 のスキンは、デフォルトスキン `def` を土台にして作ります。
`def` のスタイルシートが先に読み込まれ、その後に選択されたスキンのスタイルシート（`kona3.css`）が読み込まれて、上書き（オーバーライド）されることで見た目が決まります。

### CSS の読み込み順

```text
1. kona3engine/resource/pure-min.css
2. kona3engine/resource/grids-responsive-min.css
3. kona3engine/resource/kona3def.css   # 全スキン共通の基本スタイル
4. kona3engine/resource/darkmode.css   # 全スキン共通のダークモード
5. skin/def/kona3.css                  # デフォルトスキン(全スキンの土台)
6. skin/{スキン名}/kona3.css           # スキン固有のスタイル(def 以外のとき)
7. drawer.css                          # ドロワーメニュー
```

- `def` 以外のスキンでも、5 の `skin/def/kona3.css` が必ず先に読み込まれます。余白・表・コード・印刷・ダークモードの仕組みは `def` が受け持つので、各スキンは **配色の変数と、そのスキン固有の装飾だけ** を書けば済みます。
- `kona3def.css` と `darkmode.css` も全スキンの土台です。特定のスキンの見た目を変えるために、これらを編集しないでください。
- `def` のデザイン方針と変数の一覧は [default_skin.md](default_skin.md) を参照してください。

### スキンのディレクトリ構造

スキンは、プロジェクトの `skin` ディレクトリ以下に任意のフォルダ名で作成します。

```text
konawiki3/
├── skin/
│   ├── def/            # デフォルトスキン(全スキンの土台)
│   ├── single/         # シングルカラムスキン
│   ├── nako3/          # なでしこ3風スキン
│   └── aqua/           # 水中イメージのスキン
│       └── kona3.css   # スキンのメインスタイルシート
```

### アセットの優先順位

`kona3.css` 以外の CSS や JavaScript などのリソースは、以下の優先順位でファイルを探索します。

1. `skin/{現在設定されているスキン名}/{ファイル名}`
2. `kona3engine/resource/{ファイル名}`（コアのデフォルトリソース）

静的出力(`just build`)では、設定中のスキンに加えて `skin/def/` も出力されます。

---

## 2. スキンの作成手順

新しいスキンを作成する基本的な手順は以下の通りです。

### ステップ 1: スキンフォルダの作成
`skin/` ディレクトリ内に、作成したいスキン名（半角英数字、ハイフン、アンダースコアが使用可能）のフォルダを作成します。ここでは例として `aqua` とします。

### ステップ 2: `kona3.css` の作成
作成したフォルダ内に `kona3.css` ファイルを作成し、カスタムスタイルを記述します。
まずは `def` の変数(`--k3-` で始まる CSS 変数)を上書きするところから始めるのがおすすめです。

```css
:root {
  --k3-color-heading: #006064;
  --k3-color-accent: #0077cc;
  --k3-color-accent-hover: #00838f;
}
```

これだけで、見出し・リンク・ボタン・表・コードなどの配色がまとめて切り替わります。

### ステップ 3: スキンの適用
スキンを有効化するには、以下のいずれかの方法を行います。

* **管理画面から設定する**:
  1. 管理者アカウントでログインします。
  2. 設定編集画面（`index.php?go&editConf`）を開きます。
  3. 「Skin name」のドロップダウンメニューから作成したスキン（例: `aqua`）を選択し、保存します。
* **設定ファイルを直接編集する**:
  `private/kona3conf.json.php` を開き、`"skin"` の値を変更します。
  ```json
  "skin": "aqua"
  ```

---

## 3. サンプルスキン `aqua` の実装例

「水中をイメージした清涼感のある爽やかな画面デザイン」をコンセプトにしたスキン `aqua` の実装例です。全体は [`skin/aqua/kona3.css`](../skin/aqua/kona3.css) を参照してください。

### デザインのポイント
* **ライトテーマ (水面・浅瀬イメージ)**:
  * 全体の背景に淡い水色のグラデーションを使用し、水の中にいるような浮遊感を与えています。
  * ヘッダーには水色〜エメラルドグリーンのグラデーションを適用し、清涼感を強調しています。
  * 見出しに透明感のあるブルーやシアンの帯と線を使い、メリハリをつけています。
* **ダークテーマ (深海イメージ)**:
  * 深海を思わせる濃いネイビー（`#030a16`）を背景とし、発光する水色（`#38bdf8`）をアクセントカラーとして使用しています。

### 書き方の要点

**1. `def` の変数を上書きする**

```css
:root {
  --k3-color-text: #2c3e50;
  --k3-color-heading: #006064;
  --k3-color-bg: #f7fcfe;
  --k3-color-accent: #0077cc;
  /* ... */
}
```

**2. スキン固有の装飾も変数にする**

グラデーションなど、スキン固有の値もスキン名で始まる変数(`--aqua-`)にしておくと、ダークテーマでは変数の値を差し替えるだけで済みます。

```css
:root {
  --aqua-header-bg: linear-gradient(135deg, #0077cc 0%, #00bcd4 100%);
  --aqua-h1-bg: linear-gradient(to right, #e0f7fa 0%, #e0f2fe 100%);
}

#kona3_layout_header {
  background: var(--aqua-header-bg);
}
#kona3_main h1 {
  background: var(--aqua-h1-bg);
  border-left: 6px solid var(--aqua-h1-bar);
}
```

**3. ダークテーマの値は `@media screen` の中で差し替える**

```css
@media screen {
body.dark-theme {
  --k3-color-bg: #030a16;
  --k3-color-accent: #38bdf8;
  --aqua-header-bg: linear-gradient(135deg, #011627 0%, #082545 100%);
  /* ... */
}
}
```

`@media screen` に入れておくと、ダークモードのままブラウザから印刷しても、ライトの配色で印刷されます。

---

## 4. ダークモードへの対応

KonaWiki3 は標準でダークモード切替機能を備えています。
メニューから「Dark Mode」を選択すると、`<body>` タグに `dark-theme` クラスが追加されます。選択内容はブラウザの `localStorage`（`kona3_theme`）に保存され、次回以降も適用されます。ブラウザや OS のダークモード設定には連動しません。また、印刷モードではダークモードは適用されません。

```html
<body class="dark-theme">
```

### 変数を差し替えるだけで済む部分

`def` は、ダークモードで使う色をすべて変数で指定しています。そのため、上の「3. ダークテーマの値は `@media screen` の中で差し替える」のように、スキン側で `body.dark-theme` の変数を差し替えるだけで、本文・表・コード・メニュー・フッターなどがダークの配色に切り替わります。

### `!important` が必要な部分

`darkmode.css` には `body.dark-theme #kona3_main h2` のような詳細度の高いルールが含まれており、`def` もそれを `!important` 付きで上書きしています。そのため、スキン側で見出しの装飾(背景や罫線)などを変えた場合は、ダークモード用にも `body.dark-theme` から始まるセレクタと `!important` で指定する必要があります(`!important` が付いていないプロパティには不要です)。

```css
/* ライト */
#kona3_main h1 {
  background: var(--aqua-h1-bg);
}
/* ダーク: def の body.dark-theme #kona3_main h1 { background: none !important } を上書きする */
body.dark-theme #kona3_main h1 {
  background: var(--aqua-h1-bg) !important;
}
```

値を変数にしておけば、ライト用とダーク用で同じ変数を参照するだけなので、配色を変えるときに直す場所は変数の定義だけで済みます。

この `!important` の上書きは、`darkmode.css` を変数化すれば不要にできる見込みです(#294)。
