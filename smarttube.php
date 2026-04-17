<?php
/**
 * Plugin Name: SmartTube
 * Description: Display YouTube playlists and channel videos on your WordPress site with beautiful responsive grids, lightbox player, and auto-refresh. New uploads appear automatically.
 * Version: 1.0.0
 * Author: SmartTube
 * Text Domain: smarttube
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'STUBE_VERSION', '1.3.1' );
define( 'STUBE_DIR', plugin_dir_path( __FILE__ ) );
define( 'STUBE_URL', plugin_dir_url( __FILE__ ) );

require_once STUBE_DIR . 'includes/class-youtube-api.php';
require_once STUBE_DIR . 'includes/class-shortcode.php';
require_once STUBE_DIR . 'includes/class-widget.php';
require_once STUBE_DIR . 'includes/class-admin.php';
require_once STUBE_DIR . 'includes/class-cleanup.php';
require_once STUBE_DIR . 'includes/class-category-override.php';

final class SmartTube {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    public function init() {
        SmartTube_Shortcode::init();
        SmartTube_Widget::init();
        SmartTube_Category_Override::init();
        if ( is_admin() ) {
            new SmartTube_Admin();
            SmartTube_Cleanup::init();
        }
    }

    public static function get_settings() {
        return wp_parse_args( get_option( 'smarttube_settings', [] ), [
            'api_key'      => '',
            'channel_id'   => '',
            'cache_ttl'    => 15,
            'columns'      => 3,
            'limit'        => 12,
            'show_title'   => true,
            'show_date'    => true,
            'show_duration' => true,
            'play_mode'    => 'lightbox',
            'thumb_quality' => 'high',
            'bg_color'     => '#14706e',
            'header_color' => '#1a1a2e',
            'accent_color' => '#f5a623',
        ] );
    }

    public static function get_direction() {
        $lang = substr( get_locale(), 0, 2 );
        return in_array( $lang, [ 'ar', 'fa', 'he', 'ur' ] ) ? 'rtl' : 'ltr';
    }
}

SmartTube::instance();
