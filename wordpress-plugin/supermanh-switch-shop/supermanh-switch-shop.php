<?php
/**
 * Plugin Name:       SuperManh Switch Shop
 * Plugin URI:        https://supermanh.com
 * Description:       Shop bản Việt hóa Nintendo Switch cho Tinfoil: link tự điền hoặc thư mục Google Drive, tài khoản khách, nạp điểm và mở khoá từng bản, app quản lý trên điện thoại.
 * Version:           1.6.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SUPER MẠNH
 * Author URI:        https://supermanh.com
 * License:           GPL-2.0-or-later
 * Text Domain:       supermanh-switch-shop
 */

defined( 'ABSPATH' ) || exit;

define( 'SMSS_VERSION', '1.6.1' );
define( 'SMSS_DB_VERSION', '7' );
define( 'SMSS_FILE', __FILE__ );
define( 'SMSS_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMSS_URL', plugin_dir_url( __FILE__ ) );

require_once SMSS_DIR . 'includes/class-smss-settings.php';
require_once SMSS_DIR . 'includes/class-smss-db.php';
require_once SMSS_DIR . 'includes/class-smss-links.php';
require_once SMSS_DIR . 'includes/class-smss-drive.php';
require_once SMSS_DIR . 'includes/class-smss-library.php';
require_once SMSS_DIR . 'includes/class-smss-accounts.php';
require_once SMSS_DIR . 'includes/class-smss-endpoint.php';
require_once SMSS_DIR . 'includes/class-smss-points.php';
require_once SMSS_DIR . 'includes/class-smss-titles.php';
require_once SMSS_DIR . 'includes/class-smss-api.php';
require_once SMSS_DIR . 'includes/class-smss-app.php';

if ( is_admin() ) {
	require_once SMSS_DIR . 'includes/class-smss-admin.php';
	SMSS_Admin::init();
}

SMSS_Endpoint::init();
SMSS_API::init();
SMSS_App::init();

/**
 * A slug changed on the previous request: rebuild the rewrite rules once every
 * rule (shop, admin app, customer page) is registered with the new slugs, so
 * the old paths stop working.
 */
add_action( 'init', function () {
	if ( get_option( 'smss_flush_rules' ) ) {
		delete_option( 'smss_flush_rules' );
		flush_rewrite_rules( false );
	}
}, 99 );

/** Hourly re-sync of the Google Drive folders. */
add_action( 'smss_sync_folders', array( 'SMSS_Library', 'sync_all_folders' ) );

/** Keep the schema current after an update that did not re-run activation. */
add_action( 'plugins_loaded', function () {
	if ( get_option( 'smss_db_version' ) !== SMSS_DB_VERSION ) {
		SMSS_DB::install();
	}
} );

register_activation_hook( __FILE__, function () {
	SMSS_DB::install();
	SMSS_Endpoint::add_rewrite_rules();
	SMSS_App::add_rewrite_rules();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'smss_sync_folders' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'smss_sync_folders' );
	}
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'smss_sync_folders' );
	flush_rewrite_rules();
} );
