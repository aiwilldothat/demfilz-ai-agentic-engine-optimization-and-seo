<?php
/**
 * Plugin Name:       Demfilz AI Agentic Engine Optimization and SEO
 * Plugin URI:        https://demfilz.com/ai-seo
 * Description:       Classic SEO plus agentic engine optimization: meta titles and descriptions, FAQ schema, XML sitemap, llms.txt and markdown mirrors for AI assistants, AI crawler controls and an optimization dashboard.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Demfilz
 * Author URI:        https://demfilz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       demfilz-ai-agentic-engine-optimization-and-seo
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

define( 'DAEO_VERSION', '1.0.0' );
define( 'DAEO_FILE', __FILE__ );
define( 'DAEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'DAEO_URL', plugin_dir_url( __FILE__ ) );
define( 'DAEO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 * The plugin never touches orders, so it is fully compatible.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DAEO_FILE, true );
		}
	}
);

/**
 * Boot the plugin once all plugins are loaded. WooCommerce is optional:
 * product support lights up when it is active.
 */
add_action(
	'plugins_loaded',
	function () {
		require_once DAEO_PATH . 'includes/class-daeo-settings.php';
		require_once DAEO_PATH . 'includes/class-daeo-markdown.php';
		require_once DAEO_PATH . 'includes/class-daeo-meta-box.php';
		require_once DAEO_PATH . 'includes/class-daeo-head.php';
		require_once DAEO_PATH . 'includes/class-daeo-breadcrumbs.php';
		require_once DAEO_PATH . 'includes/class-daeo-faq.php';
		require_once DAEO_PATH . 'includes/class-daeo-schema.php';
		require_once DAEO_PATH . 'includes/class-daeo-robots.php';
		require_once DAEO_PATH . 'includes/class-daeo-sitemap.php';
		require_once DAEO_PATH . 'includes/class-daeo-llms.php';
		require_once DAEO_PATH . 'includes/class-daeo-analysis.php';
		require_once DAEO_PATH . 'includes/class-daeo-admin.php';
		require_once DAEO_PATH . 'includes/class-daeo-plugin.php';

		DAEO_Plugin::instance();
	}
);

/**
 * Activation: seed default options.
 */
register_activation_hook(
	__FILE__,
	function () {
		require_once DAEO_PATH . 'includes/class-daeo-settings.php';
		if ( false === get_option( 'daeo_settings', false ) ) {
			add_option( 'daeo_settings', DAEO_Settings::defaults() );
		}
	}
);
