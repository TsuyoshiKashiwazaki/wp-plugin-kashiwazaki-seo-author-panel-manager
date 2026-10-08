# 更新履歴

このプロジェクトのすべての変更はこのファイルに記録されます。
フォーマットは [Keep a Changelog](https://keepachangelog.com/ja/1.0.0/) に基づいています。

## [1.0.4] - 2026-10-08

### 追加
- Corporation / Organization の編集画面に、任意の **住所・電話番号**（郵便番号 / 都道府県 / 市区町村 / 番地・建物名 / 国コード / 電話番号）、**メール・問い合わせ窓口**（メールアドレス / 窓口の種類・電話番号・メールアドレス）、**法人情報**（正式名称 / 設立日 / 従業員数 / taxID / vatID / ISO 6523 コード / DUNS / LEI / GLN / NAICS）の 20 項目を追加。項目と書式は Google 検索セントラルの Organization 構造化データのドキュメントに合わせた。
- 住所と電話番号をパネルの説明文の下に表示（アイコン付き。電話番号は `tel:` リンク）。国コードが JP か空なら「〒郵便番号 都道府県市区町村番地」、それ以外の国は「番地, 市区町村, 州 郵便番号」の順。
- 住所・連絡先・法人情報のどれかが入力されていると、パネルの名前の横に **i ボタン** を表示し、マウスを乗せる・タップする・キーボードで移動すると、入力した項目の「項目名」と「内容」の一覧を吹き出しで表示（`role="tooltip"` + `aria-describedby`）。表示は CSS だけで行い、Esc キーで閉じる処理だけを数行のインラインスクリプト（`kapm-panel-info`）で行う。スクリプトは吹き出しのあるパネルを出したページにだけ読み込む。吹き出しは名前の行の下にパネルの情報欄の幅いっぱいで出し (i ボタンの位置に合わせないので名前が長くても画面からはみ出さない)、画面幅 600px 以下では項目名と内容を縦に並べる。
- JSON-LD に `address`（`PostalAddress`）/ `telephone` / `email` / `contactPoint`（`ContactPoint`）/ `legalName` / `foundingDate` / `numberOfEmployees`（`QuantitativeValue`。人数は `value`、範囲は `minValue` / `maxValue`）/ `taxID` / `vatID` / `iso6523Code` / `duns` / `leiCode` / `globalLocationNumber` / `naics` を出力。空欄の項目は出力しない。住所は国コード以外の項目が 1 つでもあるとき、問い合わせ窓口は電話番号かメールアドレスがあるときだけ出力する。
- 国コード（英字 2 文字）・メールアドレス・設立日（YYYY / YYYY-MM / YYYY-MM-DD の実在する日付）・従業員数（人数か範囲。全角数字・カンマ・「〜」も可）の書式を保存時に確認し、合わない値は空欄で保存して管理画面に警告を表示。

### 変更
- データベースの `apm_corporations` / `apm_organizations` に 20 列を追加（`kapm_db_version` を 3 に更新。ファイル上書き更新でも次の読み込み時に `dbDelta()` で列を追加）。
- 保存時の値の無害化を `KAPM_Database::sanitize_value()` にまとめた。

## [1.0.3] - 2026-10-05

### 追加
- ブロックエディタで、エンティティごとに **画像のデザイン** を選べるようにした（丸（標準）/ 角丸の四角 / 四角 / 楕円（縦長）/ 楕円（横長）/ 切り抜かない）。各エンティティの「表示ラベル」の下に表示。ブロック属性 `imageStyles`、ショートコード属性 `image_styles` を追加。許可された値以外は丸として扱う。
- ブロックエディタのサイドバーに **並び順** パネルを追加。エンティティを 2 件以上選ぶと表示され、ドラッグまたは ↑↓ ボタンでパネルの表示順を変更できる。ブロック属性・ショートコード属性 `order`（例: `person-1,org-1,corp-1`）を追加。並び順に含まれないパネルは既定の順（Person → Corporation → Organization）で後ろに付く。
- 出力モードに **出力しない（パネルの表示だけ）**（`mode="none"`）を追加。JSON-LD を出力せず HTML パネルだけを表示する。
- Custom モードで紐付け先スキーマの `@id` が空のとき、サイドバーとブロックのプレビューに警告を表示。
- パネルのカラーに **カスタム** を追加。背景色・文字色・アクセント色を WordPress 標準のカラーピッカーで指定でき、カードの `style` 属性に CSS 変数として出力する。
- テーブル構造のバージョンをオプション `kapm_db_version` に記録し、`plugins_loaded` で古い構造を検出したら `dbDelta()` で不足列を追加する仕組みを追加（ファイル上書き更新でも再有効化が不要）。アンインストール時はこのオプションも削除する。

### 変更
- 「パネルデザイン」を **パネルのデザイン**（形: 標準 / アクセント / ミニマル / カード）と **パネルのカラー**（色: おまかせ / グレー / 白 / ダーク / ブルー / グリーン / オレンジ / カスタム）の 2 項目に分割。形と色が 1 つの選択肢に混在していた（Dark だけが色）ため、組み合わせを選べなかった。
- 既定のカラー「おまかせ」は、デザインごとに v1.0.2 までと同じ配色で表示する。旧値 `panel_style = dark` は「標準 × ダーク」として読み替える（データの書き換えは不要）。
- パネルの CSS を CSS 変数（`--kapm-bg` / `--kapm-text` / `--kapm-accent` 等）で色を受け取る構造に変更。カードに `kapm-color-*` と `kapm-image-*` のクラスを追加。
- データベースに `panel_color` / `color_bg` / `color_text` / `color_accent` の 4 列を追加。

### バグ修正
- sameAs URL を `sanitize_textarea_field()` で保存していたため、`%E6%9F%8F` のようなパーセントエンコードが削除され、日本語を含む URL（例: LinkedIn の日本語プロフィール URL）が壊れる問題を修正。1 行ずつ `esc_url_raw()` で無害化する方式に変更。
- Threads の新ドメイン `threads.com` の URL が汎用の Web サイトアイコンで表示される問題を修正（アイコン対応表に追加）。

### 削除
- 開発用のテストスクリプト `tests/runtime-test-v1.0.2.php` をリポジトリと配布物から削除。WordPress なしで単体実行できるスクリプトのため、プラグインフォルダに置かれると Web から直接実行できる状態になっていた。

## [1.0.2] - 2026-04-15

### セキュリティ
- **[MEDIUM]** Schema.org JSON-LD の sameAs 配列に `javascript:`、`data:`、`file:` 等の危険なスキームを含む URL が混入する経路を遮断 (`includes/class-shortcode.php`)。`extract_valid_urls()` を `filter_var( FILTER_VALIDATE_URL )` のみの検証から、`wp_parse_url()` で取り出した scheme を許可リスト (`http`/`https`) と照合する方式に変更。フロント `<a href>` は `esc_url()` でガードされていたが、JSON-LD 構造化データの sameAs は esc 不要な領域でそのまま consumer (headless WordPress / 他 SEO プラグイン / クローラ) に渡るため、後段で任意スキームが意図せず処理される vector を塞いだ。フィルタフック `kapm_same_as_protocols` を追加し、特殊用途向けに許可スキームを拡張可能。

### バグ修正
- ブロック属性 `labels` の JSON 値が個別キーで非 scalar (配列/オブジェクト) を含む場合に、フロント描画時に `esc_html()` が PHP 8 系で `TypeError` を発生させて著者パネル全体が描画破綻する問題を修正 (`includes/class-shortcode.php`)。`json_decode` 後に各 value を `is_scalar()` でチェックし、非 scalar は空文字に正規化することでフォールバック。

## [1.0.1] - 2026-04-15

### セキュリティ
- **[CRITICAL]** Custom モード JSON-LD の `target_schema_id` 未サニタイズによる stored XSS 脆弱性を修正。`edit_posts` 権限のユーザーがブロック属性に `</script><script>...</script>` を混入した場合、`wp_json_encode( ..., JSON_UNESCAPED_SLASHES )` の組み合わせでフロント `<head>` 内に任意 JavaScript が注入される経路を塞いだ (`includes/class-shortcode.php`)。
- Custom モードデータ収集時に `sanitize_text_field( wp_strip_all_tags() )` で `target_schema_id` を無害化。
- `wp_json_encode()` のフラグから `JSON_UNESCAPED_SLASHES` を削除し `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` を追加 (defense-in-depth)。
- REST API エンドポイント `/kapm/v1/{persons,corporations,organizations}` のレスポンスを `id` / `name` / `name_en` / `role` の 4 フィールドのみに制限。bio / url / image_url / logo_url / description / same_as / job_title / panel_style は編集者以下のロールから隠蔽。
- `KAPM_Database::get_posts_using_entity()` に type 引数の whitelist チェックを追加 (defense-in-depth)。
- 管理画面スクリプト読み込み判定を `$_GET['page']` 直接参照から `add_menu_page()` の `$hook_suffix` 比較に変更 (WPCS 準拠)。
- `admin/js/admin-media.js` のツールチップ表示を `innerHTML` コピーから `cloneNode(true)` ベースの DOM コピーに変更 (将来の XSS 表面を削減)。

### バグ修正
- `KAPM_Database::get_posts_using_entity()` がネストブロック (Group / Columns / Row / Reusable 等) 内に配置された `kapm/author-panel` ブロックを検出できなかった問題を修正。`block_tree_contains_entity()` で再帰走査するよう変更し、`scan_blocks_for_custom()` との挙動差を解消。
- `KAPM_Shortcode::parse_ids()` で負数を `absint()` が絶対値化する問題を修正。`ctype_digit` + `(int) > 0` で正数のみ受け入れるように変更。併せて `array_unique()` で ID 重複も除去。
- 同一 `target_schema_id` への複数 Custom モードブロックが並ぶと `<script>` タグが個別に複数出力される問題を修正。`collect_custom_data()` で既存エントリにマージ。
- ブロック属性の `labels` JSON が壊れている場合に Gutenberg エディタが例外停止する問題を修正 (`safeParseLabels()` で try/catch 化)。
- ブロック属性が非 scalar の場合に `string` 型宣言で TypeError になる余地を塞ぐ (`is_scalar()` チェック + string cast)。
- URL 末尾にフラグメント (`#section`) を含む URL から `@id` を生成すると `https://example.com/#section/#person-1` のような二重フラグメント URL になる問題を修正 (`build_entity_base_url()` で fragment/query を除去)。
- 管理画面の保存/削除通知 `<div class="notice">` を `<div class="kapm-tab-content">` の内側で echo していた問題を修正。`admin_notices` フック経由で標準位置に表示。
- `admin/views/corporation-list.php` と `organization-list.php` で URL セルに `esc_url()` を text context に適用していた誤用を修正。`<a href>` + `esc_html` で正しく表示。
- `filemtime()` で対象ファイルが存在しない場合の warning を `file_exists()` 前置きで抑止。
- PHP 8.0 要件の根拠記述から誤記の「named 引数」を削除し `match` 式と `int|false` Union 戻り値型に限定。

### 変更 (リファクタリング)
- `KAPM_Database` の 3 エンティティ CRUD (`get_persons` / `insert_person` / `update_person` / `delete_person` × 3) を汎用メソッド `get_entities( $type )` / `get_entity( $type, $id )` / `insert_entity( $type, $data )` / `update_entity( $type, $id, $data )` / `delete_entity( $type, $id )` に統合。既存 public API は薄い wrapper として維持し後方互換性を確保。
- `KAPM_Admin` の `handle_person` / `handle_corporation` / `handle_organization` 3 メソッドを `handle_entity( string $type )` 1 本に統合。エンティティ設定は `get_entity_config()` の静的マップに集約。
- `KAPM_Shortcode::build_html()` の 100 行強の Person/Corp/Org HTML 生成を `build_entity_card( $type, $entity, $labels )` 1 メソッドに統合。
- `KAPM_Shortcode::$custom_mode_data` を `private static` から `private` インスタンスプロパティに変更。長寿命 PHP プロセスでの state 汚染を防止。
- `KAPM_Gutenberg::register_rest_routes()` を 3 ルートのコピペから `foreach` ループによる一括登録に変更。
- `KAPM_Shortcode` の `extract_valid_urls()` ヘルパを抽出し、`build_person_node` / `build_org_node` / `render_same_as_icons` 3 箇所から再利用。

### 追加 (拡張)
- フィルタフック `kapm_role_to_schema_property` を追加。`role` 文字列から Schema.org プロパティ名への変換を他プラグイン/テーマから拡張可能に。
- フィルタフック `kapm_same_as_icon_map` を追加。sameAs URL の Dashicons マッピング 44 ドメインを拡張可能に。

### 互換性
- 既存の DB スキーマには変更なし。エンティティデータはそのまま引き継がれる。
- 既存の public API (`KAPM_Database::get_persons()` 等) は後方互換 wrapper として維持。
- ショートコード属性とブロック属性は全て変更なし。

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

[1.0.4]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-author-panel-manager/releases/tag/v1.0.4
[1.0.3]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-author-panel-manager/releases/tag/v1.0.3
[1.0.2]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-author-panel-manager/releases/tag/v1.0.2
[1.0.1]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-author-panel-manager/releases/tag/v1.0.1
[1.0.0]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-author-panel-manager/releases/tag/v1.0.0
