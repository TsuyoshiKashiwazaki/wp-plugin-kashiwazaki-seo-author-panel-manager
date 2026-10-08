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
        wp_enqueue_style( 'wp-color-picker' );

        $js_path = KAPM_PLUGIN_PATH . 'admin/js/admin-media.js';
        $js_ver  = KAPM_VERSION;
        if ( file_exists( $js_path ) ) {
            $js_ver .= '.' . filemtime( $js_path );
        }
        wp_enqueue_script(
            'kapm-admin-media',
            KAPM_PLUGIN_URL . 'admin/js/admin-media.js',
            array( 'jquery', 'wp-color-picker' ),
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
            foreach ( $this->find_invalid_detail_labels( $type, $data ) as $label ) {
                /* translators: %s: 入力欄の名前 */
                $this->echo_notice( 'notice-warning', sprintf( __( '「%s」は書式が正しくないため、空欄として保存しました。', 'kashiwazaki-seo-author-panel-manager' ), $label ) );
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
                'same_as'     => isset( $_POST['same_as'] ) ? KAPM_Database::sanitize_url_list( wp_unslash( $_POST['same_as'] ) ) : '',
                'panel_style' => isset( $_POST['panel_style'] ) ? sanitize_text_field( wp_unslash( $_POST['panel_style'] ) ) : 'default',
            ) + $this->collect_panel_color_data();
        }

        // corporation / organization
        $data = array(
            'name'        => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'name_en'     => isset( $_POST['name_en'] ) ? sanitize_text_field( wp_unslash( $_POST['name_en'] ) ) : '',
            'role'        => isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : 'Publisher',
            'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'url'         => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
            'logo_url'    => isset( $_POST['logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['logo_url'] ) ) : '',
            'same_as'     => isset( $_POST['same_as'] ) ? KAPM_Database::sanitize_url_list( wp_unslash( $_POST['same_as'] ) ) : '',
            'panel_style' => isset( $_POST['panel_style'] ) ? sanitize_text_field( wp_unslash( $_POST['panel_style'] ) ) : 'default',
        ) + $this->collect_panel_color_data();

        // 住所・連絡先・法人情報（書式の検証は KAPM_Database::sanitize_value() で保存時に行う）
        foreach ( array_keys( KAPM_Database::get_org_detail_fields() ) as $field ) {
            $data[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
        }
        return $data;
    }

    /**
     * 入力はあったが書式が合わず、保存時に空欄になる任意項目の名前を返す（Corp / Org のみ）
     */
    private function find_invalid_detail_labels( string $type, array $data ): array {
        if ( $type === 'person' ) {
            return array();
        }
        $labels = array();
        foreach ( self::get_org_detail_sections() as $section ) {
            $labels += wp_list_pluck( $section['fields'], 'label' );
        }
        $invalid = array();
        foreach ( KAPM_Database::get_org_detail_fields() as $field => $meta ) {
            $raw = trim( (string) ( $data[ $field ] ?? '' ) );
            if ( $raw !== '' && KAPM_Database::sanitize_value( $meta['type'], $raw ) === '' ) {
                $invalid[] = $labels[ $field ] ?? $field;
            }
        }
        return $invalid;
    }

    /**
     * Corporation / Organization の任意項目の入力欄（見出し・名前・説明）
     * 列と無害化の方法は KAPM_Database::get_org_detail_fields() で定義する
     */
    public static function get_org_detail_sections(): array {
        $tel_help = __( '国番号と市外局番を含めて入力してください（例: +81-3-1234-5678）。', 'kashiwazaki-seo-author-panel-manager' );
        return array(
            array(
                'title'       => __( '住所・電話番号', 'kashiwazaki-seo-author-panel-manager' ),
                'description' => __( '入力した項目は、パネル（説明文の下と、名前の横の i ボタンの吹き出し）と構造化データ（JSON-LD）に出力します。空欄の項目は出力しません。', 'kashiwazaki-seo-author-panel-manager' ),
                'fields'      => array(
                    'postal_code'      => array(
                        'label'       => __( '郵便番号', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => '100-0001',
                    ),
                    'address_region'   => array(
                        'label'       => __( '都道府県', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => __( '東京都', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'address_locality' => array(
                        'label'       => __( '市区町村', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => __( '千代田区', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'street_address'   => array(
                        'label'       => __( '番地・建物名', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => __( '千代田1-1 ○○ビル5F', 'kashiwazaki-seo-author-panel-manager' ),
                        'class'       => 'large-text',
                    ),
                    'address_country'  => array(
                        'label'   => __( '国コード', 'kashiwazaki-seo-author-panel-manager' ),
                        'help'    => __( 'ISO 3166-1 の英字 2 文字（日本は JP）。パネルには表示せず（住所の書き方の切り替えに使います）、構造化データに出力します。', 'kashiwazaki-seo-author-panel-manager' ),
                        'class'   => 'small-text',
                        'pattern' => '[A-Za-z]{2}',
                    ),
                    'telephone'        => array(
                        'label'       => __( '電話番号', 'kashiwazaki-seo-author-panel-manager' ),
                        'type'        => 'tel',
                        'placeholder' => '+81-3-1234-5678',
                        'help'        => $tel_help . __( 'パネルには入力したとおりに表示します。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                ),
            ),
            array(
                'title'       => __( 'メール・問い合わせ窓口', 'kashiwazaki-seo-author-panel-manager' ),
                'description' => __( '入力した項目は、パネルの名前の横の i ボタンの吹き出しと、構造化データ（JSON-LD）に出力します。', 'kashiwazaki-seo-author-panel-manager' ),
                'fields'      => array(
                    'email'             => array(
                        'label' => __( 'メールアドレス', 'kashiwazaki-seo-author-panel-manager' ),
                        'type'  => 'email',
                    ),
                    'contact_type'      => array(
                        'label'       => __( '問い合わせ窓口の種類', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => 'Customer Service',
                        'help'        => __( '窓口の用途（contactType）。例: Customer Service / Sales / Technical Support', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'contact_telephone' => array(
                        'label'       => __( '問い合わせ窓口の電話番号', 'kashiwazaki-seo-author-panel-manager' ),
                        'type'        => 'tel',
                        'placeholder' => '+81-3-1234-5678',
                        'help'        => $tel_help,
                    ),
                    'contact_email'     => array(
                        'label' => __( '問い合わせ窓口のメールアドレス', 'kashiwazaki-seo-author-panel-manager' ),
                        'type'  => 'email',
                        'help'  => __( '問い合わせ窓口は、電話番号かメールアドレスのどちらかがあるときだけ出力します。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                ),
            ),
            array(
                'title'       => __( '法人情報', 'kashiwazaki-seo-author-panel-manager' ),
                'description' => __( '入力した項目は、パネルの名前の横の i ボタンの吹き出しと、構造化データ（JSON-LD）に出力します。', 'kashiwazaki-seo-author-panel-manager' ),
                'fields'      => array(
                    'legal_name'             => array(
                        'label' => __( '正式名称（登記上の名称）', 'kashiwazaki-seo-author-panel-manager' ),
                        'help'  => __( '「名前」と異なる場合に入力してください（legalName）。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'founding_date'          => array(
                        'label'       => __( '設立日', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => '2012-06-26',
                        'pattern'     => '\d{4}(-\d{2}(-\d{2})?)?',
                        'help'        => __( 'YYYY-MM-DD 形式。年だけ（YYYY）・年月だけ（YYYY-MM）も入力できます。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'number_of_employees'    => array(
                        'label'       => __( '従業員数', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => '50',
                        'help'        => __( '人数（例: 50）か範囲（例: 100-999）。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'tax_id'                 => array(
                        'label' => __( '税務上の識別番号（taxID）', 'kashiwazaki-seo-author-panel-manager' ),
                        'help'  => __( '例: 法人番号（13 桁）。国コードと同じ国の番号を入力してください。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'vat_id'                 => array(
                        'label' => __( 'VAT 登録番号（vatID）', 'kashiwazaki-seo-author-panel-manager' ),
                        'help'  => __( '付加価値税（VAT）の登録番号。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'iso6523_code'           => array(
                        'label'       => __( 'ISO 6523 コード', 'kashiwazaki-seo-author-panel-manager' ),
                        'placeholder' => '0199:724500PMK2A2M1SQQ228',
                        'help'        => __( '識別体系の番号（ICD）、コロン、ID の順に入力します。0060 = DUNS、0088 = GLN、0199 = LEI。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'duns'                   => array(
                        'label' => __( 'DUNS 番号', 'kashiwazaki-seo-author-panel-manager' ),
                        'help'  => __( 'Google は ISO 6523 コード（0060:）での指定を推奨しています。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'lei_code'               => array(
                        'label' => __( 'LEI コード', 'kashiwazaki-seo-author-panel-manager' ),
                        'help'  => __( 'Google は ISO 6523 コード（0199:）での指定を推奨しています。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'global_location_number' => array(
                        'label' => __( 'GLN（GS1 Global Location Number）', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                    'naics'                  => array(
                        'label' => __( 'NAICS コード', 'kashiwazaki-seo-author-panel-manager' ),
                        'help'  => __( '北米産業分類システムの業種コード。', 'kashiwazaki-seo-author-panel-manager' ),
                    ),
                ),
            ),
        );
    }

    /**
     * パネルのカラー（プリセット名とカスタム 3 色）を POST から抽出
     * 色は KAPM_Database::prepare_field_values() で sanitize_hex_color により再検証される
     */
    private function collect_panel_color_data(): array {
        $data = array(
            'panel_color' => isset( $_POST['panel_color'] ) ? sanitize_key( wp_unslash( $_POST['panel_color'] ) ) : 'auto',
        );
        foreach ( array( 'color_bg', 'color_text', 'color_accent' ) as $field ) {
            $data[ $field ] = isset( $_POST[ $field ] ) ? (string) sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) ) : '';
        }
        return $data;
    }
}
