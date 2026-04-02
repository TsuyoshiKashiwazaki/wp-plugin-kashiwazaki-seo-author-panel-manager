<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<h2><?php echo $item ? esc_html__( 'Person 編集', 'kashiwazaki-seo-author-panel-manager' ) : esc_html__( 'Person 追加', 'kashiwazaki-seo-author-panel-manager' ); ?></h2>
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=kapm&tab=person' ) ); ?>">
    <?php wp_nonce_field( 'kapm_save_person', 'kapm_person_nonce' ); ?>
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
                <input type="text" id="role" name="role" class="regular-text" value="<?php echo esc_attr( $item['role'] ?? 'Author' ); ?>">
                <span class="kapm-tooltip" tabindex="0" aria-label="<?php esc_attr_e( 'Role の説明', 'kashiwazaki-seo-author-panel-manager' ); ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                    <span class="kapm-tooltip-content">
                        <?php esc_html_e( 'JSON-LDの構造化データで使用するプロパティ名です（パネルには表示されません）。', 'kashiwazaki-seo-author-panel-manager' ); ?><br><br>
                        <strong>Author</strong> — <?php esc_html_e( '記事の執筆者（デフォルト）', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Publisher</strong> — <?php esc_html_e( '記事の発行元・出版社', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Editor</strong> — <?php esc_html_e( '記事の編集者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Reviewer</strong> — <?php esc_html_e( '記事のレビュー・監修者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Contributor</strong> — <?php esc_html_e( '寄稿者・協力者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Creator</strong> — <?php esc_html_e( 'コンテンツの作成者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Sponsor</strong> — <?php esc_html_e( 'スポンサー・資金提供者', 'kashiwazaki-seo-author-panel-manager' ); ?><br>
                        <strong>Translator</strong> — <?php esc_html_e( '翻訳者', 'kashiwazaki-seo-author-panel-manager' ); ?>
                    </span>
                </span>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="job_title"><?php esc_html_e( '肩書き（Job Title）', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td><input type="text" id="job_title" name="job_title" class="regular-text" value="<?php echo esc_attr( $item['job_title'] ?? '' ); ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="bio"><?php esc_html_e( '自己紹介（Bio）', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td><textarea id="bio" name="bio" rows="5" class="large-text"><?php echo esc_textarea( $item['bio'] ?? '' ); ?></textarea></td>
        </tr>
        <tr>
            <th scope="row"><label for="image_url"><?php esc_html_e( '画像', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td>
                <input type="url" id="image_url" name="image_url" class="large-text kapm-media-input" value="<?php echo esc_url( $item['image_url'] ?? '' ); ?>">
                <button type="button" class="button kapm-media-select" data-target="image_url"><?php esc_html_e( 'メディアから選択', 'kashiwazaki-seo-author-panel-manager' ); ?></button>
                <div class="kapm-image-preview"><?php if ( ! empty( $item['image_url'] ) ) : ?><img src="<?php echo esc_url( $item['image_url'] ); ?>" style="max-width:150px;margin-top:8px;"><?php endif; ?></div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="url"><?php esc_html_e( 'URL', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td><input type="url" id="url" name="url" class="large-text" value="<?php echo esc_url( $item['url'] ?? '' ); ?>"></td>
        </tr>
        <tr>
            <th scope="row"><label for="same_as"><?php esc_html_e( 'sameAs URL', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
            <td>
                <textarea id="same_as" name="same_as" rows="6" class="large-text"><?php echo esc_textarea( $item['same_as'] ?? '' ); ?></textarea>
                <p class="description"><?php esc_html_e( 'Schema.orgのsameAsに出力するURLを1行に1つ入力してください（例: X/Twitter, Facebook, LinkedIn, GitHub, Amazon著者ページ等）。', 'kashiwazaki-seo-author-panel-manager' ); ?></p>
            </td>
        </tr>
        <?php include __DIR__ . '/_panel-style-select.php'; ?>
    </table>

    <?php submit_button( $item ? __( '更新', 'kashiwazaki-seo-author-panel-manager' ) : __( '追加', 'kashiwazaki-seo-author-panel-manager' ) ); ?>
</form>
<?php if ( $item ) : $usage_type = 'persons'; $usage_id = (int) $item['id']; include __DIR__ . '/_usage-posts.php'; endif; ?>
<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=kapm&tab=person' ) ); ?>">&larr; <?php esc_html_e( '一覧に戻る', 'kashiwazaki-seo-author-panel-manager' ); ?></a></p>
