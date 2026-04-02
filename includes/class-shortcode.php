<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KAPM_Shortcode {

    private static array $custom_mode_data = array();

    public function __construct() {
        add_shortcode( 'author_panel', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'template_redirect', array( $this, 'preparse_custom_mode' ) );
        add_action( 'wp_head', array( $this, 'output_custom_json_ld' ), 99 );
    }

    public function enqueue_styles(): void {
        wp_register_style(
            'kapm-panel-style',
            KAPM_PLUGIN_URL . 'public/css/panel-style.css',
            array( 'dashicons' ),
            KAPM_VERSION . '.' . filemtime( KAPM_PLUGIN_PATH . 'public/css/panel-style.css' )
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
     */
    private function scan_blocks_for_custom( array $blocks ): void {
        foreach ( $blocks as $block ) {
            if ( $block['blockName'] === 'kapm/author-panel' ) {
                $a = $block['attrs'] ?? array();
                if ( ( $a['mode'] ?? 'standard' ) === 'custom' && ! empty( $a['targetSchemaId'] ) ) {
                    $this->collect_custom_data(
                        $a['persons'] ?? '',
                        $a['corporations'] ?? '',
                        $a['organizations'] ?? '',
                        $a['targetSchemaId']
                    );
                }
            }
            if ( ! empty( $block['innerBlocks'] ) ) {
                $this->scan_blocks_for_custom( $block['innerBlocks'] );
            }
        }
    }

    /**
     * customモードデータを収集
     */
    private function collect_custom_data( string $persons_str, string $corps_str, string $orgs_str, string $target_schema_id ): void {
        $persons       = $this->fetch_entities( $this->parse_ids( $persons_str ), 'person' );
        $corporations  = $this->fetch_entities( $this->parse_ids( $corps_str ), 'corporation' );
        $organizations = $this->fetch_entities( $this->parse_ids( $orgs_str ), 'organization' );

        if ( empty( $persons ) && empty( $corporations ) && empty( $organizations ) ) {
            return;
        }

        self::$custom_mode_data[] = array(
            'target_schema_id' => $target_schema_id,
            'persons'          => $persons,
            'corporations'     => $corporations,
            'organizations'    => $organizations,
        );
    }

    /**
     * wp_head で custom モードの JSON-LD を出力
     */
    public function output_custom_json_ld(): void {
        if ( empty( self::$custom_mode_data ) ) {
            return;
        }

        foreach ( self::$custom_mode_data as $data ) {
            $json_ld = $this->build_custom_json_ld(
                $data['persons'],
                $data['corporations'],
                $data['organizations'],
                $data['target_schema_id']
            );
            echo '<!-- Kashiwazaki SEO Author Panel Manager - Custom Mode JSON-LD -->' . "\n";
            echo '<script type="application/ld+json">' . "\n";
            echo wp_json_encode( $json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
            echo "\n" . '</script>' . "\n";
            echo '<!-- / Kashiwazaki SEO Author Panel Manager -->' . "\n";
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

        $labels = json_decode( $atts['labels'], true ) ?: array();
        $html = $this->build_html( $persons, $corporations, $organizations, $labels );

        if ( $atts['mode'] === 'standard' ) {
            $json_ld = $this->build_standard_json_ld( $persons, $corporations, $organizations );
            $html .= "\n" . '<!-- Kashiwazaki SEO Author Panel Manager - Standard Mode JSON-LD -->' . "\n";
            $html .= '<script type="application/ld+json">' . "\n" . wp_json_encode( $json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . "\n" . '</script>' . "\n";
            $html .= '<!-- / Kashiwazaki SEO Author Panel Manager -->' . "\n";
        }
        // custom モードの JSON-LD は wp_head で出力済み

        return $html;
    }

    private function parse_ids( string $str ): array {
        if ( empty( trim( $str ) ) ) {
            return array();
        }
        return array_filter( array_map( 'absint', explode( ',', $str ) ) );
    }

    private function fetch_entities( array $ids, string $type ): array {
        $results = array();
        foreach ( $ids as $id ) {
            $entity = null;
            switch ( $type ) {
                case 'person':
                    $entity = KAPM_Database::get_person( $id );
                    break;
                case 'corporation':
                    $entity = KAPM_Database::get_corporation( $id );
                    break;
                case 'organization':
                    $entity = KAPM_Database::get_organization( $id );
                    break;
            }
            if ( $entity ) {
                $entity['_type'] = $type;
                $results[] = $entity;
            }
        }
        return $results;
    }

    private function build_html( array $persons, array $corporations, array $organizations, array $labels = array() ): string {
        ob_start();
        ?>
        <div class="kapm-author-panel">
        <?php foreach ( $persons as $p ) : ?>
            <div class="kapm-author-card kapm-person kapm-style-<?php echo esc_attr( $p['panel_style'] ?? 'default' ); ?>">
                <div class="kapm-author-image-wrap">
                    <?php if ( ! empty( $p['image_url'] ) ) : ?>
                        <div class="kapm-author-image">
                            <img src="<?php echo esc_url( $p['image_url'] ); ?>" alt="<?php echo esc_attr( $p['name'] ); ?>" width="80" height="80" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <?php $lkey = 'person-' . $p['id']; if ( ! empty( $labels[ $lkey ] ) ) : ?>
                        <div class="kapm-author-label"><?php echo esc_html( $labels[ $lkey ] ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="kapm-author-info">
                    <div class="kapm-author-name">
                        <?php if ( ! empty( $p['url'] ) ) : ?>
                            <a href="<?php echo esc_url( $p['url'] ); ?>" rel="author"><?php echo esc_html( $p['name'] ); ?></a>
                        <?php else : ?>
                            <?php echo esc_html( $p['name'] ); ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $p['name_en'] ) ) : ?>
                            <span class="kapm-name-en">(<?php echo esc_html( $p['name_en'] ); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $p['job_title'] ) ) : ?>
                        <div class="kapm-author-job"><?php echo esc_html( $p['job_title'] ); ?></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $p['bio'] ) ) : ?>
                        <div class="kapm-author-bio"><?php echo esc_html( $p['bio'] ); ?></div>
                    <?php endif; ?>
                    <?php echo $this->render_same_as_icons( $p['same_as'] ?? '' ); ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ( $corporations as $c ) : ?>
            <div class="kapm-author-card kapm-corporation kapm-style-<?php echo esc_attr( $c['panel_style'] ?? 'default' ); ?>">
                <div class="kapm-author-image-wrap">
                    <?php if ( ! empty( $c['logo_url'] ) ) : ?>
                        <div class="kapm-author-image">
                            <img src="<?php echo esc_url( $c['logo_url'] ); ?>" alt="<?php echo esc_attr( $c['name'] ); ?>" width="80" height="80" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <?php $lkey = 'corp-' . $c['id']; if ( ! empty( $labels[ $lkey ] ) ) : ?>
                        <div class="kapm-author-label"><?php echo esc_html( $labels[ $lkey ] ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="kapm-author-info">
                    <div class="kapm-author-name">
                        <?php if ( ! empty( $c['url'] ) ) : ?>
                            <a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['name'] ); ?></a>
                        <?php else : ?>
                            <?php echo esc_html( $c['name'] ); ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $c['name_en'] ) ) : ?>
                            <span class="kapm-name-en">(<?php echo esc_html( $c['name_en'] ); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $c['description'] ) ) : ?>
                        <div class="kapm-author-bio"><?php echo esc_html( $c['description'] ); ?></div>
                    <?php endif; ?>
                    <?php echo $this->render_same_as_icons( $c['same_as'] ?? '' ); ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php foreach ( $organizations as $o ) : ?>
            <div class="kapm-author-card kapm-organization kapm-style-<?php echo esc_attr( $o['panel_style'] ?? 'default' ); ?>">
                <div class="kapm-author-image-wrap">
                    <?php if ( ! empty( $o['logo_url'] ) ) : ?>
                        <div class="kapm-author-image">
                            <img src="<?php echo esc_url( $o['logo_url'] ); ?>" alt="<?php echo esc_attr( $o['name'] ); ?>" width="80" height="80" loading="lazy">
                        </div>
                    <?php endif; ?>
                    <?php $lkey = 'org-' . $o['id']; if ( ! empty( $labels[ $lkey ] ) ) : ?>
                        <div class="kapm-author-label"><?php echo esc_html( $labels[ $lkey ] ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="kapm-author-info">
                    <div class="kapm-author-name">
                        <?php if ( ! empty( $o['url'] ) ) : ?>
                            <a href="<?php echo esc_url( $o['url'] ); ?>"><?php echo esc_html( $o['name'] ); ?></a>
                        <?php else : ?>
                            <?php echo esc_html( $o['name'] ); ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $o['name_en'] ) ) : ?>
                            <span class="kapm-name-en">(<?php echo esc_html( $o['name_en'] ); ?>)</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $o['description'] ) ) : ?>
                        <div class="kapm-author-bio"><?php echo esc_html( $o['description'] ); ?></div>
                    <?php endif; ?>
                    <?php echo $this->render_same_as_icons( $o['same_as'] ?? '' ); ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

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
     * target_schema_id のノードに author/publisher プロパティを紐付け
     */
    private function build_custom_json_ld( array $persons, array $corporations, array $organizations, string $target_schema_id ): array {
        $graph = array();
        $target_node = array(
            '@id' => $target_schema_id,
        );

        $all_entities = array();
        foreach ( $persons as $p ) {
            $all_entities[] = array( 'node' => $this->build_person_node( $p ), 'role' => $p['role'] );
        }
        foreach ( $corporations as $c ) {
            $all_entities[] = array( 'node' => $this->build_org_node( $c, 'corp' ), 'role' => $c['role'] );
        }
        foreach ( $organizations as $o ) {
            $all_entities[] = array( 'node' => $this->build_org_node( $o, 'org' ), 'role' => $o['role'] );
        }

        foreach ( $all_entities as $entry ) {
            $graph[] = $entry['node'];
            $role_prop = $this->role_to_property( $entry['role'] );
            $ref = array( '@id' => $entry['node']['@id'] );

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
     * Person ノードを構築
     */
    private function build_person_node( array $p ): array {
        $base_url = ! empty( $p['url'] ) ? $p['url'] : home_url( '/' );
        $node = array(
            '@type' => 'Person',
            '@id'   => trailingslashit( $base_url ) . '#person-' . $p['id'],
            'name'  => $p['name'],
        );
        if ( ! empty( $p['name_en'] ) ) {
            $node['alternateName'] = $p['name_en'];
        }
        if ( ! empty( $p['job_title'] ) ) {
            $node['jobTitle'] = $p['job_title'];
        }
        if ( ! empty( $p['bio'] ) ) {
            $node['description'] = $p['bio'];
        }
        if ( ! empty( $p['image_url'] ) ) {
            $node['image'] = array(
                '@type' => 'ImageObject',
                'url'   => $p['image_url'],
            );
        }
        if ( ! empty( $p['url'] ) ) {
            $node['url'] = $p['url'];
        }
        if ( ! empty( $p['same_as'] ) ) {
            $lines = array_filter( array_map( 'trim', explode( "\n", $p['same_as'] ) ) );
            $same_as_urls = array();
            foreach ( $lines as $line ) {
                if ( filter_var( $line, FILTER_VALIDATE_URL ) ) {
                    $same_as_urls[] = $line;
                }
            }
            if ( ! empty( $same_as_urls ) ) {
                $node['sameAs'] = $same_as_urls;
            }
        }
        return $node;
    }

    /**
     * Organization / Corporation ノードを構築
     */
    private function build_org_node( array $entity, string $prefix ): array {
        $base_url = ! empty( $entity['url'] ) ? $entity['url'] : home_url( '/' );
        $schema_type = $prefix === 'corp' ? 'Corporation' : 'Organization';
        $fragment    = $prefix === 'corp' ? '#corporation-' : '#organization-';
        $node = array(
            '@type' => $schema_type,
            '@id'   => trailingslashit( $base_url ) . $fragment . $entity['id'],
            'name'  => $entity['name'],
        );
        if ( ! empty( $entity['name_en'] ) ) {
            $node['alternateName'] = $entity['name_en'];
        }
        if ( ! empty( $entity['description'] ) ) {
            $node['description'] = $entity['description'];
        }
        if ( ! empty( $entity['url'] ) ) {
            $node['url'] = $entity['url'];
        }
        if ( ! empty( $entity['logo_url'] ) ) {
            $node['logo'] = array(
                '@type' => 'ImageObject',
                'url'   => $entity['logo_url'],
            );
        }
        if ( ! empty( $entity['same_as'] ) ) {
            $lines = array_filter( array_map( 'trim', explode( "\n", $entity['same_as'] ) ) );
            $same_as_urls = array();
            foreach ( $lines as $line ) {
                if ( filter_var( $line, FILTER_VALIDATE_URL ) ) {
                    $same_as_urls[] = $line;
                }
            }
            if ( ! empty( $same_as_urls ) ) {
                $node['sameAs'] = $same_as_urls;
            }
        }
        return $node;
    }

    /**
     * role 文字列を Schema.org プロパティ名に変換
     */
    private function role_to_property( string $role ): string {
        $role_lower = strtolower( trim( $role ) );
        return match ( $role_lower ) {
            'author', 'writer'       => 'author',
            'publisher'              => 'publisher',
            'editor'                 => 'editor',
            'reviewer'               => 'reviewedBy',
            'contributor'            => 'contributor',
            'creator'                => 'creator',
            'sponsor', 'funder'      => 'sponsor',
            'translator'             => 'translator',
            default                  => 'author',
        };
    }

    /**
     * sameAs URLからアイコン付きリンクをレンダリング
     */
    private function render_same_as_icons( string $same_as ): string {
        if ( empty( trim( $same_as ) ) ) {
            return '';
        }
        $lines = array_filter( array_map( 'trim', explode( "\n", $same_as ) ) );
        if ( empty( $lines ) ) {
            return '';
        }
        $html = '<div class="kapm-social-icons">';
        foreach ( $lines as $url ) {
            if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                continue;
            }
            $icon_class = $this->get_icon_class( $url );
            $host = wp_parse_url( $url, PHP_URL_HOST );
            $name = $host ? str_replace( 'www.', '', $host ) : '';
            $name = $name ? ucfirst( explode( '.', $name )[0] ?? '' ) : '';
            $html .= '<a href="' . esc_url( $url ) . '" class="kapm-icon" target="_blank" rel="noopener noreferrer" title="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $name ) . '">';
            $html .= '<span class="dashicons ' . esc_attr( $icon_class ) . '" aria-hidden="true"></span>';
            $html .= '</a>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * URLからDashiconsクラスを返す
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

        $host = wp_parse_url( $url, PHP_URL_HOST );
        if ( ! $host ) {
            return 'dashicons-admin-site';
        }
        $host = strtolower( str_replace( 'www.', '', $host ) );

        // 完全一致
        if ( isset( $map[ $host ] ) ) {
            return $map[ $host ];
        }

        // ワイルドカードマッチ（末尾ドット = サブドメイン対応）
        foreach ( $map as $domain => $icon ) {
            if ( str_ends_with( $domain, '.' ) && str_starts_with( $host, rtrim( $domain, '.' ) ) ) {
                return $icon;
            }
        }

        return 'dashicons-admin-site';
    }
}
