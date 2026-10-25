<?php
/**
 * Plugin orchestrator.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots every component according to the module settings.
 */
class DAEO_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var DAEO_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get or create the instance.
	 *
	 * @return DAEO_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire the components.
	 */
	private function __construct() {
		DAEO_Meta_Box::init();
		DAEO_Faq::init();
		DAEO_Breadcrumbs::init();
		DAEO_Analysis::init();
		DAEO_Head::init();
		DAEO_Robots::init();

		if ( DAEO_Settings::module_on( 'schema' ) ) {
			DAEO_Schema::init();
		}
		if ( DAEO_Settings::module_on( 'sitemap' ) ) {
			DAEO_Sitemap::init();
		}
		if ( DAEO_Settings::module_on( 'llms' ) || DAEO_Settings::module_on( 'md' ) ) {
			DAEO_Llms::init();
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ) );

		if ( is_admin() ) {
			DAEO_Admin::init();
		}
	}

	/**
	 * Small front-end stylesheet for the FAQ accordion and breadcrumbs.
	 *
	 * @return void
	 */
	public static function front_assets() {
		if ( ! is_singular() ) {
			return;
		}
		$post_id = get_queried_object_id();
		if ( ! $post_id ) {
			return;
		}
		$post       = get_post( $post_id );
		$content    = $post instanceof WP_Post ? (string) $post->post_content : '';
		$has_faq    = count( DAEO_Faq::items( $post_id ) ) > 0 || has_shortcode( $content, 'daeo_faq' );
		$has_crumbs = has_shortcode( $content, 'daeo_breadcrumbs' );
		if ( ! $has_faq && ! $has_crumbs ) {
			return;
		}
		wp_enqueue_style(
			'daeo-public',
			DAEO_URL . 'public/css/daeo-public.css',
			array(),
			DAEO_VERSION
		);
	}
}
