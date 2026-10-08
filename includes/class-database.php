<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KAPM_Database {

    /**
     * エンティティ種別ごとの設定
     * 3 種の CRUD ロジックを単一の定義で扱うためのマップ
     */
    private static function get_entity_config( string $type ): ?array {
        static $configs = null;
        if ( $configs === null ) {
            $configs = array(
                'person' => array(
                    'table'        => 'apm_persons',
                    'plural'       => 'persons',
                    'default_role' => 'Author',
                    'fields'       => array(
                        'name'        => array( 'type' => 'text',     'default' => '' ),
                        'name_en'     => array( 'type' => 'text',     'default' => '' ),
                        'role'        => array( 'type' => 'text',     'default' => 'Author' ),
                        'job_title'   => array( 'type' => 'text',     'default' => '' ),
                        'bio'         => array( 'type' => 'textarea', 'default' => '' ),
                        'image_url'   => array( 'type' => 'url',      'default' => '' ),
                        'url'         => array( 'type' => 'url',      'default' => '' ),
                        'same_as'     => array( 'type' => 'url_list', 'default' => '' ),
                        'panel_style' => array( 'type' => 'text',     'default' => 'default' ),
                        'panel_color' => array( 'type' => 'text',     'default' => 'auto' ),
                        'color_bg'    => array( 'type' => 'color',    'default' => '' ),
                        'color_text'  => array( 'type' => 'color',    'default' => '' ),
                        'color_accent' => array( 'type' => 'color', 'default' => '' ),
                    ),
                ),
                'corporation' => array(
                    'table'        => 'apm_corporations',
                    'plural'       => 'corporations',
                    'default_role' => 'Publisher',
                    'fields'       => array(
                        'name'        => array( 'type' => 'text',     'default' => '' ),
                        'name_en'     => array( 'type' => 'text',     'default' => '' ),
                        'role'        => array( 'type' => 'text',     'default' => 'Publisher' ),
                        'description' => array( 'type' => 'textarea', 'default' => '' ),
                        'url'         => array( 'type' => 'url',      'default' => '' ),
                        'logo_url'    => array( 'type' => 'url',      'default' => '' ),
                        'same_as'     => array( 'type' => 'url_list', 'default' => '' ),
                        'panel_style' => array( 'type' => 'text',     'default' => 'default' ),
                        'panel_color' => array( 'type' => 'text',     'default' => 'auto' ),
                        'color_bg'    => array( 'type' => 'color',    'default' => '' ),
                        'color_text'  => array( 'type' => 'color',    'default' => '' ),
                        'color_accent' => array( 'type' => 'color', 'default' => '' ),
                    ) + self::get_org_detail_fields(),
                ),
                'organization' => array(
                    'table'        => 'apm_organizations',
                    'plural'       => 'organizations',
                    'default_role' => 'Publisher',
                    'fields'       => array(
                        'name'        => array( 'type' => 'text',     'default' => '' ),
                        'name_en'     => array( 'type' => 'text',     'default' => '' ),
                        'role'        => array( 'type' => 'text',     'default' => 'Publisher' ),
                        'description' => array( 'type' => 'textarea', 'default' => '' ),
                        'url'         => array( 'type' => 'url',      'default' => '' ),
                        'logo_url'    => array( 'type' => 'url',      'default' => '' ),
                        'same_as'     => array( 'type' => 'url_list', 'default' => '' ),
                        'panel_style' => array( 'type' => 'text',     'default' => 'default' ),
                        'panel_color' => array( 'type' => 'text',     'default' => 'auto' ),
                        'color_bg'    => array( 'type' => 'color',    'default' => '' ),
                        'color_text'  => array( 'type' => 'color',    'default' => '' ),
                        'color_accent' => array( 'type' => 'color', 'default' => '' ),
                    ) + self::get_org_detail_fields(),
                ),
            );
        }
        return $configs[ $type ] ?? null;
    }

    /**
     * Corporation / Organization 共通の任意項目（住所・連絡先・法人情報）
     * キーは DB の列名、type は prepare_field_values() での無害化の方法
     * 出力先は Google の Organization 構造化データのプロパティ
     * https://developers.google.com/search/docs/appearance/structured-data/organization
     */
    public static function get_org_detail_fields(): array {
        return array(
            'postal_code'            => array( 'type' => 'text',      'default' => '' ), // address.postalCode
            'address_region'         => array( 'type' => 'text',      'default' => '' ), // address.addressRegion
            'address_locality'       => array( 'type' => 'text',      'default' => '' ), // address.addressLocality
            'street_address'         => array( 'type' => 'text',      'default' => '' ), // address.streetAddress
            'address_country'        => array( 'type' => 'country',   'default' => '' ), // address.addressCountry
            'telephone'              => array( 'type' => 'text',      'default' => '' ),
            'email'                  => array( 'type' => 'email',     'default' => '' ),
            'contact_type'           => array( 'type' => 'text',      'default' => '' ), // contactPoint.contactType
            'contact_telephone'      => array( 'type' => 'text',      'default' => '' ), // contactPoint.telephone
            'contact_email'          => array( 'type' => 'email',     'default' => '' ), // contactPoint.email
            'legal_name'             => array( 'type' => 'text',      'default' => '' ),
            'founding_date'          => array( 'type' => 'date',      'default' => '' ),
            'number_of_employees'    => array( 'type' => 'employees', 'default' => '' ),
            'tax_id'                 => array( 'type' => 'text',      'default' => '' ),
            'vat_id'                 => array( 'type' => 'text',      'default' => '' ),
            'iso6523_code'           => array( 'type' => 'text',      'default' => '' ),
            'duns'                   => array( 'type' => 'text',      'default' => '' ),
            'lei_code'               => array( 'type' => 'text',      'default' => '' ),
            'global_location_number' => array( 'type' => 'text',      'default' => '' ),
            'naics'                  => array( 'type' => 'text',      'default' => '' ),
        );
    }

    /**
     * 全エンティティ種別の plural キー（REST / 逆引き検索の whitelist）
     */
    public static function get_entity_types(): array {
        return array( 'person', 'corporation', 'organization' );
    }

    public static function get_entity_plurals(): array {
        return array( 'persons', 'corporations', 'organizations' );
    }

    private static function get_table_name( string $type ): string {
        global $wpdb;
        $config = self::get_entity_config( $type );
        return $config ? ( $wpdb->prefix . $config['table'] ) : '';
    }

    /**
     * テーブル作成（プラグイン有効化時）
     */
    public static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_persons       = $wpdb->prefix . 'apm_persons';
        $table_corporations  = $wpdb->prefix . 'apm_corporations';
        $table_organizations = $wpdb->prefix . 'apm_organizations';

        // Corporation / Organization 共通の任意項目の列（dbDelta の書式に合わせ 1 列 1 行）
        $org_detail_columns = '';
        foreach ( array_keys( self::get_org_detail_fields() ) as $column ) {
            $org_detail_columns .= "            {$column} varchar(255) NOT NULL DEFAULT '',\n";
        }

        $sql_persons = "CREATE TABLE {$table_persons} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            name_en varchar(255) NOT NULL DEFAULT '',
            role varchar(255) NOT NULL DEFAULT 'Author',
            job_title varchar(255) NOT NULL DEFAULT '',
            bio text NOT NULL,
            image_url varchar(2083) NOT NULL DEFAULT '',
            url varchar(2083) NOT NULL DEFAULT '',
            same_as text NOT NULL,
            panel_style varchar(50) NOT NULL DEFAULT 'default',
            panel_color varchar(50) NOT NULL DEFAULT 'auto',
            color_bg varchar(7) NOT NULL DEFAULT '',
            color_text varchar(7) NOT NULL DEFAULT '',
            color_accent varchar(7) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        $sql_corporations = "CREATE TABLE {$table_corporations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            name_en varchar(255) NOT NULL DEFAULT '',
            role varchar(255) NOT NULL DEFAULT 'Publisher',
            description text NOT NULL,
            url varchar(2083) NOT NULL DEFAULT '',
            logo_url varchar(2083) NOT NULL DEFAULT '',
            same_as text NOT NULL,
            panel_style varchar(50) NOT NULL DEFAULT 'default',
            panel_color varchar(50) NOT NULL DEFAULT 'auto',
            color_bg varchar(7) NOT NULL DEFAULT '',
            color_text varchar(7) NOT NULL DEFAULT '',
            color_accent varchar(7) NOT NULL DEFAULT '',
{$org_detail_columns}            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        $sql_organizations = "CREATE TABLE {$table_organizations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL DEFAULT '',
            name_en varchar(255) NOT NULL DEFAULT '',
            role varchar(255) NOT NULL DEFAULT 'Publisher',
            description text NOT NULL,
            url varchar(2083) NOT NULL DEFAULT '',
            logo_url varchar(2083) NOT NULL DEFAULT '',
            same_as text NOT NULL,
            panel_style varchar(50) NOT NULL DEFAULT 'default',
            panel_color varchar(50) NOT NULL DEFAULT 'auto',
            color_bg varchar(7) NOT NULL DEFAULT '',
            color_text varchar(7) NOT NULL DEFAULT '',
            color_accent varchar(7) NOT NULL DEFAULT '',
{$org_detail_columns}            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_persons );
        dbDelta( $sql_corporations );
        dbDelta( $sql_organizations );

        update_option( 'kapm_db_version', KAPM_DB_VERSION );
    }

    /**
     * 1 行 1 URL のリスト（sameAs）を無害化する
     * sanitize_textarea_field は %XX（パーセントエンコード）を削除して
     * https://www.linkedin.com/in/%E6%9F%8F... のような URL を壊すため、1 行ずつ esc_url_raw に掛ける
     */
    public static function sanitize_url_list( string $text ): string {
        $urls = array();
        foreach ( preg_split( '/\R/', $text ) as $line ) {
            $url = esc_url_raw( trim( $line ) );
            if ( $url !== '' ) {
                $urls[] = $url;
            }
        }
        return implode( "\n", $urls );
    }

    /**
     * 有効化を経ずにファイルだけ更新された場合も列を追加する
     * （dbDelta は既存テーブルに無い列を ALTER TABLE ... ADD COLUMN で足す）
     */
    public static function maybe_upgrade(): void {
        if ( get_option( 'kapm_db_version' ) !== KAPM_DB_VERSION ) {
            self::create_tables();
        }
    }

    /**
     * パネルのデザイン（型）。値 => 管理画面の表示名
     * 旧 'dark' はデザインとカラーが混ざっていたため、normalize_panel_appearance() で
     * デザイン 'default' + カラー 'dark' に読み替える
     */
    public static function get_panel_designs(): array {
        return array(
            'default' => __( '標準（枠線）', 'kashiwazaki-seo-author-panel-manager' ),
            'accent'  => __( 'アクセント（左に太線）', 'kashiwazaki-seo-author-panel-manager' ),
            'minimal' => __( 'ミニマル（下線のみ）', 'kashiwazaki-seo-author-panel-manager' ),
            'card'    => __( 'カード（影付き）', 'kashiwazaki-seo-author-panel-manager' ),
        );
    }

    /**
     * パネルのカラー。値 => 管理画面の表示名
     * 'auto' はデザインごとの従来の配色、'custom' は color_bg / color_text / color_accent を使う
     */
    public static function get_panel_colors(): array {
        return array(
            'auto'   => __( 'おまかせ（デザインに合わせる）', 'kashiwazaki-seo-author-panel-manager' ),
            'gray'   => __( 'グレー', 'kashiwazaki-seo-author-panel-manager' ),
            'white'  => __( '白', 'kashiwazaki-seo-author-panel-manager' ),
            'dark'   => __( 'ダーク', 'kashiwazaki-seo-author-panel-manager' ),
            'blue'   => __( 'ブルー', 'kashiwazaki-seo-author-panel-manager' ),
            'green'  => __( 'グリーン', 'kashiwazaki-seo-author-panel-manager' ),
            'orange' => __( 'オレンジ', 'kashiwazaki-seo-author-panel-manager' ),
            'custom' => __( 'カスタム（色を指定）', 'kashiwazaki-seo-author-panel-manager' ),
        );
    }

    /**
     * 保存値からデザインとカラーを確定する（旧 'dark'・未知の値の読み替えを 1 か所に集約）
     *
     * @return array{design: string, color: string}
     */
    public static function normalize_panel_appearance( array $entity ): array {
        $design = (string) ( $entity['panel_style'] ?? 'default' );
        $color  = (string) ( $entity['panel_color'] ?? 'auto' );
        if ( $design === 'dark' ) {
            $design = 'default';
            if ( $color === 'auto' || $color === '' ) {
                $color = 'dark';
            }
        }
        if ( ! array_key_exists( $design, self::get_panel_designs() ) ) {
            $design = 'default';
        }
        if ( ! array_key_exists( $color, self::get_panel_colors() ) ) {
            $color = 'auto';
        }
        return array( 'design' => $design, 'color' => $color );
    }

    // =========================================================================
    // Generic CRUD (3 エンティティ共通の内部実装)
    // =========================================================================

    public static function get_entities( string $type ): array {
        global $wpdb;
        $table = self::get_table_name( $type );
        if ( $table === '' ) {
            return array();
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — table name is a fixed internal constant, not user input
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A ) ?: array();
    }

    public static function get_entity( string $type, int $id ): ?array {
        global $wpdb;
        $table = self::get_table_name( $type );
        if ( $table === '' || $id <= 0 ) {
            return null;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function insert_entity( string $type, array $data ): int|false {
        global $wpdb;
        $table  = self::get_table_name( $type );
        $config = self::get_entity_config( $type );
        if ( $table === '' || $config === null ) {
            return false;
        }
        $prepared = self::prepare_field_values( $config, $data );
        $result   = $wpdb->insert( $table, $prepared['values'], $prepared['formats'] );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function update_entity( string $type, int $id, array $data ): bool {
        global $wpdb;
        $table  = self::get_table_name( $type );
        $config = self::get_entity_config( $type );
        if ( $table === '' || $config === null || $id <= 0 ) {
            return false;
        }
        $prepared = self::prepare_field_values( $config, $data );
        return $wpdb->update( $table, $prepared['values'], array( 'id' => $id ), $prepared['formats'], array( '%d' ) ) !== false;
    }

    public static function delete_entity( string $type, int $id ): bool {
        global $wpdb;
        $table = self::get_table_name( $type );
        if ( $table === '' || $id <= 0 ) {
            return false;
        }
        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ) !== false;
    }

    /**
     * config の fields 定義に従い、$data から値を抽出・サニタイズして $wpdb->insert/update 用の配列を生成
     */
    private static function prepare_field_values( array $config, array $data ): array {
        $values  = array();
        $formats = array();
        foreach ( $config['fields'] as $field => $meta ) {
            $values[ $field ] = self::sanitize_value( $meta['type'], (string) ( $data[ $field ] ?? $meta['default'] ) );
            $formats[]        = '%s';
        }
        return array( 'values' => $values, 'formats' => $formats );
    }

    /**
     * fields 定義の type に従って 1 つの値を無害化する
     * 書式が決まっている型（email / country / date / employees）は、合わない値を空文字にする
     */
    public static function sanitize_value( string $type, string $raw ): string {
        switch ( $type ) {
            case 'url':
                return esc_url_raw( $raw );
            case 'textarea':
                return sanitize_textarea_field( $raw );
            case 'url_list':
                return self::sanitize_url_list( $raw );
            case 'color':
                // #rgb / #rrggbb 以外は空文字（sanitize_hex_color は不正値で null を返す）
                return (string) sanitize_hex_color( $raw );
            case 'email':
                // 形式が正しくないメールアドレスは sanitize_email が空文字を返す
                return sanitize_email( $raw );
            case 'country':
                // ISO 3166-1 alpha-2（英字 2 文字）
                $code = strtoupper( trim( $raw ) );
                return preg_match( '/^[A-Z]{2}$/', $code ) ? $code : '';
            case 'date':
                return self::sanitize_iso_date( $raw );
            case 'employees':
                $range = self::parse_employees( $raw );
                if ( $range === null ) {
                    return '';
                }
                return $range['min'] === $range['max'] ? (string) $range['min'] : $range['min'] . '-' . $range['max'];
            case 'text':
            default:
                return sanitize_text_field( $raw );
        }
    }

    /**
     * ISO 8601 の日付（YYYY / YYYY-MM / YYYY-MM-DD）。実在しない日付やそれ以外の書式は空文字
     */
    private static function sanitize_iso_date( string $raw ): string {
        $date = trim( $raw );
        if ( ! preg_match( '/^(\d{4})(?:-(\d{2})(?:-(\d{2}))?)?$/', $date, $m ) ) {
            return '';
        }
        $month = isset( $m[2] ) ? (int) $m[2] : 1;
        $day   = isset( $m[3] ) ? (int) $m[3] : 1;
        return checkdate( $month, $day, (int) $m[1] ) ? $date : '';
    }

    /**
     * 従業員数を「人数」か「範囲」として読む。全角数字・カンマ・「〜」も受け付ける
     * 例: '2056' → min=max=2056、'100-999' / '１００〜９９９' → min=100, max=999
     *
     * @return array{min: int, max: int}|null 読めない値は null
     */
    public static function parse_employees( string $raw ): ?array {
        $text = strtr( trim( $raw ), array(
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4',
            '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            '〜' => '-', '～' => '-', '~' => '-', '－' => '-', '−' => '-',
            ',' => '', '，' => '', ' ' => '', '　' => '',
        ) );
        if ( preg_match( '/^(\d{1,9})$/', $text, $m ) ) {
            return array( 'min' => (int) $m[1], 'max' => (int) $m[1] );
        }
        if ( preg_match( '/^(\d{1,9})-(\d{1,9})$/', $text, $m ) && (int) $m[1] <= (int) $m[2] ) {
            return array( 'min' => (int) $m[1], 'max' => (int) $m[2] );
        }
        return null;
    }

    // =========================================================================
    // Backwards-compatible wrappers (単数系メソッド、既存の呼び出し元互換用)
    // =========================================================================

    public static function get_persons(): array        { return self::get_entities( 'person' ); }
    public static function get_corporations(): array   { return self::get_entities( 'corporation' ); }
    public static function get_organizations(): array  { return self::get_entities( 'organization' ); }

    public static function get_person( int $id ): ?array       { return self::get_entity( 'person', $id ); }
    public static function get_corporation( int $id ): ?array  { return self::get_entity( 'corporation', $id ); }
    public static function get_organization( int $id ): ?array { return self::get_entity( 'organization', $id ); }

    public static function insert_person( array $data ): int|false       { return self::insert_entity( 'person', $data ); }
    public static function insert_corporation( array $data ): int|false  { return self::insert_entity( 'corporation', $data ); }
    public static function insert_organization( array $data ): int|false { return self::insert_entity( 'organization', $data ); }

    public static function update_person( int $id, array $data ): bool       { return self::update_entity( 'person', $id, $data ); }
    public static function update_corporation( int $id, array $data ): bool  { return self::update_entity( 'corporation', $id, $data ); }
    public static function update_organization( int $id, array $data ): bool { return self::update_entity( 'organization', $id, $data ); }

    public static function delete_person( int $id ): bool       { return self::delete_entity( 'person', $id ); }
    public static function delete_corporation( int $id ): bool  { return self::delete_entity( 'corporation', $id ); }
    public static function delete_organization( int $id ): bool { return self::delete_entity( 'organization', $id ); }

    // =========================================================================
    // Usage reverse lookup (innerBlocks 再帰対応, type whitelist, post_content を SELECT に含める)
    // =========================================================================

    /**
     * 指定エンティティを使用している投稿を検索
     *
     * @param string $type_plural 'persons', 'corporations', 'organizations'
     * @param int    $id          エンティティID
     * @return array [ ['ID' => int, 'post_title' => string, 'edit_link' => string], ... ]
     */
    public static function get_posts_using_entity( string $type_plural, int $id ): array {
        // whitelist
        if ( ! in_array( $type_plural, self::get_entity_plurals(), true ) || $id <= 0 ) {
            return array();
        }

        global $wpdb;
        $results = array();

        // ブロック形式: "persons":"1" or "persons":"1,2,3"
        $like_pattern = '%' . $wpdb->esc_like( '"' . $type_plural . '":"' ) . '%';

        // post_content を SELECT に含めて再取得を排除
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                $like_pattern
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return array();
        }

        $id_str = (string) $id;
        foreach ( $rows as $row ) {
            $blocks = parse_blocks( $row['post_content'] );
            if ( self::block_tree_contains_entity( $blocks, $type_plural, $id_str ) ) {
                $results[] = array(
                    'ID'         => (int) $row['ID'],
                    'post_title' => $row['post_title'],
                    'post_type'  => $row['post_type'],
                    'edit_link'  => get_edit_post_link( $row['ID'], 'raw' ),
                );
            }
        }

        return $results;
    }

    /**
     * ブロックツリーを再帰的に辿り、kapm/author-panel ブロックの指定属性内に ID が含まれるかを判定
     *
     * @param array  $blocks      parse_blocks() の結果
     * @param string $type_plural 'persons', 'corporations', 'organizations'
     * @param string $id_str      比較対象の ID（文字列化済み）
     */
    private static function block_tree_contains_entity( array $blocks, string $type_plural, string $id_str ): bool {
        foreach ( $blocks as $block ) {
            if ( ( $block['blockName'] ?? '' ) === 'kapm/author-panel' ) {
                $attr_val = $block['attrs'][ $type_plural ] ?? '';
                if ( is_string( $attr_val ) && $attr_val !== '' ) {
                    $ids = array_filter( array_map( 'trim', explode( ',', $attr_val ) ) );
                    if ( in_array( $id_str, $ids, true ) ) {
                        return true;
                    }
                }
            }
            // innerBlocks を再帰的にチェック（Group / Columns / Row / Reusable 等のネスト対応）
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                if ( self::block_tree_contains_entity( $block['innerBlocks'], $type_plural, $id_str ) ) {
                    return true;
                }
            }
        }
        return false;
    }
}
