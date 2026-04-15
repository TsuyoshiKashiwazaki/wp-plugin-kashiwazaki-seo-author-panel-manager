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
                        'same_as'     => array( 'type' => 'textarea', 'default' => '' ),
                        'panel_style' => array( 'type' => 'text',     'default' => 'default' ),
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
                        'same_as'     => array( 'type' => 'textarea', 'default' => '' ),
                        'panel_style' => array( 'type' => 'text',     'default' => 'default' ),
                    ),
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
                        'same_as'     => array( 'type' => 'textarea', 'default' => '' ),
                        'panel_style' => array( 'type' => 'text',     'default' => 'default' ),
                    ),
                ),
            );
        }
        return $configs[ $type ] ?? null;
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
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_persons );
        dbDelta( $sql_corporations );
        dbDelta( $sql_organizations );
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
            $raw = $data[ $field ] ?? $meta['default'];
            switch ( $meta['type'] ) {
                case 'url':
                    $values[ $field ] = esc_url_raw( (string) $raw );
                    break;
                case 'textarea':
                    $values[ $field ] = sanitize_textarea_field( (string) $raw );
                    break;
                case 'text':
                default:
                    $values[ $field ] = sanitize_text_field( (string) $raw );
                    break;
            }
            $formats[] = '%s';
        }
        return array( 'values' => $values, 'formats' => $formats );
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
