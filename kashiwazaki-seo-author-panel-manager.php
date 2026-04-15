<?php
/**
 * Plugin Name: Kashiwazaki SEO Author Panel Manager
 * Plugin URI: https://www.tsuyoshikashiwazaki.jp
 * Description: Manages an independent author database with Person, Corporation, and Organization entities. Outputs author panels via shortcode with Schema.org JSON-LD structured data, supporting both standalone and custom modes that link to existing Article/NewsArticle/WebPage schema IDs from other plugins.
 * Version: 1.0.2
 * Author: 柏崎剛 (Tsuyoshi Kashiwazaki)
 * Author URI: https://www.tsuyoshikashiwazaki.jp/profile/
 * Text Domain: kashiwazaki-seo-author-panel-manager
 * Domain Path: /languages
 * License: GPL-2.0+
 * Requires PHP: 8.0
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'KAPM_VERSION', '1.0.2' );
define( 'KAPM_PLUGIN_FILE', __FILE__ );
define( 'KAPM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'KAPM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KAPM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include classes
require_once KAPM_PLUGIN_PATH . 'includes/class-database.php';
require_once KAPM_PLUGIN_PATH . 'includes/class-admin.php';
require_once KAPM_PLUGIN_PATH . 'includes/class-shortcode.php';
require_once KAPM_PLUGIN_PATH . 'includes/class-gutenberg.php';

// Activation hook
register_activation_hook( __FILE__, array( 'KAPM_Database', 'create_tables' ) );

// Initialize admin
if ( is_admin() ) {
    new KAPM_Admin();
}

// Initialize shortcode
global $kapm_shortcode;
$kapm_shortcode = new KAPM_Shortcode();

// Initialize Gutenberg sidebar
new KAPM_Gutenberg();

// Plugin action links - 設定リンク
add_filter( 'plugin_action_links_' . KAPM_PLUGIN_BASENAME, function ( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=kapm' ) ) . '">' . __( '設定', 'kashiwazaki-seo-author-panel-manager' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
} );
