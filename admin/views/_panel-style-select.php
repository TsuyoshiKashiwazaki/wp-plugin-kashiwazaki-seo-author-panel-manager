<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// 旧 'dark'（デザインとカラーが混ざった値）は デザイン=標準 / カラー=ダーク として表示する
$appearance    = KAPM_Database::normalize_panel_appearance( $item ?? array() );
$current_style = $appearance['design'];
$current_color = $appearance['color'];
$designs       = KAPM_Database::get_panel_designs();
$colors        = KAPM_Database::get_panel_colors();
$custom_colors = array(
    'color_bg'     => array( __( '背景色', 'kashiwazaki-seo-author-panel-manager' ), '#ffffff' ),
    'color_text'   => array( __( '文字色', 'kashiwazaki-seo-author-panel-manager' ), '#333333' ),
    'color_accent' => array( __( 'アクセント色', 'kashiwazaki-seo-author-panel-manager' ), '#0073aa' ),
);
?>
<tr>
    <th scope="row"><label for="panel_style"><?php esc_html_e( 'パネルのデザイン', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
    <td>
        <select id="panel_style" name="panel_style">
            <?php foreach ( $designs as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_style, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
<tr>
    <th scope="row"><label for="panel_color"><?php esc_html_e( 'パネルのカラー', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
    <td>
        <select id="panel_color" name="panel_color">
            <?php foreach ( $colors as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_color, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e( '「おまかせ」はデザインごとの標準の配色です。「カスタム」を選ぶと、下の 3 色を自分で指定できます。', 'kashiwazaki-seo-author-panel-manager' ); ?></p>
    </td>
</tr>
<?php foreach ( $custom_colors as $field => $meta ) : ?>
<tr class="kapm-custom-color-row">
    <th scope="row"><label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $meta[0] ); ?></label></th>
    <td>
        <input type="text" id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>" class="kapm-color-field" value="<?php echo esc_attr( (string) sanitize_hex_color( (string) ( $item[ $field ] ?? '' ) ) ); ?>" data-default-color="<?php echo esc_attr( $meta[1] ); ?>">
    </td>
</tr>
<?php endforeach; ?>
