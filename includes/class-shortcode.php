<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KAPM_Shortcode {

    /**
     * static → instance プロパティ化
     * 長寿命 PHP プロセス（wp-cli, cron worker 等）での state 汚染を防ぐ
     */
    private array $custom_mode_data = array();

    /**
     * 住所・連絡先・法人情報の吹き出しの id 連番（同じエンティティが 1 ページに複数あっても id が重ならないように）
     */
    private int $org_info_seq = 0;

    /**
     * wp_json_encode のフラグ (defense-in-depth):
     * - JSON_UNESCAPED_UNICODE: 日本語をそのまま出力
     * - JSON_HEX_TAG: < / > を \u003c / \u003e にエスケープ (HTML コンテキストで </script> 終了を防ぐ)
     * - JSON_HEX_AMP / JSON_HEX_APOS / JSON_HEX_QUOT: 追加の HTML 安全性
     * - JSON_PRETTY_PRINT: 可読性
     * ※ JSON_UNESCAPED_SLASHES は意図的に使用しない（S1 XSS 修正）
     */
    private const JSON_ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT;

    /**
     * 画像のデザイン（エンティティごとに選択）と <img> の width / height
     * height が 0 のものは高さ属性を出さず、元画像の縦横比のまま表示する
     * 未指定・未知の値は 'circle'（従来の表示）にフォールバック
     */
    private const IMAGE_STYLES = array(
        'circle'    => array( 80, 80 ),
        'rounded'   => array( 80, 80 ),
        'square'    => array( 80, 80 ),
        'ellipse-v' => array( 72, 96 ),
        'ellipse-h' => array( 104, 72 ),
        'original'  => array( 80, 0 ),
    );

    public function __construct() {
        add_shortcode( 'author_panel', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'template_redirect', array( $this, 'preparse_custom_mode' ) );
        add_action( 'wp_head', array( $this, 'output_custom_json_ld' ), 99 );
    }

    public function enqueue_styles(): void {
        $css_path = KAPM_PLUGIN_PATH . 'public/css/panel-style.css';
        $version  = KAPM_VERSION;
        if ( file_exists( $css_path ) ) {
            $version .= '.' . filemtime( $css_path );
        }
        wp_register_style(
            'kapm-panel-style',
            KAPM_PLUGIN_URL . 'public/css/panel-style.css',
            array( 'dashicons' ),
            $version
        );

        // 吹き出し（.kapm-org-info）を Esc キーで閉じるためだけのスクリプト。吹き出しがあるパネルを出したときだけ読み込む
        // （表示自体は CSS の :hover / :focus-within で行う。WCAG 1.4.13 の「消せること」のため）
        wp_register_script( 'kapm-panel-info', false, array(), KAPM_VERSION, true );
        wp_add_inline_script(
            'kapm-panel-info',
            "document.addEventListener('keydown',function(e){if(e.key!=='Escape')return;document.querySelectorAll('.kapm-org-info').forEach(function(el){if(el.matches(':hover')||el.contains(document.activeElement)){el.classList.add('is-dismissed');}});});"
            . "document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.kapm-org-info').forEach(function(el){var reset=function(){el.classList.remove('is-dismissed');};el.addEventListener('mouseleave',reset);el.addEventListener('focusout',reset);});});"
        );
    }

    /**
     * template_redirect で投稿内容を先行パースし、custom モードのショートコードを検出
     */
    public function preparse_custom_mode(): void {
        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! $post || empty( $post->post_content ) ) {
            return;
        }

        $content = $post->post_content;

        // Gutenbergブロック形式を検出（ネストブロック対応）
        $blocks = parse_blocks( $content );
        $this->scan_blocks_for_custom( $blocks );

        // ショートコード形式を検出
        if ( has_shortcode( $content, 'author_panel' ) ) {
            $pattern = get_shortcode_regex( array( 'author_panel' ) );
            if ( preg_match_all( '/' . $pattern . '/s', $content, $matches, PREG_SET_ORDER ) ) {
                foreach ( $matches as $match ) {
                    $atts = shortcode_parse_atts( $match[3] );
                    $atts = shortcode_atts( array(
                        'persons'          => '',
                        'corporations'     => '',
                        'organizations'    => '',
                        'mode'             => 'standard',
                        'target_schema_id' => '',
                    ), $atts, 'author_panel' );

                    if ( $atts['mode'] !== 'custom' || empty( $atts['target_schema_id'] ) ) {
                        continue;
                    }
                    $this->collect_custom_data(
                        $atts['persons'],
                        $atts['corporations'],
                        $atts['organizations'],
                        $atts['target_schema_id']
                    );
                }
            }
        }
    }

    /**
     * ブロック配列を再帰的にスキャンしてcustomモードを検出
     * attrs が非 scalar の場合は string cast してから処理
     */
    private function scan_blocks_for_custom( array $blocks ): void {
        foreach ( $blocks as $block ) {
            if ( ( $block['blockName'] ?? '' ) === 'kapm/author-panel' ) {
                $a = $block['attrs'] ?? array();
                $mode            = is_scalar( $a['mode'] ?? '' ) ? (string) ( $a['mode'] ?? 'standard' ) : 'standard';
                $target_schema_id = is_scalar( $a['targetSchemaId'] ?? '' ) ? (string) ( $a['targetSchemaId'] ?? '' ) : '';

                if ( $mode === 'custom' && $target_schema_id !== '' ) {
                    $this->collect_custom_data(
                        is_scalar( $a['persons'] ?? '' )       ? (string) ( $a['persons'] ?? '' )       : '',
                        is_scalar( $a['corporations'] ?? '' )  ? (string) ( $a['corporations'] ?? '' )  : '',
                        is_scalar( $a['organizations'] ?? '' ) ? (string) ( $a['organizations'] ?? '' ) : '',
                        $target_schema_id
                    );
                }
            }
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                $this->scan_blocks_for_custom( $block['innerBlocks'] );
            }
        }
    }

    /**
     * customモードデータを収集
     * target_schema_id を URL/URI として無害化
     * - wp_strip_all_tags() で HTML タグを除去
     * - esc_url_raw() で URL として正規化（< > " 等の危険文字を削除または URL エンコード）
     * - JSON-LD @id で使われる URI スキーム (http/https/urn/doi/did/tag/ark) を allowlist
     *   → WP 標準の esc_url_raw allowlist には doi/did/tag/ark が無いため、明示 allowlist で後方互換維持
     *
     * - fragment (#article) や相対パス (/path) は allowlist 非対象のため自動的に保持される
     * 同じ target_schema_id は既存エントリにマージし <script> 重複を防ぐ
     */
    private function collect_custom_data( string $persons_str, string $corps_str, string $orgs_str, string $target_schema_id ): void {
        // 入口で URL として正規化（runtime test section 4 で onclick= 残留が検出されたため強化）
        $allowed_protocols = array( 'http', 'https', 'urn', 'doi', 'did', 'tag', 'ark' );
        /**
         * target_schema_id の許容 URI スキームを拡張可能にする
         *
         * @param array $allowed_protocols 既定の allowlist
         */
        $allowed_protocols = (array) apply_filters( 'kapm_target_schema_id_protocols', $allowed_protocols );
        $target_schema_id  = esc_url_raw( wp_strip_all_tags( $target_schema_id ), $allowed_protocols );
        if ( $target_schema_id === '' ) {
            return;
        }

        $persons       = $this->fetch_entities( $this->parse_ids( $persons_str ), 'person' );
        $corporations  = $this->fetch_entities( $this->parse_ids( $corps_str ), 'corporation' );
        $organizations = $this->fetch_entities( $this->parse_ids( $orgs_str ), 'organization' );

        if ( empty( $persons ) && empty( $corporations ) && empty( $organizations ) ) {
            return;
        }

        // 同じ @id に対しては既存エントリにマージして複数 <script> の出力を避ける
        foreach ( $this->custom_mode_data as &$existing ) {
            if ( $existing['target_schema_id'] === $target_schema_id ) {
                $existing['persons']       = array_merge( $existing['persons'], $persons );
                $existing['corporations']  = array_merge( $existing['corporations'], $corporations );
                $existing['organizations'] = array_merge( $existing['organizations'], $organizations );
                return;
            }
        }
        unset( $existing );

        $this->custom_mode_data[] = array(
            'target_schema_id' => $target_schema_id,
            'persons'          => $persons,
            'corporations'     => $corporations,
            'organizations'    => $organizations,
        );
    }

    /**
     * wp_head で custom モードの JSON-LD を出力
     * JSON_HEX_TAG 付きフラグを使用し </script> 閉じ攻撃を無害化
     */
    public function output_custom_json_ld(): void {
        if ( empty( $this->custom_mode_data ) ) {
            return;
        }

        foreach ( $this->custom_mode_data as $data ) {
            $json_ld = $this->build_custom_json_ld(
                $data['persons'],
                $data['corporations'],
                $data['organizations'],
                $data['target_schema_id']
            );
            echo "<!-- Kashiwazaki SEO Author Panel Manager - Custom Mode JSON-LD -->\n";
            echo "<script type=\"application/ld+json\">\n";
            echo wp_json_encode( $json_ld, self::JSON_ENCODE_FLAGS );
            echo "\n</script>\n";
            echo "<!-- / Kashiwazaki SEO Author Panel Manager -->\n";
        }
    }

    public function render( $atts ): string {
        $atts = shortcode_atts( array(
            'persons'          => '',
            'corporations'     => '',
            'organizations'    => '',
            'mode'             => 'standard',
            'target_schema_id' => '',
            'labels'           => '{}',
            'image_styles'     => '{}',
            'order'            => '',
        ), $atts, 'author_panel' );

        $person_ids = $this->parse_ids( $atts['persons'] );
        $corp_ids   = $this->parse_ids( $atts['corporations'] );
        $org_ids    = $this->parse_ids( $atts['organizations'] );

        if ( empty( $person_ids ) && empty( $corp_ids ) && empty( $org_ids ) ) {
            return '';
        }

        $persons       = $this->fetch_entities( $person_ids, 'person' );
        $corporations  = $this->fetch_entities( $corp_ids, 'corporation' );
        $organizations = $this->fetch_entities( $org_ids, 'organization' );

        if ( empty( $persons ) && empty( $corporations ) && empty( $organizations ) ) {
            return '';
        }

        wp_enqueue_style( 'kapm-panel-style' );

        // json_decode 失敗時は空配列フォールバック（is_array 明示チェック）
        $decoded_labels = json_decode( (string) $atts['labels'], true );
        $labels         = is_array( $decoded_labels ) ? $decoded_labels : array();

        // 各 value を scalar に正規化 (非 scalar は esc_html で TypeError を起こすため空文字へ)
        foreach ( $labels as $key => $value ) {
            $labels[ $key ] = is_scalar( $value ) ? (string) $value : '';
        }

        // 画像のデザイン: 許可リストにある値だけを残す（それ以外は build_entity_card で circle 扱い）
        $decoded_image_styles = json_decode( (string) $atts['image_styles'], true );
        $image_styles         = array();
        if ( is_array( $decoded_image_styles ) ) {
            foreach ( $decoded_image_styles as $key => $value ) {
                if ( is_scalar( $value ) && isset( self::IMAGE_STYLES[ (string) $value ] ) ) {
                    $image_styles[ $key ] = (string) $value;
                }
            }
        }

        $html = $this->build_html( $persons, $corporations, $organizations, $labels, $image_styles, $this->parse_order( (string) $atts['order'] ) );

        if ( $atts['mode'] === 'standard' ) {
            $json_ld = $this->build_standard_json_ld( $persons, $corporations, $organizations );
            $html .= "\n<!-- Kashiwazaki SEO Author Panel Manager - Standard Mode JSON-LD -->\n";
            $html .= "<script type=\"application/ld+json\">\n" . wp_json_encode( $json_ld, self::JSON_ENCODE_FLAGS ) . "\n</script>\n";
            $html .= "<!-- / Kashiwazaki SEO Author Panel Manager -->\n";
        }
        // custom モードの JSON-LD は wp_head で出力済み

        return $html;
    }

    /**
     * 負数の絶対値化を防ぐ
     * ID 重複を array_unique で除去
     */
    private function parse_ids( string $str ): array {
        if ( trim( $str ) === '' ) {
            return array();
        }
        $ids = array();
        foreach ( explode( ',', $str ) as $part ) {
            $part = trim( $part );
            if ( $part === '' || ! ctype_digit( $part ) ) {
                continue;
            }
            $id = (int) $part;
            if ( $id > 0 ) {
                $ids[] = $id;
            }
        }
        return array_values( array_unique( $ids ) );
    }

    /**
     * 並び順（"corp-1,person-1,org-2" 形式）を検証済みのキー配列にする
     */
    private function parse_order( string $str ): array {
        $keys = array();
        foreach ( explode( ',', $str ) as $part ) {
            $part = trim( $part );
            if ( preg_match( '/^(person|corp|org)-[1-9][0-9]*$/', $part ) ) {
                $keys[] = $part;
            }
        }
        return array_values( array_unique( $keys ) );
    }

    private function fetch_entities( array $ids, string $type ): array {
        $results = array();
        foreach ( $ids as $id ) {
            $entity = KAPM_Database::get_entity( $type, (int) $id );
            if ( $entity ) {
                $entity['_type'] = $type;
                $results[] = $entity;
            }
        }
        return $results;
    }

    // =========================================================================
    // HTML 組み立てを entity card 単位に分割
    // =========================================================================

    private function build_html( array $persons, array $corporations, array $organizations, array $labels = array(), array $image_styles = array(), array $order = array() ): string {
        // 既定の並び（Person → Corporation → Organization）。キーは labels と同じ person-1 / corp-1 / org-1
        $cards = array();
        foreach ( array( 'person' => $persons, 'corporation' => $corporations, 'organization' => $organizations ) as $type => $entities ) {
            $prefix = $type === 'person' ? 'person' : ( $type === 'corporation' ? 'corp' : 'org' );
            foreach ( $entities as $entity ) {
                $cards[ $prefix . '-' . ( $entity['id'] ?? 0 ) ] = array( $type, $entity );
            }
        }

        // 並び順の指定があれば、その順に並べ替える（指定に無いカードは既定の並びのまま後ろに付ける）
        $sorted = array();
        foreach ( $order as $key ) {
            if ( isset( $cards[ $key ] ) ) {
                $sorted[ $key ] = $cards[ $key ];
            }
        }
        $cards = $sorted + $cards;

        ob_start();
        echo '<div class="kapm-author-panel">';
        foreach ( $cards as $card ) {
            echo $this->build_entity_card( $card[0], $card[1], $labels, $image_styles );
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * 1 エンティティのカード HTML を構築
     * 全フィールドは esc_html / esc_attr / esc_url で個別にエスケープ
     */
    private function build_entity_card( string $type, array $entity, array $labels, array $image_styles = array() ): string {
        $type_class = $type === 'person' ? 'kapm-person' : ( $type === 'corporation' ? 'kapm-corporation' : 'kapm-organization' );
        $label_key_prefix = $type === 'person' ? 'person' : ( $type === 'corporation' ? 'corp' : 'org' );
        $label_key  = $label_key_prefix . '-' . ( $entity['id'] ?? 0 );
        $label_text = $labels[ $label_key ] ?? '';

        $image_style = $image_styles[ $label_key ] ?? 'circle';
        if ( ! isset( self::IMAGE_STYLES[ $image_style ] ) ) {
            $image_style = 'circle';
        }
        list( $image_width, $image_height ) = self::IMAGE_STYLES[ $image_style ];

        // Person は image_url と bio、Corp/Org は logo_url と description を使う
        $image_url   = $type === 'person' ? ( $entity['image_url'] ?? '' ) : ( $entity['logo_url'] ?? '' );
        $description = $type === 'person' ? ( $entity['bio'] ?? '' ) : ( $entity['description'] ?? '' );
        $job_title   = $type === 'person' ? ( $entity['job_title'] ?? '' ) : '';
        // 住所と電話番号は Corp/Org だけ（入力があるときだけ表示）
        $address     = $type === 'person' ? '' : $this->format_address( $entity );
        $telephone   = $type === 'person' ? '' : trim( (string) ( $entity['telephone'] ?? '' ) );
        $tel_href    = preg_replace( '/[^0-9+]/', '', $telephone );
        $info_items  = $type === 'person' ? array() : $this->build_org_info_items( $entity );
        $info_id     = '';
        if ( ! empty( $info_items ) ) {
            $info_id = 'kapm-org-info-' . ( ++$this->org_info_seq );
            wp_enqueue_script( 'kapm-panel-info' );
        }

        $appearance  = KAPM_Database::normalize_panel_appearance( $entity );
        $panel_style = $appearance['design'];
        $panel_color = $appearance['color'];
        $color_vars  = $panel_color === 'custom' ? $this->build_custom_color_vars( $entity ) : '';
        $name        = $entity['name'] ?? '';
        $name_en     = $entity['name_en'] ?? '';
        $url         = $entity['url'] ?? '';

        ob_start();
        ?>
        <div class="kapm-author-card <?php echo esc_attr( $type_class ); ?> kapm-style-<?php echo esc_attr( $panel_style ); ?> kapm-color-<?php echo esc_attr( $panel_color ); ?> kapm-image-<?php echo esc_attr( $image_style ); ?>"<?php if ( $color_vars !== '' ) : ?> style="<?php echo esc_attr( $color_vars ); ?>"<?php endif; ?>>
            <div class="kapm-author-image-wrap">
                <?php if ( $image_url !== '' ) : ?>
                    <div class="kapm-author-image">
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" width="<?php echo (int) $image_width; ?>"<?php if ( $image_height > 0 ) : ?> height="<?php echo (int) $image_height; ?>"<?php endif; ?> loading="lazy">
                    </div>
                <?php endif; ?>
                <?php if ( $label_text !== '' ) : ?>
                    <div class="kapm-author-label"><?php echo esc_html( $label_text ); ?></div>
                <?php endif; ?>
            </div>
            <div class="kapm-author-info">
                <div class="kapm-author-name">
                    <?php if ( $url !== '' ) : ?>
                        <?php if ( $type === 'person' ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>" rel="author"><?php echo esc_html( $name ); ?></a>
                        <?php else : ?>
                            <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php echo esc_html( $name ); ?>
                    <?php endif; ?>
                    <?php if ( $name_en !== '' ) : ?>
                        <span class="kapm-name-en">(<?php echo esc_html( $name_en ); ?>)</span>
                    <?php endif; ?>
                    <?php if ( $info_id !== '' ) : ?>
                        <span class="kapm-org-info"><button type="button" class="kapm-org-info-button" aria-label="<?php esc_attr_e( '詳細情報', 'kashiwazaki-seo-author-panel-manager' ); ?>" aria-describedby="<?php echo esc_attr( $info_id ); ?>"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span></button><span class="kapm-org-info-bubble" role="tooltip" id="<?php echo esc_attr( $info_id ); ?>"><?php foreach ( $info_items as $info_item ) : ?><span class="kapm-org-info-row"><span class="kapm-org-info-label"><?php echo esc_html( $info_item[0] ); ?></span><span class="kapm-org-info-value"><?php echo esc_html( $info_item[1] ); ?></span></span><?php endforeach; ?></span></span>
                    <?php endif; ?>
                </div>
                <?php if ( $job_title !== '' ) : ?>
                    <div class="kapm-author-job"><?php echo esc_html( $job_title ); ?></div>
                <?php endif; ?>
                <?php if ( $description !== '' ) : ?>
                    <div class="kapm-author-bio"><?php echo esc_html( $description ); ?></div>
                <?php endif; ?>
                <?php if ( $address !== '' || $telephone !== '' ) : ?>
                    <div class="kapm-author-contact">
                        <?php if ( $address !== '' ) : ?>
                            <div class="kapm-author-address"><span class="dashicons dashicons-location" role="img" aria-label="<?php esc_attr_e( '住所', 'kashiwazaki-seo-author-panel-manager' ); ?>"></span><span><?php echo esc_html( $address ); ?></span></div>
                        <?php endif; ?>
                        <?php if ( $telephone !== '' ) : ?>
                            <div class="kapm-author-tel"><span class="dashicons dashicons-phone" role="img" aria-label="<?php esc_attr_e( '電話番号', 'kashiwazaki-seo-author-panel-manager' ); ?>"></span><span><?php if ( $tel_href !== '' ) : ?><a href="<?php echo esc_url( 'tel:' . $tel_href ); ?>"><?php echo esc_html( $telephone ); ?></a><?php else : ?><?php echo esc_html( $telephone ); ?><?php endif; ?></span></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php echo $this->render_same_as_icons( $entity['same_as'] ?? '' ); ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * パネルに表示する住所の文字列
     * 国コードが JP か空なら日本の書き方（〒郵便番号 都道府県市区町村番地）、
     * それ以外は「番地, 市区町村, 州 郵便番号」の順にする
     */
    private function format_address( array $entity ): string {
        $postal   = trim( (string) ( $entity['postal_code'] ?? '' ) );
        $region   = trim( (string) ( $entity['address_region'] ?? '' ) );
        $locality = trim( (string) ( $entity['address_locality'] ?? '' ) );
        $street   = trim( (string) ( $entity['street_address'] ?? '' ) );
        $country  = strtoupper( trim( (string) ( $entity['address_country'] ?? '' ) ) );

        if ( $country === '' || $country === 'JP' ) {
            $line = $region . $locality . $street;
            if ( $postal !== '' ) {
                $line = '〒' . $postal . ( $line !== '' ? ' ' . $line : '' );
            }
            return $line;
        }
        $parts = array( $street, $locality, trim( $region . ' ' . $postal ) );
        return implode( ', ', array_filter( $parts, static fn( string $part ): bool => $part !== '' ) );
    }

    /**
     * 吹き出しに出す「項目名・内容」の一覧（住所・連絡先・法人情報のうち入力がある項目だけ）
     * 並びは入力画面と同じ。国コードは表示せず、住所の書き方の切り替えにだけ使う
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function build_org_info_items( array $entity ): array {
        $value = static fn( string $key ): string => trim( (string) ( $entity[ $key ] ?? '' ) );
        $items = array();

        $address = $this->format_address( $entity );
        if ( $address !== '' ) {
            $items[] = array( __( '住所', 'kashiwazaki-seo-author-panel-manager' ), $address );
        }
        $texts = array(
            'telephone'         => __( '電話番号', 'kashiwazaki-seo-author-panel-manager' ),
            'email'             => __( 'メールアドレス', 'kashiwazaki-seo-author-panel-manager' ),
            'contact_type'      => __( '問い合わせ窓口', 'kashiwazaki-seo-author-panel-manager' ),
            'contact_telephone' => __( '問い合わせ電話番号', 'kashiwazaki-seo-author-panel-manager' ),
            'contact_email'     => __( '問い合わせメール', 'kashiwazaki-seo-author-panel-manager' ),
            'legal_name'        => __( '正式名称', 'kashiwazaki-seo-author-panel-manager' ),
        );
        foreach ( $texts as $key => $label ) {
            $text = in_array( $key, array( 'email', 'contact_email' ), true ) ? KAPM_Database::sanitize_value( 'email', $value( $key ) ) : $value( $key );
            if ( $text !== '' ) {
                $items[] = array( $label, $text );
            }
        }

        // 設立日は「2012年6月26日」（年だけ・年月だけはその粒度で）
        $founding = KAPM_Database::sanitize_value( 'date', $value( 'founding_date' ) );
        if ( $founding !== '' ) {
            $parts   = array_map( 'intval', explode( '-', $founding ) );
            $display = $parts[0] . '年' . ( isset( $parts[1] ) ? $parts[1] . '月' : '' ) . ( isset( $parts[2] ) ? $parts[2] . '日' : '' );
            $items[] = array( __( '設立日', 'kashiwazaki-seo-author-panel-manager' ), $display );
        }

        $employees = KAPM_Database::parse_employees( $value( 'number_of_employees' ) );
        if ( $employees !== null ) {
            $display = $employees['min'] === $employees['max']
                ? number_format_i18n( $employees['min'] ) . '人'
                : number_format_i18n( $employees['min'] ) . '〜' . number_format_i18n( $employees['max'] ) . '人';
            $items[] = array( __( '従業員数', 'kashiwazaki-seo-author-panel-manager' ), $display );
        }

        $codes = array(
            'tax_id'                 => __( '税務上の識別番号', 'kashiwazaki-seo-author-panel-manager' ),
            'vat_id'                 => __( 'VAT 登録番号', 'kashiwazaki-seo-author-panel-manager' ),
            'iso6523_code'           => __( 'ISO 6523 コード', 'kashiwazaki-seo-author-panel-manager' ),
            'duns'                   => __( 'DUNS 番号', 'kashiwazaki-seo-author-panel-manager' ),
            'lei_code'               => __( 'LEI コード', 'kashiwazaki-seo-author-panel-manager' ),
            'global_location_number' => __( 'GLN', 'kashiwazaki-seo-author-panel-manager' ),
            'naics'                  => __( 'NAICS コード', 'kashiwazaki-seo-author-panel-manager' ),
        );
        foreach ( $codes as $key => $label ) {
            if ( $value( $key ) !== '' ) {
                $items[] = array( $label, $value( $key ) );
            }
        }
        return $items;
    }

    /**
     * カラー「カスタム」の CSS 変数（style 属性用）
     * 値は保存時と同じく sanitize_hex_color で再検証し、空・不正な色は出力しない
     * （出力しなかった変数は panel-style.css の .kapm-color-custom の既定値になる）
     */
    private function build_custom_color_vars( array $entity ): string {
        $map  = array(
            'color_bg'     => array( '--kapm-bg' ),
            'color_text'   => array( '--kapm-text', '--kapm-name' ),
            'color_accent' => array( '--kapm-accent' ),
        );
        $vars = array();
        foreach ( $map as $field => $props ) {
            $color = (string) sanitize_hex_color( (string) ( $entity[ $field ] ?? '' ) );
            if ( $color === '' ) {
                continue;
            }
            foreach ( $props as $prop ) {
                $vars[] = $prop . ':' . $color;
            }
        }
        return implode( ';', $vars );
    }

    // =========================================================================
    // JSON-LD 構築
    // =========================================================================

    private function build_standard_json_ld( array $persons, array $corporations, array $organizations ): array {
        $graph = array();

        foreach ( $persons as $p ) {
            $graph[] = $this->build_person_node( $p );
        }
        foreach ( $corporations as $c ) {
            $graph[] = $this->build_org_node( $c, 'corp' );
        }
        foreach ( $organizations as $o ) {
            $graph[] = $this->build_org_node( $o, 'org' );
        }

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        );
    }

    /**
     * Custom Mode: JSON-LD構造を組み立て
     * target_schema_id は collect_custom_data 側で sanitize 済み
     */
    private function build_custom_json_ld( array $persons, array $corporations, array $organizations, string $target_schema_id ): array {
        $graph       = array();
        $target_node = array( '@id' => $target_schema_id );

        $all_entities = array();
        foreach ( $persons as $p ) {
            $all_entities[] = array( 'node' => $this->build_person_node( $p ), 'role' => $p['role'] ?? '' );
        }
        foreach ( $corporations as $c ) {
            $all_entities[] = array( 'node' => $this->build_org_node( $c, 'corp' ), 'role' => $c['role'] ?? '' );
        }
        foreach ( $organizations as $o ) {
            $all_entities[] = array( 'node' => $this->build_org_node( $o, 'org' ), 'role' => $o['role'] ?? '' );
        }

        foreach ( $all_entities as $entry ) {
            $graph[]   = $entry['node'];
            $role_prop = $this->role_to_property( $entry['role'] );
            $ref       = array( '@id' => $entry['node']['@id'] );

            if ( ! isset( $target_node[ $role_prop ] ) ) {
                $target_node[ $role_prop ] = $ref;
            } elseif ( isset( $target_node[ $role_prop ]['@id'] ) ) {
                $target_node[ $role_prop ] = array( $target_node[ $role_prop ], $ref );
            } else {
                $target_node[ $role_prop ][] = $ref;
            }
        }

        $graph[] = $target_node;

        return array(
            '@context' => 'https://schema.org',
            '@graph'   => $graph,
        );
    }

    /**
     * URL 末尾の fragment (#anchor) を剥がしてから @id 用の base URL にする
     * これで `https://example.com/#section` + `#person-1` → 二重 fragment を防ぐ
     */
    private function build_entity_base_url( string $url ): string {
        if ( $url === '' ) {
            return home_url( '/' );
        }
        // fragment と query を除去して path レベルの URL を取得
        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return home_url( '/' );
        }
        $scheme = $parsed['scheme'] ?? 'https';
        $host   = $parsed['host'];
        $port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
        $path   = $parsed['path'] ?? '/';
        return trailingslashit( $scheme . '://' . $host . $port . $path );
    }

    /**
     * Person ノードを構築
     */
    private function build_person_node( array $p ): array {
        $base_url = $this->build_entity_base_url( (string) ( $p['url'] ?? '' ) );
        $node     = array(
            '@type' => 'Person',
            '@id'   => $base_url . '#person-' . ( (int) ( $p['id'] ?? 0 ) ),
            'name'  => (string) ( $p['name'] ?? '' ),
        );
        if ( ! empty( $p['name_en'] ) ) {
            $node['alternateName'] = (string) $p['name_en'];
        }
        if ( ! empty( $p['job_title'] ) ) {
            $node['jobTitle'] = (string) $p['job_title'];
        }
        if ( ! empty( $p['bio'] ) ) {
            $node['description'] = (string) $p['bio'];
        }
        if ( ! empty( $p['image_url'] ) ) {
            $node['image'] = array(
                '@type' => 'ImageObject',
                'url'   => (string) $p['image_url'],
            );
        }
        if ( ! empty( $p['url'] ) ) {
            $node['url'] = (string) $p['url'];
        }
        $same_as_urls = $this->extract_valid_urls( (string) ( $p['same_as'] ?? '' ) );
        if ( ! empty( $same_as_urls ) ) {
            $node['sameAs'] = $same_as_urls;
        }
        return $node;
    }

    /**
     * Organization / Corporation ノードを構築
     */
    private function build_org_node( array $entity, string $prefix ): array {
        $base_url    = $this->build_entity_base_url( (string) ( $entity['url'] ?? '' ) );
        $schema_type = $prefix === 'corp' ? 'Corporation' : 'Organization';
        $fragment    = $prefix === 'corp' ? '#corporation-' : '#organization-';
        $node        = array(
            '@type' => $schema_type,
            '@id'   => $base_url . $fragment . ( (int) ( $entity['id'] ?? 0 ) ),
            'name'  => (string) ( $entity['name'] ?? '' ),
        );
        if ( ! empty( $entity['name_en'] ) ) {
            $node['alternateName'] = (string) $entity['name_en'];
        }
        if ( ! empty( $entity['description'] ) ) {
            $node['description'] = (string) $entity['description'];
        }
        if ( ! empty( $entity['url'] ) ) {
            $node['url'] = (string) $entity['url'];
        }
        if ( ! empty( $entity['logo_url'] ) ) {
            $node['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => (string) $entity['logo_url'],
            );
        }
        $node += $this->build_org_detail_props( $entity );
        $same_as_urls = $this->extract_valid_urls( (string) ( $entity['same_as'] ?? '' ) );
        if ( ! empty( $same_as_urls ) ) {
            $node['sameAs'] = $same_as_urls;
        }
        return $node;
    }

    /**
     * 住所・連絡先・法人情報（任意項目）のプロパティ。空欄の項目は出力しない
     * 書式は Google の Organization 構造化データに合わせる
     * https://developers.google.com/search/docs/appearance/structured-data/organization
     */
    private function build_org_detail_props( array $entity ): array {
        $value = static fn( string $key ): string => trim( (string) ( $entity[ $key ] ?? '' ) );
        $props = array();

        if ( $value( 'legal_name' ) !== '' ) {
            $props['legalName'] = $value( 'legal_name' );
        }
        $email = KAPM_Database::sanitize_value( 'email', $value( 'email' ) );
        if ( $email !== '' ) {
            $props['email'] = $email;
        }
        if ( $value( 'telephone' ) !== '' ) {
            $props['telephone'] = $value( 'telephone' );
        }

        // 住所は国コード以外の項目が 1 つでもあるときだけ出力する（国コードは既定で JP が入るため）
        $address = array();
        foreach ( array( 'street_address' => 'streetAddress', 'address_locality' => 'addressLocality', 'address_region' => 'addressRegion', 'postal_code' => 'postalCode' ) as $key => $prop ) {
            if ( $value( $key ) !== '' ) {
                $address[ $prop ] = $value( $key );
            }
        }
        if ( ! empty( $address ) ) {
            $country = KAPM_Database::sanitize_value( 'country', $value( 'address_country' ) );
            if ( $country !== '' ) {
                $address['addressCountry'] = $country;
            }
            $props['address'] = array( '@type' => 'PostalAddress' ) + $address;
        }

        // 問い合わせ窓口は電話番号かメールアドレスがあるときだけ出力する
        $contact_email = KAPM_Database::sanitize_value( 'email', $value( 'contact_email' ) );
        if ( $value( 'contact_telephone' ) !== '' || $contact_email !== '' ) {
            $contact = array( '@type' => 'ContactPoint' );
            if ( $value( 'contact_type' ) !== '' ) {
                $contact['contactType'] = $value( 'contact_type' );
            }
            if ( $value( 'contact_telephone' ) !== '' ) {
                $contact['telephone'] = $value( 'contact_telephone' );
            }
            if ( $contact_email !== '' ) {
                $contact['email'] = $contact_email;
            }
            $props['contactPoint'] = $contact;
        }

        $founding_date = KAPM_Database::sanitize_value( 'date', $value( 'founding_date' ) );
        if ( $founding_date !== '' ) {
            $props['foundingDate'] = $founding_date;
        }

        // 従業員数は人数なら value、範囲なら minValue / maxValue
        $employees = KAPM_Database::parse_employees( $value( 'number_of_employees' ) );
        if ( $employees !== null ) {
            $props['numberOfEmployees'] = $employees['min'] === $employees['max']
                ? array( '@type' => 'QuantitativeValue', 'value' => $employees['min'] )
                : array( '@type' => 'QuantitativeValue', 'minValue' => $employees['min'], 'maxValue' => $employees['max'] );
        }

        foreach ( array( 'tax_id' => 'taxID', 'vat_id' => 'vatID', 'iso6523_code' => 'iso6523Code', 'duns' => 'duns', 'lei_code' => 'leiCode', 'global_location_number' => 'globalLocationNumber', 'naics' => 'naics' ) as $key => $prop ) {
            if ( $value( $key ) !== '' ) {
                $props[ $prop ] = $value( $key );
            }
        }

        return $props;
    }

    /**
     * same_as テキストを検証して有効 URL 配列を返す
     * scheme は許可リスト (デフォルト http/https) のみ通す。
     * filter_var の FILTER_VALIDATE_URL は javascript:// 等も valid と判定するため、
     * scheme を明示的に check することで JSON-LD への危険スキーム混入を防ぐ。
     * フィルタフック `kapm_same_as_protocols` で許可スキームを拡張可能。
     */
    private function extract_valid_urls( string $text ): array {
        if ( trim( $text ) === '' ) {
            return array();
        }
        $allowed_schemes = (array) apply_filters( 'kapm_same_as_protocols', array( 'http', 'https' ) );
        $allowed_schemes = array_map( 'strtolower', $allowed_schemes );
        $lines           = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
        $urls            = array();
        foreach ( $lines as $line ) {
            if ( ! filter_var( $line, FILTER_VALIDATE_URL ) ) {
                continue;
            }
            $scheme = strtolower( (string) wp_parse_url( $line, PHP_URL_SCHEME ) );
            if ( $scheme === '' || ! in_array( $scheme, $allowed_schemes, true ) ) {
                continue;
            }
            $urls[] = $line;
        }
        return array_values( array_unique( $urls ) );
    }

    /**
     * role 文字列を Schema.org プロパティ名に変換
     * apply_filters でプラグイン/テーマからの拡張を許可
     */
    private function role_to_property( string $role ): string {
        $role_lower = strtolower( trim( $role ) );
        $property   = match ( $role_lower ) {
            'author', 'writer'   => 'author',
            'publisher'          => 'publisher',
            'editor'             => 'editor',
            'reviewer'           => 'reviewedBy',
            'contributor'        => 'contributor',
            'creator'            => 'creator',
            'sponsor', 'funder'  => 'sponsor',
            'translator'         => 'translator',
            default              => 'author',
        };
        /**
         * 独自 role → Schema.org プロパティ名のマッピングを拡張可能にする
         *
         * @param string $property  既定の変換結果
         * @param string $role      オリジナルの role 値
         * @param string $role_lower 正規化後の小文字値
         */
        return (string) apply_filters( 'kapm_role_to_schema_property', $property, $role, $role_lower );
    }

    /**
     * sameAs URLからアイコン付きリンクをレンダリング
     */
    private function render_same_as_icons( string $same_as ): string {
        $urls = $this->extract_valid_urls( $same_as );
        if ( empty( $urls ) ) {
            return '';
        }
        $html = '<div class="kapm-social-icons">';
        foreach ( $urls as $url ) {
            $icon_class = $this->get_icon_class( $url );
            $host       = wp_parse_url( $url, PHP_URL_HOST );
            $name       = $host ? str_replace( 'www.', '', (string) $host ) : '';
            if ( $name !== '' ) {
                $parts = explode( '.', $name );
                $name  = ucfirst( $parts[0] );
            }
            $html .= '<a href="' . esc_url( $url ) . '" class="kapm-icon" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $name ) . '">';
            $html .= '<span class="dashicons ' . esc_attr( $icon_class ) . '" aria-hidden="true"></span>';
            $html .= '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * URLからDashiconsクラスを返す
     * apply_filters で拡張可能にする
     */
    private function get_icon_class( string $url ): string {
        // WP標準Dashiconsに存在するクラスのみ使用
        $map = array(
            'x.com'              => 'dashicons-twitter-alt',
            'twitter.com'        => 'dashicons-twitter-alt',
            'facebook.com'       => 'dashicons-facebook-alt',
            'instagram.com'      => 'dashicons-camera',
            'linkedin.com'       => 'dashicons-businessperson',
            'youtube.com'        => 'dashicons-video-alt3',
            'youtu.be'           => 'dashicons-video-alt3',
            'github.com'         => 'dashicons-editor-code',
            'gitlab.com'         => 'dashicons-editor-code',
            'bitbucket.org'      => 'dashicons-editor-code',
            'pinterest.'         => 'dashicons-admin-post',
            'tumblr.com'         => 'dashicons-share',
            'reddit.com'         => 'dashicons-format-chat',
            'medium.com'         => 'dashicons-edit',
            'note.com'           => 'dashicons-welcome-write-blog',
            'note.mu'            => 'dashicons-welcome-write-blog',
            'qiita.com'          => 'dashicons-lightbulb',
            'zenn.dev'           => 'dashicons-lightbulb',
            'tiktok.com'         => 'dashicons-format-video',
            'threads.net'        => 'dashicons-format-status',
            'threads.com'        => 'dashicons-format-status',
            'bsky.app'           => 'dashicons-share',
            'mastodon.'          => 'dashicons-share',
            'scholar.google.'    => 'dashicons-welcome-learn-more',
            'researchgate.net'   => 'dashicons-welcome-learn-more',
            'orcid.org'          => 'dashicons-welcome-learn-more',
            'amazon.'            => 'dashicons-cart',
            'spotify.com'        => 'dashicons-format-audio',
            'soundcloud.com'     => 'dashicons-format-audio',
            'vimeo.com'          => 'dashicons-video-alt2',
            'twitch.tv'          => 'dashicons-video-alt',
            'wordpress.org'      => 'dashicons-wordpress',
            'wordpress.com'      => 'dashicons-wordpress',
            'wikipedia.org'      => 'dashicons-book',
            'goodreads.com'      => 'dashicons-book-alt',
            'flickr.com'         => 'dashicons-camera',
            'telegram.org'       => 'dashicons-email-alt',
            't.me'               => 'dashicons-email-alt',
            'whatsapp.com'       => 'dashicons-phone',
            'wa.me'              => 'dashicons-phone',
            'gravatar.com'       => 'dashicons-admin-users',
            'stackoverflow.com'  => 'dashicons-editor-help',
            'patreon.com'        => 'dashicons-money-alt',
            'osf.io'             => 'dashicons-welcome-learn-more',
            'ideas.repec.org'    => 'dashicons-welcome-learn-more',
        );
        /**
         * sameAs ドメインマッピングを外部から拡張可能にする
         *
         * @param array  $map 既定のドメイン→dashicons クラスマップ
         */
        $map = (array) apply_filters( 'kapm_same_as_icon_map', $map );

        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return 'dashicons-admin-site';
        }
        $host = strtolower( str_replace( 'www.', '', (string) $host ) );

        // 完全一致
        if ( isset( $map[ $host ] ) ) {
            return (string) $map[ $host ];
        }

        // ワイルドカードマッチ（末尾ドット = サブドメイン対応）
        foreach ( $map as $domain => $icon ) {
            if ( is_string( $domain ) && str_ends_with( $domain, '.' ) && str_starts_with( $host, rtrim( $domain, '.' ) ) ) {
                return (string) $icon;
            }
        }

        return 'dashicons-admin-site';
    }
}
