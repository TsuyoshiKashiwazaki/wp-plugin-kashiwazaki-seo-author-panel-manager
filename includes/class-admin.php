<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KAPM_Admin {

    /**
     * add_menu_page() が返すフックサフィックス。$hook 比較に使用
     */
    private string $hook_suffix = '';

    /**
     * admin_notices で出力するため、保存/削除処理中にメッセージを蓄積する
     */
    private array $pending_notices = array();

    /**
     * エンティティ種別ごとの設定
     */
    private static function get_entity_config( string $type ): ?array {
        static $configs = null;
        if ( $configs === null ) {
            $configs = array(
                'person' => array(
                    'tab'          => 'person',
                    'label'        => 'Person',
                    'nonce_save'   => 'kapm_save_person',
                    'nonce_field'  => 'kapm_person_nonce',
                    'nonce_delete' => 'kapm_delete_person',
                    'form_view'    => 'person-form.php',
                    'list_view'    => 'person-list.php',
                    'usage_type'   => 'persons',
                ),
                'corporation' => array(
                    'tab'          => 'corporation',
                    'label'        => 'Corporation',
                    'nonce_save'   => 'kapm_save_corporation',
                    'nonce_field'  => 'kapm_corporation_nonce',
                    'nonce_delete' => 'kapm_delete_corporation',
                    'form_view'    => 'corporation-form.php',
                    'list_view'    => 'corporation-list.php',
                    'usage_type'   => 'corporations',
                ),
                'organization' => array(
                    'tab'          => 'organization',
                    'label'        => 'Organization',
                    'nonce_save'   => 'kapm_save_organization',
                    'nonce_field'  => 'kapm_organization_nonce',
                    'nonce_delete' => 'kapm_delete_organization',
                    'form_view'    => 'organization-form.php',
                    'list_view'    => 'organization-list.php',
                    'usage_type'   => 'organizations',
                ),
            );
        }
        return $configs[ $type ] ?? null;
    }

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_notices', array( $this, 'render_pending_notices' ) );
    }

    /**
     * $_GET['page'] 直接参照をやめて $hook_suffix で判定
     */
    public function enqueue_styles( string $hook ): void {
        if ( $hook !== $this->hook_suffix || $this->hook_suffix === '' ) {
            return;
        }

        $css_path = KAPM_PLUGIN_PATH . 'admin/css/admin-style.css';
        $css_ver  = KAPM_VERSION;
        if ( file_exists( $css_path ) ) {
            $css_ver .= '.' . filemtime( $css_path );
        }
        wp_enqueue_style(
            'kapm-admin-style',
            KAPM_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            $css_ver
        );

        wp_enqueue_media();

        $js_path = KAPM_PLUGIN_PATH . 'admin/js/admin-media.js';
        $js_ver  = KAPM_VERSION;
        if ( file_exists( $js_path ) ) {
            $js_ver .= '.' . filemtime( $js_path );
        }
        wp_enqueue_script(
            'kapm-admin-media',
            KAPM_PLUGIN_URL . 'admin/js/admin-media.js',
            array( 'jquery' ),
            $js_ver,
            true
        );
    }

    public function add_menus(): void {
        $this->hook_suffix = (string) add_menu_page(
            __( 'Kashiwazaki SEO Author Panel Manager', 'kashiwazaki-seo-author-panel-manager' ),
            __( 'Kashiwazaki SEO Author Panel Manager', 'kashiwazaki-seo-author-panel-manager' ),
            'manage_options',
            'kapm',
            array( $this, 'render_page' ),
            'dashicons-id-alt',
            81
        );
    }

    /**
     * タブ付き統合管理画面
     */
    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'person';
        if ( ! in_array( $tab, array( 'person', 'corporation', 'organization' ), true ) ) {
            $tab = 'person';
        }

        $base_url = admin_url( 'admin.php?page=kapm' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Kashiwazaki SEO Author Panel Manager', 'kashiwazaki-seo-author-panel-manager' ); ?></h1>
            <nav class="nav-tab-wrapper">
                <a href="<?php echo esc_url( $base_url . '&tab=person' ); ?>" class="nav-tab <?php echo $tab === 'person' ? 'nav-tab-active' : ''; ?>">Person</a>
                <a href="<?php echo esc_url( $base_url . '&tab=corporation' ); ?>" class="nav-tab <?php echo $tab === 'corporation' ? 'nav-tab-active' : ''; ?>">Corporation</a>
                <a href="<?php echo esc_url( $base_url . '&tab=organization' ); ?>" class="nav-tab <?php echo $tab === 'organization' ? 'nav-tab-active' : ''; ?>">Organization</a>
            </nav>
            <div class="kapm-tab-content">
        <?php
        $this->handle_entity( $tab );
        ?>
            </div>
        </div>
        <?php
    }

    /**
     * admin_notices 経由でメッセージを出力
     * render_page() は render() より後に走るため、pending_notices を保留しておく
     */
    public function render_pending_notices(): void {
        foreach ( $this->pending_notices as $notice ) {
            $class   = esc_attr( $notice['type'] );
            $message = esc_html( $notice['message'] );
            echo "<div class=\"notice {$class} is-dismissible\"><p>{$message}</p></div>";
        }
        $this->pending_notices = array();
    }

    /**
     * 即時 echo 版（tab コンテンツ内で notice を出す必要がある場合用）
     */
    private function echo_notice( string $type, string $message ): void {
        $class = esc_attr( $type );
        echo "<div class=\"notice {$class}\"><p>" . esc_html( $message ) . "</p></div>";
    }

    /**
     * 3 つの handle_*() メソッドを統合
     *
     * @param string $type 'person' / 'corporation' / 'organization'
     */
    private function handle_entity( string $type ): void {
        $config = self::get_entity_config( $type );
        if ( $config === null ) {
            return;
        }

        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

        // 削除
        if ( $action === 'delete' && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), $config['nonce_delete'] ) ) {
                KAPM_Database::delete_entity( $type, absint( $_GET['id'] ) );
                $this->echo_notice( 'notice-success', __( '削除しました。', 'kashiwazaki-seo-author-panel-manager' ) );
            }
            $action = 'list';
        }

        // 保存（新規追加・更新）
        if ( isset( $_POST[ $config['nonce_field'] ] ) && wp_verify_nonce( sanitize_key( $_POST[ $config['nonce_field'] ] ), $config['nonce_save'] ) ) {
            $data = $this->collect_post_data( $type );

            if ( ! empty( $_POST['id'] ) ) {
                KAPM_Database::update_entity( $type, absint( $_POST['id'] ), $data );
                $this->echo_notice( 'notice-success', __( '更新しました。', 'kashiwazaki-seo-author-panel-manager' ) );
            } else {
                KAPM_Database::insert_entity( $type, $data );
                $this->echo_notice( 'notice-success', __( '追加しました。', 'kashiwazaki-seo-author-panel-manager' ) );
            }
            $action = 'list';
        }

        // ビュー描画
        if ( $action === 'add' || $action === 'edit' ) {
            $item = null;
            if ( $action === 'edit' && isset( $_GET['id'] ) ) {
                $item = KAPM_Database::get_entity( $type, absint( $_GET['id'] ) );
            }
            // entity_type と config を view 側に渡す
            $entity_type = $type;
            include KAPM_PLUGIN_PATH . 'admin/views/' . $config['form_view'];
        } else {
            $items = KAPM_Database::get_entities( $type );
            include KAPM_PLUGIN_PATH . 'admin/views/' . $config['list_view'];
        }
    }

    /**
     * エンティティ種別ごとのフィールドから POST を抽出
     */
    private function collect_post_data( string $type ): array {
        // Person は job_title/bio/image_url、Corp/Org は description/logo_url を使う
        if ( $type === 'person' ) {
            return array(
                'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
                'name_en'     => isset( $_POST['name_en'] ) ? sanitize_text_field( wp_unslash( $_POST['name_en'] ) ) : '',
                'role'        => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'Author',
                'job_title'   => isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '',
                'bio'         => isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '',
                'image_url'   => isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '',
                'url'         => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
                'same_as'     => isset( $_POST['same_as'] ) ? sanitize_textarea_field( wp_unslash( $_POST['same_as'] ) ) : '',
                'panel_style' => isset( $_POST['panel_style'] ) ? sanitize_text_field( wp_unslash( $_POST['panel_style'] ) ) : 'default',
            );
        }

        // corporation / organization
        return array(
            'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'name_en'     => isset( $_POST['name_en'] ) ? sanitize_text_field( wp_unslash( $_POST['name_en'] ) ) : '',
            'role'        => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'Publisher',
            'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'url'         => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
            'logo_url'    => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
            'same_as'     => isset( $_POST['same_as'] ) ? sanitize_textarea_field( wp_unslash( $_POST['same_as'] ) ) : '',
            'panel_style' => isset( $_POST['panel_style'] ) ? sanitize_text_field( wp_unslash( $_POST['panel_style'] ) ) : 'default',
        );
    }
}
