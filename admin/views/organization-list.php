<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2 class="wp-heading-inline">Organization</h2>
<a href="<?php echo esc_url( admin_url( 'admin.php?page=kapm&tab=organization&action=add' ) ); ?>" class="page-title-action"><?php esc_html_e( '新規追加', 'kashiwazaki-seo-author-panel-manager' ); ?></a>
<hr class="wp-header-end">

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th scope="col" class="column-id">ID</th>
            <th scope="col"><?php esc_html_e( '名前', 'kashiwazaki-seo-author-panel-manager' ); ?></th>
            <th scope="col"><?php esc_html_e( 'Role', 'kashiwazaki-seo-author-panel-manager' ); ?></th>
            <th scope="col"><?php esc_html_e( 'URL', 'kashiwazaki-seo-author-panel-manager' ); ?></th>
            <th scope="col"><?php esc_html_e( '操作', 'kashiwazaki-seo-author-panel-manager' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $items ) ) : ?>
            <tr><td colspan="5"><?php esc_html_e( 'データがありません。', 'kashiwazaki-seo-author-panel-manager' ); ?></td></tr>
        <?php else : ?>
            <?php foreach ( $items as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( $row['id'] ); ?></td>
                    <td><strong><?php echo esc_html( $row['name'] ); ?></strong></td>
                    <td><?php echo esc_html( $row['role'] ); ?></td>
                    <td><?php echo esc_url( $row['url'] ); ?></td>
                    <td>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=kapm&tab=organization&action=edit&id=' . $row['id'] ) ); ?>"><?php esc_html_e( '編集', 'kashiwazaki-seo-author-panel-manager' ); ?></a>
                        |
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=kapm&tab=organization&action=delete&id=' . $row['id'] ), 'kapm_delete_organization' ) ); ?>" class="kapm-delete-link" onclick="return confirm('<?php esc_attr_e( '本当に削除しますか？', 'kashiwazaki-seo-author-panel-manager' ); ?>');"><?php esc_html_e( '削除', 'kashiwazaki-seo-author-panel-manager' ); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
