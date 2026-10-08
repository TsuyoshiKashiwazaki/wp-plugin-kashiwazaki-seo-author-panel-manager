# Kashiwazaki SEO Author Panel Manager

![Version](https://img.shields.io/badge/version-1.0.4-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)

Person（人物）、Corporation（法人）、Organization（組織）の3種類のエンティティを独立したデータベースで管理し、Gutenbergブロックで著者パネルとSchema.org JSON-LD構造化データを出力するWordPressプラグインです。

## 主な機能

- **独立したエンティティデータベース** - WordPressユーザーとは別に、Person / Corporation / Organization を個別管理
- **Gutenbergブロック対応** - ブロックエディタから挿入・設定・プレビューが可能
- **Schema.org JSON-LD出力** - `Person`、`Corporation`、`Organization` の構造化データをGoogleリッチリザルト仕様に準拠して出力
- **Standard / Custom / 出力しない** - 独立したJSON-LD出力、他プラグイン（Yoast SEO、Rank Math等）の既存スキーマへの紐付け、またはJSON-LDを出さずにパネルだけ表示
- **エンティティごとの表示ラベル** - 記事ごとに「執筆者」「監修者」「運営会社」等のラベルをブロックエディタから個別設定
- **画像のデザイン** - 丸 / 角丸の四角 / 四角 / 楕円（縦長・横長）/ 切り抜かない を記事ごと・エンティティごとに選択
- **並び順** - ブロックエディタのサイドバーでドラッグまたは ↑↓ ボタンでパネルの順番を変更
- **パネルのデザインとカラー** - 形（標準 / アクセント / ミニマル / カード）と色（おまかせ / グレー / 白 / ダーク / ブルー / グリーン / オレンジ / カスタム）をエンティティごとに別々に選択。カスタムでは背景色・文字色・アクセント色を指定可能
- **住所・連絡先・法人情報** - Corporation / Organization に住所・電話番号・メール・問い合わせ窓口・正式名称・設立日・従業員数・各種識別番号を任意で入力。住所と電話番号はパネルに表示し、入力した項目を名前の横の吹き出し（国コードは除く）と構造化データに出力
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

ブロックエディタで「+」ボタンをクリックし、「著者」または「Kashiwazaki」で検索。**Kashiwazaki SEO Author Panel Manager** ブロックを選択し、右サイドバーでエンティティ・ラベル・画像のデザイン・並び順・出力モードを設定します。

### ショートコード（代替手段）

```
[author_panel persons="1,2" corporations="1" organizations="1" mode="standard"]
```

### Customモード

他プラグインが出力した既存のJSON-LDスキーマに、author/publisher等のプロパティを紐付けます。

```
[author_panel persons="1" corporations="1" mode="custom" target_schema_id="https://example.com/post/#article"]
```

CustomモードのJSON-LDは `<head>` 内に出力されます。紐付け先の `@id` が空のときは、ブロックエディタに警告が表示されます。

### 出力しない

JSON-LDを出さずに、パネルの表示だけに使います。並び順と画像のデザインも指定できます。

```
[author_panel persons="1" corporations="1" mode="none" order="corp-1,person-1" image_styles='{"corp-1":"original"}']
```

## パネルのデザインとカラー

各エンティティの編集画面で、形と色を別々に選びます。

| パネルのデザイン（形） | 説明 |
|--------|------|
| 標準 | 背景 + 細い枠線 |
| アクセント | 左に太線 |
| ミニマル | 枠なし、下線のみ |
| カード | シャドウ + 大きめ角丸 |

| パネルのカラー（色） | 説明 |
|--------|------|
| おまかせ | デザインごとの標準配色（既定） |
| グレー / 白 / ブルー / グリーン / オレンジ | 明るい配色 |
| ダーク | ダークネイビー背景 + 水色アクセント（WCAG AA準拠） |
| カスタム | 背景色・文字色・アクセント色を指定 |

## ライセンス

GPL-2.0+

## 作者

柏崎剛 (Tsuyoshi Kashiwazaki)
https://www.tsuyoshikashiwazaki.jp
