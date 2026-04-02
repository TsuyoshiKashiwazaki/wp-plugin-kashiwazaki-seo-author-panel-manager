<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2><?php echo $item ? esc_html__( 'Corporation 編集', 'kashiwazaki-seo-author-panel-manager' ) : esc_html__( 'Corporation 追加', 'kashiwazaki-seo-author-panel-manager' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=kapm&tab=corporation' ) ); ?>">
    <?php wp_nonce_field( 'kapm_save_corporation', 'kapm_corporation_nonce' ); ?>
    <?php if ( $item ) : ?>
        <input type="hidden" name="id" value="<?php echo esc_attr( $item['id'] ); ?>">
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="name"><?php esc_html_e( '名前', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td><input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr( $item['name'] ?? '' ); ?>" required></td>
        </tr>
        <tr>
            <th scope="row"><label for="name_en"><?php esc_html_e( '英語名', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td><input type="text" id="name_en" name="name_en" class="regular-text" value="<?php echo esc_attr( $item['name_en'] ?? '' ); ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="role"><?php esc_html_e( 'Role', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td>
                <input type="text" id="role" name="role" class="regular-text" value="<?php echo esc_attr( $item['role'] ?? 'Publisher' ); ?>">
                <span class="kapm-tooltip" tabindex="0" aria-label="<?php esc_attr_e( 'Role の説明', 'kashiwazaki-seo-author-panel-manager' ); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                    <span class="kapm-tooltip-content">
                        <?php esc_html_e( 'JSON-LDの構造化データで使用するプロパティ名です（パネルには表示されません）。', 'kashiwazaki-seo-author-panel-manager' ); ?><br><br>
                        <strong>Publisher</strong> — <?php esc_html_e( '記事の発行元・出版社（デフォルト）', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Author</strong> — <?php esc_html_e( '記事の執筆者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Sponsor</strong> — <?php esc_html_e( 'スポンサー・資金提供者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Creator</strong> — <?php esc_html_e( 'コンテンツの作成者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Contributor</strong> — <?php esc_html_e( '寄稿者・協力者', 'kashiwazaki-seo-author-panel-manager' ); ?>
                    </span>
                </span>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="description"><?php esc_html_e( '説明（Description）', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td><textarea id="description" name="description" rows="5" class="large-text"><?php echo esc_textarea( $item['description'] ?? '' ); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="url"><?php esc_html_e( 'URL', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td><input type="url" id="url" name="url" class="large-text" value="<?php echo esc_url( $item['url'] ?? '' ); ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="logo_url"><?php esc_html_e( 'ロゴ', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td>
                <input type="url" id="logo_url" name="logo_url" class="large-text kapm-media-input" value="<?php echo esc_url( $item['logo_url'] ?? '' ); ?>">
                <button type="button" class="button kapm-media-select" data-target="logo_url"><?php esc_html_e( 'メディアから選択', 'kashiwazaki-seo-author-panel-manager' ); ?></button>
                <div class="kapm-image-preview"><?php if ( ! empty( $item['logo_url'] ) ) : ?><img src="<?php echo esc_url( $item['logo_url'] ); ?>" style="max-width:150px;margin-top:8px;"><?php endif; ?></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="same_as"><?php esc_html_e( 'sameAs URL', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td>
                <textarea id="same_as" name="same_as" rows="4" class="large-text"><?php echo esc_textarea( $item['same_as'] ?? '' ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Schema.orgのsameAsに出力するURLを1行に1つ入力してください。', 'kashiwazaki-seo-author-panel-manager' ); ?></p>
            </td>
        </tr>
        <?php include __DIR__ . '/_panel-style-select.php'; ?>
    </table>

    <?php submit_button( $item ? __( '更新', 'kashiwazaki-seo-author-panel-manager' ) : __( '追加', 'kashiwazaki-seo-author-panel-manager' ) ); ?>
</form>
<?php if ( $item ) : $usage_type = 'corporations'; $usage_id = (int) $item['id']; include __DIR__ . '/_usage-posts.php'; endif; ?>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=kapm&tab=corporation' ) ); ?>">&larr; <?php esc_html_e( '一覧に戻る', 'kashiwazaki-seo-author-panel-manager' ); ?></a></p>
