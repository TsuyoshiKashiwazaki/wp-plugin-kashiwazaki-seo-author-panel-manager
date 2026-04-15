# Kashiwazaki SEO Author Panel Manager

![Version](https://img.shields.io/badge/version-1.0.2-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)

Person（人物）、Corporation（法人）、Organization（組織）の3種類のエンティティを独立したデータベースで管理し、Gutenbergブロックで著者パネルとSchema.org JSON-LD構造化データを出力するWordPressプラグインです。

## 主な機能

- **独立したエンティティデータベース** - WordPressユーザーとは別に、Person / Corporation / Organization を個別管理
- **Gutenbergブロック対応** - ブロックエディタから挿入・設定・プレビューが可能
- **Schema.org JSON-LD出力** - `Person`、`Corporation`、`Organization` の構造化データをGoogleリッチリザルト仕様に準拠して出力
- **Standard / Customモード** - 独立したJSON-LD出力、または他プラグイン（Yoast SEO、Rank Math等）の既存スキーマへの紐付け
- **エンティティごとの表示ラベル** - 記事ごとに「執筆者」「監修者」「運営会社」等のラベルをブロックエディタから個別設定
- **5種類のパネルデザイン** - Default / Dark / Accent / Minimal / Card をエンティティごとに選択可能
- **ソーシャルアイコン** - sameAs URLを自動判定してDashiconsで表示（X、Facebook、LinkedIn、GitHub、YouTube等）
- **メディアライブラリ連携** - 画像・ロゴをWordPressメディアライブラリから選択
- **使用記事の追跡** - 各エンティティの編集画面から、どの記事で使われているかを確認可能
- **管理画面リンク** - ブロックエディタのサイドバーからエンティティ管理画面へ直接移動

## 動作要件

- PHP 8.0以上
- WordPress 6.0以上

## インストール

1. `wp-plugin-kashiwazaki-seo-author-panel-manager` ディレクトリを `/wp-content/plugins/` にアップロード
2. WordPress管理画面の「プラグイン」から有効化
3. 管理メニューの「Kashiwazaki SEO Author Panel Manager」からエンティティを登録

## 使い方

### エンティティの登録

管理メニュー「Kashiwazaki SEO Author Panel Manager」を開き、**Person** / **Corporation** / **Organization** タブからエンティティを追加・編集します。

### 記事への挿入

ブロックエディタで「+」ボタンをクリックし、「著者」または「Kashiwazaki」で検索。**Kashiwazaki SEO Author Panel Manager** ブロックを選択し、右サイドバーでエンティティ・ラベル・モードを設定します。

### ショートコード（代替手段）

```
[author_panel persons="1,2" corporations="1" organizations="1" mode="standard"]
```

### Customモード

他プラグインが出力した既存のJSON-LDスキーマに、author/publisher等のプロパティを紐付けます。

```
[author_panel persons="1" corporations="1" mode="custom" target_schema_id="https://example.com/post/#article"]
```

CustomモードのJSON-LDは `<head>` 内に出力されます。

## パネルデザイン

| テーマ | 説明 |
|--------|------|
| Default | グレー背景 + 細ボーダー |
| Dark | ダークネイビー背景 + 水色アクセント（WCAG AA準拠） |
| Accent | 白背景 + 左カラーボーダー |
| Minimal | 背景なし、下線のみ |
| Card | 白背景 + シャドウ + 大きめ角丸 |

## ライセンス

GPL-2.0+

## 作者

柏崎剛 (Tsuyoshi Kashiwazaki)
https://www.tsuyoshikashiwazaki.jp
