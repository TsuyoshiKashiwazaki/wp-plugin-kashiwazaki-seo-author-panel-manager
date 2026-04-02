<?php
/**
 * 使用中の記事セクション（編集画面用パーツ）
 * 変数: $usage_type (string), $usage_id (int)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( empty( $usage_type ) || empty( $usage_id ) ) return;

$usage_posts = KAPM_Database::get_posts_using_entity( $usage_type, $usage_id );
?>
<div class="kapm-usage-posts" style="margin-top: 24px; padding: 16px 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
    <h3 style="margin-top:0; font-size: 14px;"><?php esc_html_e( '使用中の記事', 'kashiwazaki-seo-author-panel-manager' ); ?></h3>
    <?php if ( empty( $usage_posts ) ) : ?>
        <p style="color:#999; margin:0;"><?php esc_html_e( 'このエンティティを使用している記事はありません。', 'kashiwazaki-seo-author-panel-manager' ); ?></p>
    <?php else : ?>
        <ul style="margin: 0; padding: 0; list-style: none;">
            <?php foreach ( $usage_posts as $post ) : ?>
                <li style="margin-bottom: 4px;">
                    <a href="<?php echo esc_url( $post['edit_link'] ); ?>">
                        <?php echo esc_html( $post['post_title'] ); ?>
                    </a>
                    <span style="color:#999; font-size: 12px;">(<?php echo esc_html( $post['post_type'] ); ?>)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
