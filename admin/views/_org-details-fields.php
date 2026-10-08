<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Corporation / Organization 共通の任意項目（住所・連絡先・法人情報）。項目の定義は KAPM_Admin::get_org_detail_sections()
foreach ( KAPM_Admin::get_org_detail_sections() as $kapm_section ) :
?>
<h3 class="kapm-form-section"><?php echo esc_html( $kapm_section['title'] ); ?></h3>
<p class="description"><?php echo esc_html( $kapm_section['description'] ); ?></p>
<table class="form-table">
    <?php foreach ( $kapm_section['fields'] as $kapm_field => $kapm_def ) :
        $kapm_value = (string) ( $item[ $kapm_field ] ?? '' );
        // 国コードは未入力なら日本（JP）を入れておく（住所が空なら出力されないので影響しない）
        if ( $kapm_field === 'address_country' && $kapm_value === '' ) {
            $kapm_value = 'JP';
        }
    ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr( $kapm_field ); ?>"><?php echo esc_html( $kapm_def['label'] ); ?></label></th>
            <td>
                <input type="<?php echo esc_attr( $kapm_def['type'] ?? 'text' ); ?>" id="<?php echo esc_attr( $kapm_field ); ?>" name="<?php echo esc_attr( $kapm_field ); ?>" class="<?php echo esc_attr( $kapm_def['class'] ?? 'regular-text' ); ?>" value="<?php echo esc_attr( $kapm_value ); ?>" maxlength="255"<?php if ( ! empty( $kapm_def['placeholder'] ) ) : ?> placeholder="<?php echo esc_attr( $kapm_def['placeholder'] ); ?>"<?php endif; ?><?php if ( ! empty( $kapm_def['pattern'] ) ) : ?> pattern="<?php echo esc_attr( $kapm_def['pattern'] ); ?>"<?php endif; ?>>
                <?php if ( ! empty( $kapm_def['help'] ) ) : ?>
                    <p class="description"><?php echo esc_html( $kapm_def['help'] ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
<?php endforeach; ?>
