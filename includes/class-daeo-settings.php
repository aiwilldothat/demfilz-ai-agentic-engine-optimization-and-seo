<?php
/**
 * Plugin settings storage.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the daeo_settings option.
 */
class DAEO_Settings {

	const OPTION = 'daeo_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'post_types'          => array( 'post', 'page', 'product' ),
			'module_meta'         => 1,
			'module_social'       => 1,
			'module_schema'       => 1,
			'module_sitemap'      => 1,
			'module_llms'         => 1,
			'module_md'           => 1,
			'org_name'            => '',
			'org_logo'            => '',
			'org_sameas'          => '',
			'social_image'        => '',
			'twitter_handle'      => '',
			'llms_summary'        => '',
			'llms_max'            => 100,
			'title_site_name'     => 0,
			'bots'                => array(),
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * One setting.
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Fallback when unset.
	 * @return mixed
	 */
	public static function get( $key, $fallback = '' ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Persist settings.
	 *
	 * @param array $settings Settings to store.
	 * @return void
	 */
	public static function save( $settings ) {
		update_option( self::OPTION, array_merge( self::all(), (array) $settings ) );
	}

	/**
	 * The post types the plugin manages, filtered to those that exist and
	 * are public.
	 *
	 * @return array Post type names.
	 */
	public static function post_types() {
		$chosen = (array) self::get( 'post_types', array() );
		$public = get_post_types( array( 'public' => true ), 'names' );
		$out    = array();
		foreach ( $chosen as $pt ) {
			if ( isset( $public[ $pt ] ) || in_array( $pt, $public, true ) ) {
				$out[] = $pt;
			}
		}
		return $out;
	}

	/**
	 * Whether a module toggle is on.
	 *
	 * @param string $module Module key without prefix, e.g. 'meta'.
	 * @return bool
	 */
	public static function module_on( $module ) {
		return (bool) absint( self::get( 'module_' . $module, 0 ) );
	}

	/**
	 * The organization name (falls back to the site name).
	 *
	 * @return string
	 */
	public static function org_name() {
		$name = trim( (string) self::get( 'org_name' ) );
		return '' !== $name ? $name : get_bloginfo( 'name' );
	}

	/**
	 * The site summary used for llms.txt and the front-page description
	 * (falls back to the site tagline).
	 *
	 * @return string
	 */
	public static function site_summary() {
		$summary = trim( (string) self::get( 'llms_summary' ) );
		return '' !== $summary ? $summary : (string) get_bloginfo( 'description' );
	}

	/**
	 * The sameAs URLs, one per stored line.
	 *
	 * @return array
	 */
	public static function same_as() {
		$out = array();
		foreach ( preg_split( '/[\r\n]+/', (string) self::get( 'org_sameas' ) ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line && preg_match( '#^https?://#i', $line ) ) {
				$out[] = $line;
			}
		}
		return $out;
	}
}
