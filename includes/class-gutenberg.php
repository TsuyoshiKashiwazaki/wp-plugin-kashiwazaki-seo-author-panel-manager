<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KAPM_Gutenberg {

    /**
     * ブロックエディタで返す必要最小限のフィールド
     * Person の bio / url / image_url / job_title、Corp/Org の description / logo_url / same_as 等の
     * 個人情報寄りフィールドは REST レスポンスから除外する。
     */
    private const REST_PUBLIC_FIELDS = array( 'id', 'name', 'name_en', 'role' );

    public function __construct() {
        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    public function register_block(): void {
        register_block_type( 'kapm/author-panel', array(
            'editor_script'   => 'kapm-editor-script',
            'render_callback' => array( $this, 'render_block' ),
            'attributes'      => array(
                'persons'        => array( 'type' => 'string', 'default' => '' ),
                'corporations'   => array( 'type' => 'string', 'default' => '' ),
                'organizations'  => array( 'type' => 'string', 'default' => '' ),
                'mode'           => array( 'type' => 'string', 'default' => 'standard' ),
                'targetSchemaId' => array( 'type' => 'string', 'default' => '' ),
                'labels'         => array( 'type' => 'string', 'default' => '{}' ),
            ),
        ) );
    }

    public function enqueue_editor_assets(): void {
        $js_file = KAPM_PLUGIN_PATH . 'assets/js/gutenberg-sidebar.js';
        if ( ! file_exists( $js_file ) ) {
            return;
        }

        $data_js = 'var kapmData = ' . wp_json_encode( array(
            'restUrl'  => rest_url( 'kapm/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'adminUrl' => admin_url( 'admin.php?page=kapm' ),
        ) ) . ';';

        $block_js = file_get_contents( $js_file );
        if ( $block_js === false ) {
            return;
        }

        wp_register_script( 'kapm-editor-script', false, array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-components', 'wp-data' ), KAPM_VERSION );
        wp_add_inline_script( 'kapm-editor-script', $data_js . "\n" . $block_js );
        wp_enqueue_script( 'kapm-editor-script' );
    }

    public function render_block( array $attributes ): string {
        global $kapm_shortcode;
        return $kapm_shortcode->render( array(
            'persons'          => $attributes['persons'] ?? '',
            'corporations'     => $attributes['corporations'] ?? '',
            'organizations'    => $attributes['organizations'] ?? '',
            'mode'             => $attributes['mode'] ?? 'standard',
            'target_schema_id' => $attributes['targetSchemaId'] ?? '',
            'labels'           => $attributes['labels'] ?? '{}',
        ) );
    }

    /**
     * REST ルートをエンティティ種別ごとにループ登録）
     * permission_callback は edit_posts 維持だが、返却フィールドを最小化して情報漏洩を防ぐ
     */
    public function register_rest_routes(): void {
        $routes = array(
            'persons'       => 'person',
            'corporations'  => 'corporation',
            'organizations' => 'organization',
        );

        foreach ( $routes as $route => $type ) {
            register_rest_route( 'kapm/v1', '/' . $route, array(
                'methods'             => 'GET',
                'callback'            => function () use ( $type ) {
                    $rows     = KAPM_Database::get_entities( $type );
                    $filtered = array_map( array( $this, 'filter_rest_fields' ), $rows );
                    return new \WP_REST_Response( $filtered, 200 );
                },
                'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
            ) );
        }
    }

    /**
     * REST レスポンス用に安全なフィールドのみ抽出
     */
    private function filter_rest_fields( array $entity ): array {
        $filtered = array();
        foreach ( self::REST_PUBLIC_FIELDS as $field ) {
            if ( array_key_exists( $field, $entity ) ) {
                $filtered[ $field ] = $entity[ $field ];
            }
        }
        return $filtered;
    }
}
