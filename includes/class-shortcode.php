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
     * wp_json_encode のフラグ (defense-in-depth):
     * - JSON_UNESCAPED_UNICODE: 日本語をそのまま出力
     * - JSON_HEX_TAG: < / > を \u003c / \u003e にエスケープ (HTML コンテキストで </script> 終了を防ぐ)
     * - JSON_HEX_AMP / JSON_HEX_APOS / JSON_HEX_QUOT: 追加の HTML 安全性
     * - JSON_PRETTY_PRINT: 可読性
     * ※ JSON_UNESCAPED_SLASHES は意図的に使用しない（S1 XSS 修正）
     */
    private const JSON_ENCODE_FLAGS = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT;

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

        $html = $this->build_html( $persons, $corporations, $organizations, $labels );

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

    private function build_html( array $persons, array $corporations, array $organizations, array $labels = array() ): string {
        ob_start();
        echo '<div class="kapm-author-panel">';
        foreach ( $persons as $p ) {
            echo $this->build_entity_card( 'person', $p, $labels );
        }
        foreach ( $corporations as $c ) {
            echo $this->build_entity_card( 'corporation', $c, $labels );
        }
        foreach ( $organizations as $o ) {
            echo $this->build_entity_card( 'organization', $o, $labels );
        }
        echo '</div>';
        return ob_get_clean();
    }

    /**
     * 1 エンティティのカード HTML を構築
     * 全フィールドは esc_html / esc_attr / esc_url で個別にエスケープ
     */
    private function build_entity_card( string $type, array $entity, array $labels ): string {
        $type_class = $type === 'person' ? 'kapm-person' : ( $type === 'corporation' ? 'kapm-corporation' : 'kapm-organization' );
        $label_key_prefix = $type === 'person' ? 'person' : ( $type === 'corporation' ? 'corp' : 'org' );
        $label_key  = $label_key_prefix . '-' . ( $entity['id'] ?? 0 );
        $label_text = $labels[ $label_key ] ?? '';

        // Person は image_url と bio、Corp/Org は logo_url と description を使う
        $image_url   = $type === 'person' ? ( $entity['image_url'] ?? '' ) : ( $entity['logo_url'] ?? '' );
        $description = $type === 'person' ? ( $entity['bio'] ?? '' ) : ( $entity['description'] ?? '' );
        $job_title   = $type === 'person' ? ( $entity['job_title'] ?? '' ) : '';

        $panel_style = $entity['panel_style'] ?? 'default';
        $name        = $entity['name'] ?? '';
        $name_en     = $entity['name_en'] ?? '';
        $url         = $entity['url'] ?? '';

        ob_start();
        ?>
        <div class="kapm-author-card <?php echo esc_attr( $type_class ); ?> kapm-style-<?php echo esc_attr( $panel_style ); ?>">
            <div class="kapm-author-image-wrap">
                <?php if ( $image_url !== '' ) : ?>
                    <div class="kapm-author-image">
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $name ); ?>" width="80" height="80" loading="lazy">
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
                </div>
                <?php if ( $job_title !== '' ) : ?>
                    <div class="kapm-author-job"><?php echo esc_html( $job_title ); ?></div>
                <?php endif; ?>
                <?php if ( $description !== '' ) : ?>
                    <div class="kapm-author-bio"><?php echo esc_html( $description ); ?></div>
                <?php endif; ?>
                <?php echo $this->render_same_as_icons( $entity['same_as'] ?? '' ); ?>
            </div>
        </div>
        <?php
        return (string) ob_get_clean();
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
        $same_as_urls = $this->extract_valid_urls( (string) ( $entity['same_as'] ?? '' ) );
        if ( ! empty( $same_as_urls ) ) {
            $node['sameAs'] = $same_as_urls;
        }
        return $node;
    }

    /**
     * same_as テキストを FILTER_VALIDATE_URL で検証して有効 URL 配列を返す
     */
    private function extract_valid_urls( string $text ): array {
        if ( trim( $text ) === '' ) {
            return array();
        }
        $lines = array_filter( array_map( 'trim', explode( "\n", $text ) ) );
        $urls  = array();
        foreach ( $lines as $line ) {
            if ( filter_var( $line, FILTER_VALIDATE_URL ) ) {
                $urls[] = $line;
            }
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
