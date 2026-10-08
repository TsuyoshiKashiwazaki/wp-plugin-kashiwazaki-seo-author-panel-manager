=== Kashiwazaki SEO Author Panel Manager ===
Contributors: kashiwazakitsuyoshi
Tags: author, schema, json-ld, structured-data, seo
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Person、Corporation、Organizationの3種類のエンティティを独立管理し、著者パネルとSchema.org JSON-LD構造化データを出力するプラグインです。

== Description ==

Kashiwazaki SEO Author Panel Managerは、WordPressユーザーとは独立した著者データベースを管理し、Gutenbergブロックで著者パネルとSchema.org JSON-LD構造化データを出力するプラグインです。

主な機能:

* Person（人物）、Corporation（法人）、Organization（組織）の独立管理
* Gutenbergブロックエディタ対応（挿入・設定・プレビュー）
* Schema.org JSON-LD構造化データ出力（Person / Corporation / Organization）
* Standardモード（独立JSON-LD）、Customモード（既存スキーマへの紐付け）、出力しない（パネルのみ）
* エンティティごとの表示ラベル設定（執筆者・監修者・運営会社等）
* 画像のデザイン（丸 / 角丸の四角 / 四角 / 楕円 / 切り抜かない）を記事ごとに選択
* ドラッグまたは ↑↓ ボタンでパネルの並び順を変更
* パネルのデザイン（標準 / アクセント / ミニマル / カード）とカラー（おまかせ / グレー / 白 / ダーク / ブルー / グリーン / オレンジ / カスタム）を別々に設定
* Corporation / Organization の住所・連絡先・法人情報（住所と電話番号はパネルに表示、入力した項目を吹き出し（国コードは除く）と構造化データに出力）
* sameAs URLのソーシャルアイコン自動表示
* メディアライブラリからの画像選択

== Installation ==

1. `wp-plugin-kashiwazaki-seo-author-panel-manager` ディレクトリを `/wp-content/plugins/` にアップロードしてください。
2. WordPress管理画面の「プラグイン」メニューから有効化してください。
3. 管理メニューの「Kashiwazaki SEO Author Panel Manager」からエンティティを登録してください。

== Frequently Asked Questions ==

= 既存のSEOプラグインと併用できますか？ =

はい。Customモードを使うことで、Yoast SEOやRank Math等が出力するArticle/NewsArticleスキーマに、author/publisherプロパティを紐付けることができます。

= WordPressのユーザーデータとは別ですか？ =

はい。本プラグインは独自のデータベーステーブルでエンティティを管理するため、WordPressユーザーとは完全に独立しています。

== Changelog ==

= 1.0.4 =
* 機能追加: Corporation / Organization に住所・電話番号・メール・問い合わせ窓口・法人情報（正式名称・設立日・従業員数・taxID・vatID・ISO 6523 コード・DUNS・LEI・GLN・NAICS）の任意項目 20 個を追加
* 機能追加: 住所と電話番号をパネルに表示（電話番号は tel: リンク）。全項目を Schema.org JSON-LD（address / telephone / email / contactPoint / legalName / foundingDate / numberOfEmployees 等）に出力
* 機能追加: 入力した住所・連絡先・法人情報の一覧を、パネルの名前の横の i ボタンの吹き出しで表示（Esc キーで閉じる）
* 機能追加: 国コード・メールアドレス・設立日・従業員数の書式が合わないときは空欄で保存し、管理画面に警告を表示
* 変更: Corporation / Organization のテーブルに 20 列を追加（ファイル上書き更新時も自動で追加）

= 1.0.3 =
* 機能追加: ブロックエディタで、エンティティごとに画像のデザイン（丸 / 角丸の四角 / 四角 / 楕円（縦長・横長）/ 切り抜かない）を選べるようにした
* 機能追加: ブロックエディタのサイドバーに「並び順」を追加。ドラッグまたは ↑↓ ボタンでパネルの順番を変更できる
* 機能追加: 出力モードに「出力しない（パネルの表示だけ）」を追加
* 機能追加: Custom モードで紐付け先の @id が空のとき、ブロックエディタに警告を表示
* 変更: 「パネルデザイン」を「パネルのデザイン」（形）と「パネルのカラー」（色）の 2 項目に分割。カラーはプリセット 7 種とカスタム（背景色・文字色・アクセント色）。旧「Dark」は「標準 × ダーク」として読み替え
* 変更: テーブル構造のバージョンを記録し、ファイル上書き更新時も不足列を自動追加
* バグ修正: sameAs URL のパーセントエンコード（%E6 等）が保存時に削除され、日本語を含む URL が壊れる問題を修正
* バグ修正: Threads の新ドメイン threads.com が汎用アイコンになる問題を修正
* 削除: Web から直接実行できる状態になっていた開発用テストスクリプト（tests/）を配布物から削除

= 1.0.2 =
* セキュリティ: sameAs URL のスキームを http/https の許可リストに制限。`javascript:`、`data:`、`file:` 等の危険スキームが Schema.org JSON-LD の sameAs 配列に混入する経路を遮断（フィルタフック `kapm_same_as_protocols` で許可スキーム拡張可）
* バグ修正: ブロック属性 labels に非 scalar 値 (配列/オブジェクト) が混入した場合に `esc_html()` が PHP 8 系で TypeError を起こしてフロント描画が破綻する問題を修正

= 1.0.1 =
* セキュリティ: Customモード JSON-LD の `target_schema_id` 未サニタイズによる stored XSS（CRITICAL）を修正
* セキュリティ: REST API レスポンスを id/name/name_en/role のみに制限し、情報漏洩を抑止
* セキュリティ: `get_posts_using_entity()` の type 引数に whitelist を追加
* セキュリティ: admin-media.js のツールチップ表示を innerHTML から DOM cloneNode に変更
* セキュリティ: 管理画面スクリプト読み込み判定を `$_GET['page']` から `$hook_suffix` に変更
* バグ修正: 「使用中の記事」逆引きがネストブロック（Group / Columns / Row 等）内の著者パネルを検出するよう再帰対応
* バグ修正: `parse_ids()` が負数を絶対値化する問題と ID 重複を除去
* バグ修正: 同一 `target_schema_id` への複数 Custom モードブロックで `<script>` タグが重複出力される問題を修正
* バグ修正: ブロック属性 JSON の labels が壊れている場合にブロックエディタが例外停止する問題を修正
* バグ修正: ブロック属性が非 scalar のときに TypeError で落ちる余地を塞ぐ
* バグ修正: URL 末尾のフラグメント（`#section`）付き URL で二重フラグメント @id が生成される問題を修正
* バグ修正: 管理画面の保存/削除通知を `admin_notices` フック経由で標準位置に表示
* バグ修正: Corporation/Organization 一覧の URL セルが `esc_url` 誤用でテキスト表示できない問題を修正
* リファクタリング: Person/Corporation/Organization の CRUD を汎用メソッドに統合（既存 public API は互換 wrapper として維持）
* リファクタリング: 管理画面の handle_person/corporation/organization を `handle_entity( $type )` に統合
* リファクタリング: 著者カードの HTML 生成を `build_entity_card()` に統合
* リファクタリング: `$custom_mode_data` を static からインスタンスプロパティに変更
* リファクタリング: REST ルート 3 個をループで登録
* 拡張: `kapm_role_to_schema_property` / `kapm_same_as_icon_map` フィルタフックを追加

= 1.0.0 =
* 初回リリース
