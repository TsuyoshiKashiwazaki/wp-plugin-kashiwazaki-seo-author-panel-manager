<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KAPM_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
    }

    public function enqueue_styles( string $hook ): void {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'kapm' ) {
            return;
        }
        wp_enqueue_style(
            'kapm-admin-style',
            KAPM_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            KAPM_VERSION . '.' . filemtime( KAPM_PLUGIN_PATH . 'admin/css/admin-style.css' )
        );
        wp_enqueue_media();
        wp_enqueue_script(
            'kapm-admin-media',
            KAPM_PLUGIN_URL . 'admin/js/admin-media.js',
            array( 'jquery' ),
            KAPM_VERSION . '.' . filemtime( KAPM_PLUGIN_PATH . 'admin/js/admin-media.js' ),
            true
        );
    }

    public function add_menus(): void {
        add_menu_page(
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
        switch ( $tab ) {
            case 'corporation':
                $this->handle_corporation();
                break;
            case 'organization':
                $this->handle_organization();
                break;
            default:
                $this->handle_person();
                break;
        }
        ?>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    // Person
    // =========================================================================

    private function handle_person(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

        if ( $action === 'delete' && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'kapm_delete_person' ) ) {
                KAPM_Database::delete_person( absint( $_GET['id'] ) );
                echo '<div class="notice notice-success"><p>' . esc_html__( '削除しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            }
            $action = 'list';
        }

        if ( isset( $_POST['kapm_person_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['kapm_person_nonce'] ), 'kapm_save_person' ) ) {
            $data = array(
                'name'      => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
                'name_en'   => isset( $_POST['name_en'] ) ? sanitize_text_field( wp_unslash( $_POST['name_en'] ) ) : '',
                'role'      => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'Author',
                'job_title' => isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '',
                'bio'       => isset( $_POST['bio'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bio'] ) ) : '',
                'image_url' => isset( $_POST['image_url'] ) ? esc_url_raw( wp_unslash( $_POST['image_url'] ) ) : '',
                'url'       => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
                'same_as'     => isset( $_POST['same_as'] ) ? sanitize_textarea_field( wp_unslash( $_POST['same_as'] ) ) : '',
                'panel_style' => isset( $_POST['panel_style'] ) ? sanitize_text_field( wp_unslash( $_POST['panel_style'] ) ) : 'default',
            );

            if ( ! empty( $_POST['id'] ) ) {
                KAPM_Database::update_person( absint( $_POST['id'] ), $data );
                echo '<div class="notice notice-success"><p>' . esc_html__( '更新しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            } else {
                KAPM_Database::insert_person( $data );
                echo '<div class="notice notice-success"><p>' . esc_html__( '追加しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            }
            $action = 'list';
        }

        if ( $action === 'add' || $action === 'edit' ) {
            $item = null;
            if ( $action === 'edit' && isset( $_GET['id'] ) ) {
                $item = KAPM_Database::get_person( absint( $_GET['id'] ) );
            }
            include KAPM_PLUGIN_PATH . 'admin/views/person-form.php';
        } else {
            $items = KAPM_Database::get_persons();
            include KAPM_PLUGIN_PATH . 'admin/views/person-list.php';
        }
    }

    // =========================================================================
    // Corporation
    // =========================================================================

    private function handle_corporation(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

        if ( $action === 'delete' && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'kapm_delete_corporation' ) ) {
                KAPM_Database::delete_corporation( absint( $_GET['id'] ) );
                echo '<div class="notice notice-success"><p>' . esc_html__( '削除しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            }
            $action = 'list';
        }

        if ( isset( $_POST['kapm_corporation_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['kapm_corporation_nonce'] ), 'kapm_save_corporation' ) ) {
            $data = array(
                'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
                'name_en'  => isset( $_POST['name_en'] ) ? sanitize_text_field( wp_unslash( $_POST['name_en'] ) ) : '',
                'role'        => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'Publisher',
                'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
                'url'         => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
                'logo_url'    => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
                'same_as'     => isset( $_POST['same_as'] ) ? sanitize_textarea_field( wp_unslash( $_POST['same_as'] ) ) : '',
                'panel_style' => isset( $_POST['panel_style'] ) ? sanitize_text_field( wp_unslash( $_POST['panel_style'] ) ) : 'default',
            );

            if ( ! empty( $_POST['id'] ) ) {
                KAPM_Database::update_corporation( absint( $_POST['id'] ), $data );
                echo '<div class="notice notice-success"><p>' . esc_html__( '更新しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            } else {
                KAPM_Database::insert_corporation( $data );
                echo '<div class="notice notice-success"><p>' . esc_html__( '追加しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            }
            $action = 'list';
        }

        if ( $action === 'add' || $action === 'edit' ) {
            $item = null;
            if ( $action === 'edit' && isset( $_GET['id'] ) ) {
                $item = KAPM_Database::get_corporation( absint( $_GET['id'] ) );
            }
            include KAPM_PLUGIN_PATH . 'admin/views/corporation-form.php';
        } else {
            $items = KAPM_Database::get_corporations();
            include KAPM_PLUGIN_PATH . 'admin/views/corporation-list.php';
        }
    }

    // =========================================================================
    // Organization
    // =========================================================================

    private function handle_organization(): void {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

        if ( $action === 'delete' && isset( $_GET['id'], $_GET['_wpnonce'] ) ) {
            if ( wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'kapm_delete_organization' ) ) {
                KAPM_Database::delete_organization( absint( $_GET['id'] ) );
                echo '<div class="notice notice-success"><p>' . esc_html__( '削除しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            }
            $action = 'list';
        }

        if ( isset( $_POST['kapm_organization_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['kapm_organization_nonce'] ), 'kapm_save_organization' ) ) {
            $data = array(
                'name'     => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
                'name_en'  => isset( $_POST['name_en'] ) ? sanitize_text_field( wp_unslash( $_POST['name_en'] ) ) : '',
                'role'        => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'Publisher',
                'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
                'url'         => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
                'logo_url'    => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
                'same_as'     => isset( $_POST['same_as'] ) ? sanitize_textarea_field( wp_unslash( $_POST['same_as'] ) ) : '',
                'panel_style' => isset( $_POST['panel_style'] ) ? sanitize_text_field( wp_unslash( $_POST['panel_style'] ) ) : 'default',
            );

            if ( ! empty( $_POST['id'] ) ) {
                KAPM_Database::update_organization( absint( $_POST['id'] ), $data );
                echo '<div class="notice notice-success"><p>' . esc_html__( '更新しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            } else {
                KAPM_Database::insert_organization( $data );
                echo '<div class="notice notice-success"><p>' . esc_html__( '追加しました。', 'kashiwazaki-seo-author-panel-manager' ) . '</p></div>';
            }
            $action = 'list';
        }

        if ( $action === 'add' || $action === 'edit' ) {
            $item = null;
            if ( $action === 'edit' && isset( $_GET['id'] ) ) {
                $item = KAPM_Database::get_organization( absint( $_GET['id'] ) );
            }
            include KAPM_PLUGIN_PATH . 'admin/views/organization-form.php';
        } else {
            $items = KAPM_Database::get_organizations();
            include KAPM_PLUGIN_PATH . 'admin/views/organization-list.php';
        }
    }
}
