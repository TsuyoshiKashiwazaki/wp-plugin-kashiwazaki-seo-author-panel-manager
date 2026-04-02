<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$current_style = $item['panel_style'] ?? 'default';
$styles = array(
    'default' => 'Default（グレー背景）',
    'dark'    => 'Dark（ダーク背景）',
    'accent'  => 'Accent（左ボーダー）',
    'minimal' => 'Minimal（ボーダーなし）',
    'card'    => 'Card（シャドウ付き）',
);
?>
<tr>
    <th scope="row"><label for="panel_style"><?php esc_html_e( 'パネルデザイン', 'kashiwazaki-seo-author-panel-manager' ); ?></label></th>
    <td>
        <select id="panel_style" name="panel_style">
            <?php foreach ( $styles as $value => $label ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_style, $value ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
