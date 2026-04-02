=== Kashiwazaki SEO Author Panel Manager ===
Contributors: kashiwazakitsuyoshi
Tags: author, schema, json-ld, structured-data, seo
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
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

1. `wp-plugin-kashiwazaki-author-panel-manager` ディレクトリを `/wp-content/plugins/` にアップロードしてください。
2. WordPress管理画面の「プラグイン」メニューから有効化してください。
3. 管理メニューの「Kashiwazaki SEO Author Panel Manager」からエンティティを登録してください。

== Frequently Asked Questions ==

= 既存のSEOプラグインと併用できますか？ =

はい。Customモードを使うことで、Yoast SEOやRank Math等が出力するArticle/NewsArticleスキーマに、author/publisherプロパティを紐付けることができます。

= WordPressのユーザーデータとは別ですか？ =

はい。本プラグインは独自のデータベーステーブルでエンティティを管理するため、WordPressユーザーとは完全に独立しています。

== Changelog ==

= 1.0.0 =
* 初回リリース
