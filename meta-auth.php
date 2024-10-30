<?php

/**
 * Plugin Name: Meta Auth
 * Description: 2FA authentication for WordPress using Web3 and browser wallets.
 * Author: Adastracrypto.com
 * Version: 1.3.2
 * Author URI: https://adastracrypto.com
 * Text Domain: meta-auth
 */

// Useful constants.
define('META_AUTH_VER', '1.3.2');
define('META_AUTH_DIR', __DIR__ . '/');
define('META_AUTH_URI', plugins_url('/', __FILE__));
define('AUTH_PLUGIN', "meta-auth");
define('AUTH_TABLE', "meta_auth_sessions");

// Autoload vendors
require __DIR__ . '/vendor/autoload.php';

/**
 * Do activation
 *
 * @see https://developer.wordpress.org/reference/functions/register_activation_hook/
 */
function meta_auth_activate($network)
{
	global $wpdb;

	try {
		if (version_compare(PHP_VERSION, '7.2', '<')) {
			throw new Exception(__('Meta Auth requires PHP version 7.2 at least!', AUTH_PLUGIN));
		}

		if (version_compare($GLOBALS['wp_version'], '4.6.0', '<')) {
			throw new Exception(__('Meta Auth requires WordPress 4.6.0 at least!', AUTH_PLUGIN));
		}

		if (!get_option('meta_auth_activated') && !get_transient('meta_auth_init_activation') && !set_transient('meta_auth_init_activation', 1)) {
			throw new Exception(__('Failed to initialize setup wizard.', AUTH_PLUGIN));
		}

		if (!function_exists('dbDelta')) {
			require ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$wpdb->query('DROP TABLE IF EXISTS meta_auth_sessions;');

		dbDelta("CREATE TABLE IF NOT EXISTS meta_auth_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ip VARCHAR(32) NOT NULL DEFAULT '',
			agent VARCHAR(512) NOT NULL DEFAULT '',
			link VARCHAR(255) NOT NULL DEFAULT '',
			email varchar(126) NULL,
			balance VARCHAR(32) NOT NULL DEFAULT '',
			wallet_type VARCHAR(16) NOT NULL DEFAULT '0',
			wallet_address VARCHAR(126) NOT NULL DEFAULT '',
			visited_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			synced TINYINT DEFAULT 0,
			PRIMARY KEY  (id)
		);");

		$wpdb->query('CREATE TABLE IF NOT EXISTS meta_wallet_connections (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			plugin_name VARCHAR(255) NOT NULL,
			session_table VARCHAR(255) NOT NULL,
			session_id INT NOT NULL,
			wallet_address VARCHAR(126) NOT NULL,
			ticker VARCHAR(16) NOT NULL,
			wallet_type VARCHAR(16) NOT NULL
		)');

		Januus\WP\Api::setupKeypair();

		if (!wp_next_scheduled('meta_auth_sync_data')) {

			if (!wp_schedule_event(time(), 'every_sixty_minutes', 'meta_auth_sync_data')) {
				throw new Exception(__('Failed to connect to remote server!', AUTH_PLUGIN));
			}
		}
	} catch (Exception $e) {
		if (defined('DOING_AJAX') && DOING_AJAX) { // Someone may install it via AJAX
			header('Content-Type:application/json;charset=' . get_option('blog_charset'));
			status_header(500);
			exit(json_encode([
				'success' => false,
				'name' => __('Failed To Activate Meta Auth', AUTH_PLUGIN),
				'message' => $e->getMessage(),
			]));
		} else {
			exit($e->getMessage());
		}
	}
}
add_action('activate_meta-auth/meta-auth.php', 'meta_auth_activate');

function run_every_sixty_minute($schedules)
{
	$schedules['every_sixty_minutes'] = array(
		'interval' => 3600,
		'display' => __('Every 60 Minutes', 'textdomain')
	);
	return $schedules;
}

add_filter('cron_schedules', 'run_every_sixty_minute');
/**
 * Do installation
 *
 * @see https://developer.wordpress.org/reference/hooks/plugins_loaded/
 */
function meta_auth_install()
{
	load_plugin_textdomain(AUTH_PLUGIN, false, 'meta-auth/languages');

	require __DIR__ . '/common/functions.php';
	require __DIR__ . '/common/shortcode.php';
	require __DIR__ . '/common/hooks.php';

	if (is_admin()) {
		require __DIR__ . '/admin/class-terms-page.php';
		require __DIR__ . '/admin/class-settings-page.php';
		require __DIR__ . '/admin/class-plugin-activation.php';
		require __DIR__ . '/admin/hooks.php';
	} else {
		require __DIR__ . '/frontend/hooks.php';
	}
}
add_action('plugins_loaded', 'meta_auth_install', 10, 0);

/*
	   |--------------------------------------------------------------------------
	   |  admin noticce for add infura project key
	   |--------------------------------------------------------------------------
		*/

function meta_auth_admin_notice_warn()
{
	$settings = (array) get_option('meta_auth_settings');

	if (!isset($settings['infura_project_id']) && empty($settings['infura_project_id'])) {
		echo '<div class="notice notice-error is-dismissible">
				<p>Important:Please enter an infura API-KEY for WalletConnect to work <a style="font-weight:bold" href="' . esc_url(get_admin_url(null, 'admin.php?page=meta-auth-settings')) . '">Link</a></p>
				</div>';
	}
}
if (is_admin()) {
	add_action('admin_notices', 'meta_auth_admin_notice_warn');
}