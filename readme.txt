=== Kashiwazaki SEO Author Panel Manager ===
Contributors: kashiwazakitsuyoshi
Tags: author, schema, json-ld, structured-data, seo
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Person、Corporation、Organizationの3種類のエンティティを独立管理し、著者パネルとSchema.org JSON-LD構造化データを出力するプラグインです。

== Description ==

Kashiwazaki SEO Author Panel Managerは、WordPressユーザーとは独立した著者データベースを管理し、Gutenbergブロックで著者パネルとSchema.org JSON-LD構造化データを出力するプラグインです。

主な機能:

* Person（人物）、Corporation（法人）、Organization（組織）の独立管理
* Gutenbergブロックエディタ対応（挿入・設定・プレビュー）
* Schema.org JSON-LD構造化データ出力（Person / Corporation / Organization）
* Standardモード（独立JSON-LD）とCustomモード（既存スキーマへの紐付け）
* エンティティごとの表示ラベル設定（執筆者・監修者・運営会社等）
* 5種類のパネルデザイン（Default / Dark / Accent / Minimal / Card）
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
