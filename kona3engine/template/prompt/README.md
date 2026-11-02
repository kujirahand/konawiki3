# AI prompt templates

KonaWiki3 の編集画面の AI 機能が使うプロンプトです。
ここのファイルはサーバー側 (`kona3edit_ai_run()`) だけが読み込み、画面やレスポンスには出しません。

## 配置

```text
prompt/{lang}/{action}.md
```

- `lang`: UI 言語コード (`ja`, `en`)。無い言語は `en` → `ja` の順でフォールバックします。
- `action`: `proofread` / `rephrase` / `append` / `quality` / `improve`
  (`kona3edit_ai_get_actions()` のキーと一致させること)

## 形式

ファイル全体がそのまま user メッセージになります (`### 指示:` / `### 入力:` 形式)。
system メッセージは設定 `openai_api_basic_instruction` を使います。

## 置換変数

| 変数 | 内容 |
|---|---|
| `__TEXT__` | 対象テキスト (proofread/quality/improve は本文全体、rephrase は選択範囲) |
| `__TEXT_SELECTED__` | 選択範囲 (ユーザーひな形向け) |
| `__TEXT_BEFORE__` | append 用。選択終端(カーソル)より前の末尾 4000 文字 |
| `__INSTRUCTION__` | rephrase 用。言い換えの指示 (空なら既定文言) |

テキスト系変数は ``` で囲まれて展開され、本文中の ``` はエスケープされます。
使われなかった変数は空文字になります。

## 出力契約

| action | AI に求める出力 |
|---|---|
| proofread | JSON `[{"ng","ok","desc"}]` (ng は本文中の実在文字列。なければ `[]`) |
| rephrase | JSON `[{"ok","desc"}]` (最大3件) |
| append | 続きの文章だけをテキストで |
| quality | JSON `{"score","summary","good":[],"bad":[]}` (score は 0〜100) |
| improve | JSON `[{"point","detail","ng","ok"}]` (ng/ok は任意) |
