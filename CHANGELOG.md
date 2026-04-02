# 更新履歴

このプロジェクトのすべての変更はこのファイルに記録されます。
フォーマットは [Keep a Changelog](https://keepachangelog.com/ja/1.0.0/) に基づいています。

## [1.0.0] - 2026-04-02

### 追加
- Person（人物）、Corporation（法人）、Organization（組織）の独立データベース管理
- Gutenbergブロックエディタ対応（挿入・設定・ライブプレビュー）
- Schema.org JSON-LD構造化データ出力（Person / Corporation / Organization）
- Standardモード（独立JSON-LD出力）
- Customモード（既存スキーマへのauthor/publisher紐付け、wp_head出力）
- エンティティごとの表示ラベル設定（執筆者・監修者・運営会社等）
- 5種類のパネルデザイン（Default / Dark / Accent / Minimal / Card）
- sameAs URLのソーシャルアイコン自動表示（Dashicons）
- メディアライブラリからの画像・ロゴ選択
- 管理画面タブ切り替えUI（Person / Corporation / Organization）
- エンティティ編集画面に使用中の記事一覧を表示
- ブロックエディタサイドバーからエンティティ管理画面へのリンク
- Roleフィールドのツールチップヘルプ（Author / Publisher / Editor / Reviewer等）
- プラグイン一覧画面に「設定」リンク
- HTMLコメントによるJSON-LD出力マーキング
- ネストブロック対応（Group / Columns内でのCustomモード検出）
- WCAG AA準拠のコントラスト比（Darkテーマ含む全デザイン）
- uninstall.phpによるアンインストール時テーブル削除

[1.0.0]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-author-panel-manager/releases/tag/v1.0.0-dev
