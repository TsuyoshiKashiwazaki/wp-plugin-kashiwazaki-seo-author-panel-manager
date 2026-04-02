<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KAPM_Database {

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
    // Person CRUD
    // =========================================================================

    public static function get_persons(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_persons';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A ) ?: array();
    }

    public static function get_person( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_persons';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function insert_person( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_persons';
        $result = $wpdb->insert( $table, array(
            'name'      => sanitize_text_field( $data['name'] ?? '' ),
            'name_en'   => sanitize_text_field( $data['name_en'] ?? '' ),
            'role'      => sanitize_text_field( $data['role'] ?? 'Author' ),
            'job_title' => sanitize_text_field( $data['job_title'] ?? '' ),
            'bio'       => sanitize_textarea_field( $data['bio'] ?? '' ),
            'image_url' => esc_url_raw( $data['image_url'] ?? '' ),
            'url'       => esc_url_raw( $data['url'] ?? '' ),
            'same_as'     => sanitize_textarea_field( $data['same_as'] ?? '' ),
            'panel_style' => sanitize_text_field( $data['panel_style'] ?? 'default' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        return $result ? $wpdb->insert_id : false;
    }

    public static function update_person( int $id, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_persons';
        $result = $wpdb->update( $table, array(
            'name'        => sanitize_text_field( $data['name'] ?? '' ),
            'name_en'     => sanitize_text_field( $data['name_en'] ?? '' ),
            'role'        => sanitize_text_field( $data['role'] ?? 'Author' ),
            'job_title'   => sanitize_text_field( $data['job_title'] ?? '' ),
            'bio'         => sanitize_textarea_field( $data['bio'] ?? '' ),
            'image_url'   => esc_url_raw( $data['image_url'] ?? '' ),
            'url'         => esc_url_raw( $data['url'] ?? '' ),
            'same_as'     => sanitize_textarea_field( $data['same_as'] ?? '' ),
            'panel_style' => sanitize_text_field( $data['panel_style'] ?? 'default' ),
        ), array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
        return $result !== false;
    }

    public static function delete_person( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_persons';
        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ) !== false;
    }

    // =========================================================================
    // Corporation CRUD
    // =========================================================================

    public static function get_corporations(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_corporations';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A ) ?: array();
    }

    public static function get_corporation( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_corporations';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function insert_corporation( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_corporations';
        $result = $wpdb->insert( $table, array(
            'name'        => sanitize_text_field( $data['name'] ?? '' ),
            'name_en'     => sanitize_text_field( $data['name_en'] ?? '' ),
            'role'        => sanitize_text_field( $data['role'] ?? 'Publisher' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'url'         => esc_url_raw( $data['url'] ?? '' ),
            'logo_url'    => esc_url_raw( $data['logo_url'] ?? '' ),
            'same_as'     => sanitize_textarea_field( $data['same_as'] ?? '' ),
            'panel_style' => sanitize_text_field( $data['panel_style'] ?? 'default' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        return $result ? $wpdb->insert_id : false;
    }

    public static function update_corporation( int $id, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_corporations';
        $result = $wpdb->update( $table, array(
            'name'        => sanitize_text_field( $data['name'] ?? '' ),
            'name_en'     => sanitize_text_field( $data['name_en'] ?? '' ),
            'role'        => sanitize_text_field( $data['role'] ?? 'Publisher' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'url'         => esc_url_raw( $data['url'] ?? '' ),
            'logo_url'    => esc_url_raw( $data['logo_url'] ?? '' ),
            'same_as'     => sanitize_textarea_field( $data['same_as'] ?? '' ),
            'panel_style' => sanitize_text_field( $data['panel_style'] ?? 'default' ),
        ), array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
        return $result !== false;
    }

    public static function delete_corporation( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_corporations';
        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ) !== false;
    }

    // =========================================================================
    // Organization CRUD
    // =========================================================================

    public static function get_organizations(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_organizations';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A ) ?: array();
    }

    public static function get_organization( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_organizations';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function insert_organization( array $data ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_organizations';
        $result = $wpdb->insert( $table, array(
            'name'        => sanitize_text_field( $data['name'] ?? '' ),
            'name_en'     => sanitize_text_field( $data['name_en'] ?? '' ),
            'role'        => sanitize_text_field( $data['role'] ?? 'Publisher' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'url'         => esc_url_raw( $data['url'] ?? '' ),
            'logo_url'    => esc_url_raw( $data['logo_url'] ?? '' ),
            'same_as'     => sanitize_textarea_field( $data['same_as'] ?? '' ),
            'panel_style' => sanitize_text_field( $data['panel_style'] ?? 'default' ),
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        return $result ? $wpdb->insert_id : false;
    }

    public static function update_organization( int $id, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_organizations';
        $result = $wpdb->update( $table, array(
            'name'        => sanitize_text_field( $data['name'] ?? '' ),
            'name_en'     => sanitize_text_field( $data['name_en'] ?? '' ),
            'role'        => sanitize_text_field( $data['role'] ?? 'Publisher' ),
            'description' => sanitize_textarea_field( $data['description'] ?? '' ),
            'url'         => esc_url_raw( $data['url'] ?? '' ),
            'logo_url'    => esc_url_raw( $data['logo_url'] ?? '' ),
            'same_as'     => sanitize_textarea_field( $data['same_as'] ?? '' ),
            'panel_style' => sanitize_text_field( $data['panel_style'] ?? 'default' ),
        ), array( 'id' => $id ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ), array( '%d' ) );
        return $result !== false;
    }

    public static function delete_organization( int $id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'apm_organizations';
        return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ) !== false;
    }

    /**
     * 指定エンティティを使用している投稿を検索
     *
     * @param string $type   'persons', 'corporations', 'organizations'
     * @param int    $id     エンティティID
     * @return array [ ['ID' => int, 'post_title' => string, 'edit_link' => string], ... ]
     */
    public static function get_posts_using_entity( string $type, int $id ): array {
        global $wpdb;
        $results = array();

        // ブロック形式: "persons":"1" or "persons":"1,2,3"
        $like_pattern = '%"' . $type . '":"' . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_title, post_type FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
                $like_pattern
            ),
            ARRAY_A
        );

        $id_str = (string) $id;
        foreach ( $rows as $row ) {
            $blocks = parse_blocks( get_post_field( 'post_content', $row['ID'] ) );
            foreach ( $blocks as $block ) {
                if ( $block['blockName'] !== 'kapm/author-panel' ) {
                    continue;
                }
                $attr_val = $block['attrs'][ $type ] ?? '';
                $ids = array_filter( array_map( 'trim', explode( ',', $attr_val ) ) );
                if ( in_array( $id_str, $ids, true ) ) {
                    $results[] = array(
                        'ID'         => (int) $row['ID'],
                        'post_title' => $row['post_title'],
                        'post_type'  => $row['post_type'],
                        'edit_link'  => get_edit_post_link( $row['ID'], 'raw' ),
                    );
                    break;
                }
            }
        }

        return $results;
    }
}
