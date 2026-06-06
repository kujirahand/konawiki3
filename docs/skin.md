# KonaWiki3 スキン作成ガイド

KonaWiki3 では、CSS を中心としたシンプルな仕組みで Wiki の見た目（スキン）をカスタマイズ・追加できます。
本ドキュメントでは、スキンの基本的な仕組みから、作成手順、ダークモード対応、そして清涼感のあるサンプルスキン `aqua` の実装例について解説します。

---

## 1. スキンシステムの概要

KonaWiki3 のデザインは、基本となるスタイルシートが読み込まれた後、選択されたスキンのスタイルシート（`kona3.css`）が読み込まれ、上書き（オーバーライド）されることで決定します。

### スキンのディレクトリ構造

スキンは、プロジェクトの `skin` ディレクトリ以下に任意のフォルダ名で作成します。

```text
konawiki3/
├── skin/
│   ├── def/            # デフォルトスキン
│   ├── single/         # シングルカラムスキン
│   ├── nako3/          # なでしこ3風スキン
│   └── aqua/           # 新規作成するスキン（例）
│       └── kona3.css   # スキンのメインスタイルシート
```

### アセットの優先順位

CSS や JavaScript などのリソースが読み込まれる際、システムは以下の優先順位でファイルを探索します。

1. `skin/{現在設定されているスキン名}/{ファイル名}`
2. `skin/def/{ファイル名}`（フォールバック）
3. `kona3engine/resource/{ファイル名}`（コアのデフォルトリソース）

そのため、新しくスキンを作成する際は、デフォルトの CSS をすべてコピーする必要はなく、**変更したい部分のみを `kona3.css` に記述する**だけで動作します。

---

## 2. スキンの作成手順

新しいスキンを作成する基本的な手順は以下の通りです。

### ステップ 1: スキンフォルダの作成
`skin/` ディレクトリ内に、作成したいスキン名（半角英数字、ハイフン、アンダースコアが使用可能）のフォルダを作成します。ここでは例として `aqua` とします。

### ステップ 2: `kona3.css` の作成
作成したフォルダ内に `kona3.css` ファイルを作成し、カスタムスタイルを記述します。

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

「水中をイメージした清涼感のある爽やかな画面デザイン」をコンセプトにしたスキン `aqua` の実装例です。

### デザインのポイント
* **ライトテーマ (水面・浅瀬イメージ)**: 
  * 全体の背景に淡い水色のグラデーションを使用し、水の中にいるような浮遊感を与えています。
  * ヘッダーには美しい水色〜エメラルドグリーンのグラデーションを適用し、清涼感を強調しています。
  * リンクや見出しに透明感のあるブルーやシアンを効果的に配置し、メリハリをつけています。
* **ダークテーマ (深海イメージ)**:
  * ダークモード時は、深海を思わせる濃いネイビー（`#030a16`）を背景とし、発光する水色（`#38bdf8`）をアクセントカラーとして使用しています。

### `skin/aqua/kona3.css`

```css
/**
 * KonaWiki3 Skin: aqua
 * 水中をイメージした清涼感のある爽やかな画面デザイン
 */

/* ==========================================
   Light Theme (デフォルト / 水中・水面イメージ)
   ========================================== */

/* 全体設定 */
body {
  background-color: #f7fcfe;
  color: #2c3e50;
  background-image: radial-gradient(circle at 50% -20%, #e0f4ff 0%, transparent 50%);
  background-attachment: fixed;
}

/* ヘッダー */
#kona3_layout_header {
  background: linear-gradient(135deg, #0077cc 0%, #00bcd4 100%);
  border-bottom: 2px solid #0097a7;
  box-shadow: 0 2px 8px rgba(0, 188, 212, 0.15);
}

#kona3_header_title a {
  color: #ffffff;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  font-weight: bold;
}

#kona3_header_title .pagename {
  color: rgba(255, 255, 255, 0.9);
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* リンク */
#kona3_main a:link,
#kona3_main a:active,
#kona3_menubar a:link,
#kona3_menubar a:active {
  color: #0077cc;
  text-decoration: none;
  transition: all 0.25s ease;
}

#kona3_main a:visited,
#kona3_menubar a:visited {
  color: #005599;
}

#kona3_main a:hover {
  color: #0099b8;
  background-color: #e6f9ff;
  border-radius: 3px;
  padding: 1px 4px;
  margin: -1px -4px;
}

#kona3_menubar a:hover {
  color: #0099b8;
  background-color: #e6f9ff;
}

/* 見出し */
#kona3_main h1 {
  background: linear-gradient(to right, #e0f7fa 0%, #e0f2fe 100%);
  border-bottom: 2px solid #00acc1;
  border-left: 6px solid #00838f;
  color: #006064;
  padding: 12px 16px;
  border-radius: 0 8px 8px 0;
  box-shadow: 0 2px 5px rgba(0,0,0,0.02);
}

#kona3_main h2 {
  border: none;
  border-left: 5px solid #00bcd4;
  background: linear-gradient(to right, #f1fbfd 0%, transparent 100%);
  color: #00838f;
  font-size: 1.3em;
  font-weight: 700;
  margin: 1.8em 0 1em;
  padding: 0.6em 0.8em;
  border-radius: 0 4px 4px 0;
}

#kona3_main h3 {
  border: none;
  border-bottom: 2px dashed #00e5ff;
  color: #0097a7;
  font-size: 1.15em;
  font-weight: 700;
  margin: 1.6em 0 0.8em;
  padding: 0.4em 0 0.4em 4px;
}

#kona3_main h4,
#kona3_main h5,
#kona3_main h6 {
  border-bottom: 1px solid #e0f7fa;
  color: #00acc1;
}

/* メニューバー */
#kona3_menubar {
  border-left-color: #b2ebf2;
  border-top-color: #b2ebf2;
}

#kona3_menubar h1,
#kona3_menubar h2,
#kona3_menubar h3 {
  color: #00838f;
  border-bottom: 1px solid #b2ebf2;
  font-weight: bold;
}

/* コードブロックなど */
pre {
  background-color: #f0f9ff;
  border: 1px solid #bae7ff;
  border-radius: 6px;
}

/* 強調表示 */
strong.strong1 {
  background: linear-gradient(transparent 60%, rgba(0, 229, 255, 0.2) 60%);
}
strong.strong2 {
  background: linear-gradient(transparent 60%, rgba(0, 188, 212, 0.3) 60%);
}


/* ==========================================
   Dark Theme (深海イメージ)
   ========================================== */

body.dark-theme {
  background-color: #030a16;
  color: #d0e8ff;
  background-image: radial-gradient(circle at 50% -20%, #082545 0%, transparent 60%);
}

body.dark-theme #kona3_layout_header {
  background: linear-gradient(135deg, #011627 0%, #082545 100%);
  border-bottom: 2px solid #0b3c5d;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4);
}

body.dark-theme #kona3_header_title a {
  color: #38bdf8;
  text-shadow: 0 0 10px rgba(56, 189, 248, 0.3);
}

body.dark-theme #kona3_header_title .pagename {
  color: #93c5fd;
}

/* ダークテーマのリンク */
body.dark-theme #kona3_main a:link,
body.dark-theme #kona3_main a:active,
body.dark-theme #kona3_menubar a:link,
body.dark-theme #kona3_menubar a:active {
  color: #38bdf8;
}

body.dark-theme #kona3_main a:visited,
body.dark-theme #kona3_menubar a:visited {
  color: #0284c7;
}

body.dark-theme #kona3_main a:hover {
  color: #7dd3fc;
  background-color: #0c2340;
}

body.dark-theme #kona3_menubar a:hover {
  color: #7dd3fc;
  background-color: #0c2340;
}

/* ダークテーマの見出し */
body.dark-theme #kona3_main h1 {
  background: linear-gradient(to right, #082545 0%, #031022 100%);
  border-bottom: 2px solid #00e5ff;
  border-left: 6px solid #38bdf8;
  color: #e0f2fe;
}

body.dark-theme #kona3_main h2 {
  border-left: 5px solid #00bcd4;
  background: linear-gradient(to right, #041d37 0%, transparent 100%);
  color: #38bdf8;
}

body.dark-theme #kona3_main h3 {
  border-bottom: 2px dashed #0c4a6e;
  color: #0ea5e9;
}

body.dark-theme #kona3_main h4,
body.dark-theme #kona3_main h5,
body.dark-theme #kona3_main h6 {
  border-bottom: 1px solid #0c4a6e;
  color: #0ea5e9;
}

/* ダークテーマのメニューバー */
body.dark-theme #kona3_menubar {
  border-left-color: #0c4a6e;
  border-top-color: #0c4a6e;
}

body.dark-theme #kona3_menubar h1,
body.dark-theme #kona3_menubar h2,
body.dark-theme #kona3_menubar h3 {
  color: #38bdf8;
  border-bottom: 1px solid #0c4a6e;
}

/* ダークテーマのコードブロックなど */
body.dark-theme pre {
  background-color: #031525;
  border: 1px solid #0c3e60;
}

body.dark-theme code {
  color: #60a5fa;
}

/* ダークテーマの強調表示 */
body.dark-theme strong.strong1 {
  background: linear-gradient(transparent 60%, rgba(56, 189, 248, 0.25) 60%);
}
body.dark-theme strong.strong2 {
  background: linear-gradient(transparent 60%, rgba(0, 229, 255, 0.35) 60%);
}
```

---

## 4. ダークモードへの対応

KonaWiki3 は標準でダークモード切替機能を備えています。
ブラウザや OS のダークモード設定、またはメニューから「Dark Mode」を選択した際、`<body>` タグに `dark-theme` クラスが追加されます。

```html
<body class="dark-theme">
```

スキン側でダークモードに対応するには、CSS 内で以下のように `.dark-theme` クラスがある場合のスタイル定義を記述します。

```css
/* 例: ダークモード時に背景色と文字色を切り替える */
body.dark-theme {
  background-color: #030a16;
  color: #d0e8ff;
}
```

このアプローチにより、一つの `kona3.css` ファイル内でライトモードとダークモードの双方に美しく対応したスキンを作成できます。
