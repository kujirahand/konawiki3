<?php

/** ページを凍結する (先頭行に書くと管理者だけが編集できる)
 * - [書式] #freeze
 * - ページ本文の先頭行に書いておくと、そのページは凍結され、
 *   書き換え (保存・削除) の際に管理者であることが必要になる。
 * - Markdown では行頭の「#freeze」または「!!freeze」でもよい。
 * - 凍結できる (および解除できる) のは管理者だけ。
 * - 詳しくは docs/freeze.md を参照。
 */

function kona3plugins_freeze_execute($args) {
  // 凍結は編集時のチェック (kona3pageIsFrozen) で効くもので、
  // 表示上は何も出力しない (HTMLコメントだけを残す)
  return "<!-- #freeze -->";
}
