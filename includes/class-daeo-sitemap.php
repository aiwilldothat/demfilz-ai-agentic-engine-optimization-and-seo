<?php
/**
 * Virtual XML sitemap at /sitemap.xml.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serves the sitemap without writing any file to disk.
 */
class DAEO_Sitemap {

	const MAX_URLS = 5000;

	/**
	 * Hook the interception.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
		// Avoid duplicate sitemaps: core's /wp-sitemap.xml steps aside while
		// this module provides /sitemap.xml.
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
	}

	/**
	 * Serve the sitemap when /sitemap.xml is requested.
	 *
	 * @return void
	 */
	public static function maybe_serve() {
		if ( '/sitemap.xml' !== DAEO_Llms::request_path() ) {
			return;
		}
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: application/xml; charset=UTF-8' );
			header( 'X-Robots-Tag: noindex' );
		}
		// The body is XML built exclusively from esc_url/esc_html parts.
		echo self::build(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped during build.
		exit;
	}

	/**
	 * The published, indexable content entries for the sitemap and LLM files.
	 *
	 * @param int $limit Maximum number of posts.
	 * @return WP_Post[]
	 */
	public static function content_posts( $limit ) {
		$posts = get_posts(
			array(
				'post_type'        => DAEO_Settings::post_types(),
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'has_password'     => false,
				'suppress_filters' => false,
			)
		);

		$out = array();
		foreach ( $posts as $post ) {
			if ( '1' === (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_NOINDEX, true ) ) {
				continue;
			}
			$out[] = $post;
		}
		return $out;
	}

	/**
	 * Build the sitemap XML.
	 *
	 * @return string
	 */
	public static function build() {
		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		$latest = get_lastpostmodified( 'gmt' );
		$xml   .= self::url_entry( home_url( '/' ), $latest ? gmdate( 'c', strtotime( $latest ) ) : '' );

		foreach ( self::content_posts( self::MAX_URLS ) as $post ) {
			$permalink = (string) get_permalink( $post );
			if ( '' === $permalink || untrailingslashit( $permalink ) === untrailingslashit( home_url( '/' ) ) ) {
				continue;
			}
			$xml .= self::url_entry( $permalink, get_post_modified_time( 'c', true, $post ) );
		}

		$xml .= '</urlset>' . "\n";
		return $xml;
	}

	/**
	 * One url entry.
	 *
	 * @param string $loc     URL.
	 * @param string $lastmod ISO date ('' to omit).
	 * @return string
	 */
	private static function url_entry( $loc, $lastmod ) {
		$entry = "\t<url>\n\t\t<loc>" . esc_url( $loc ) . "</loc>\n";
		if ( '' !== (string) $lastmod ) {
			$entry .= "\t\t<lastmod>" . esc_html( (string) $lastmod ) . "</lastmod>\n";
		}
		$entry .= "\t</url>\n";
		return $entry;
	}
}
