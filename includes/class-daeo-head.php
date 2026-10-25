<?php
/**
 * Front-end head output: document title, meta description, canonical,
 * robots and social (Open Graph / Twitter) tags.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Emits the head tags for the meta and social modules.
 */
class DAEO_Head {

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public static function init() {
		if ( DAEO_Settings::module_on( 'meta' ) ) {
			add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_title' ), 20 );
			add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ) );
			add_action( 'wp_head', array( __CLASS__, 'print_meta' ), 1 );
			remove_action( 'wp_head', 'rel_canonical' );
		}
		if ( DAEO_Settings::module_on( 'social' ) ) {
			add_action( 'wp_head', array( __CLASS__, 'print_social' ), 4 );
		}
	}

	/**
	 * Use the custom meta title when one is stored.
	 *
	 * @param string $title Title so far (empty unless another plugin set it).
	 * @return string
	 */
	public static function filter_title( $title ) {
		if ( is_singular() ) {
			$custom = (string) get_post_meta( get_queried_object_id(), DAEO_Meta_Box::META_TITLE, true );
			if ( '' !== $custom ) {
				if ( absint( DAEO_Settings::get( 'title_site_name', 0 ) ) ) {
					$custom .= ' - ' . get_bloginfo( 'name' );
				}
				return $custom;
			}
		}
		return $title;
	}

	/**
	 * Add noindex for posts flagged in the meta box.
	 *
	 * @param array $robots Robots directives.
	 * @return array
	 */
	public static function filter_robots( $robots ) {
		if ( is_singular() && '1' === (string) get_post_meta( get_queried_object_id(), DAEO_Meta_Box::META_NOINDEX, true ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * The meta description for the current view ('' when none applies).
	 *
	 * @return string
	 */
	public static function description() {
		if ( is_front_page() ) {
			$desc = DAEO_Settings::site_summary();
			if ( is_singular() ) {
				$custom = (string) get_post_meta( get_queried_object_id(), DAEO_Meta_Box::META_DESC, true );
				if ( '' !== $custom ) {
					$desc = $custom;
				}
			}
			return $desc;
		}
		if ( is_singular() ) {
			$post   = get_queried_object();
			$custom = (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_DESC, true );
			if ( '' !== $custom ) {
				return $custom;
			}
			// Only the manual excerpt is used as a fallback. The raw post
			// body is never used: membership/paywall plugins gate it at
			// render time, so deriving a description from it would leak
			// restricted content into the page head.
			if ( '' !== (string) $post->post_excerpt ) {
				return wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_excerpt ) ), 28, '...' );
			}
		}
		return '';
	}

	/**
	 * The canonical URL for the current view ('' when none applies).
	 *
	 * @return string
	 */
	public static function canonical() {
		if ( is_singular() ) {
			$custom = (string) get_post_meta( get_queried_object_id(), DAEO_Meta_Box::META_CANONICAL, true );
			if ( '' !== $custom ) {
				return $custom;
			}
			return (string) get_permalink( get_queried_object_id() );
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		return '';
	}

	/**
	 * Print description and canonical tags.
	 *
	 * @return void
	 */
	public static function print_meta() {
		$desc = self::description();
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		$canonical = self::canonical();
		if ( '' !== $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}
		// Advertise the Markdown mirror to AI crawlers that land on the HTML.
		if ( is_singular() && DAEO_Settings::module_on( 'md' ) ) {
			$post_id = get_queried_object_id();
			if ( $post_id && DAEO_Llms::is_mirrorable( $post_id ) ) {
				echo '<link rel="alternate" type="text/markdown" href="' . esc_url( DAEO_Llms::md_url( get_post( $post_id ) ) ) . '">' . "\n";
			}
		}
	}

	/**
	 * The social image URL for the current view ('' when none).
	 *
	 * @return string
	 */
	public static function social_image() {
		if ( is_singular() && has_post_thumbnail( get_queried_object_id() ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( get_queried_object_id() ), 'large' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				return (string) $src[0];
			}
		}
		return trim( (string) DAEO_Settings::get( 'social_image' ) );
	}

	/**
	 * Print Open Graph and Twitter card tags.
	 *
	 * @return void
	 */
	public static function print_social() {
		$title = wp_get_document_title();
		$desc  = self::description();
		$url   = self::canonical();
		if ( '' === $url ) {
			return;
		}

		echo '<meta property="og:type" content="' . esc_attr( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( '' !== $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";

		$image = self::social_image();
		if ( '' !== $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
		}
		if ( is_singular( 'post' ) ) {
			echo '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', get_queried_object_id() ) ) . '">' . "\n";
			echo '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', get_queried_object_id() ) ) . '">' . "\n";
		}

		echo '<meta name="twitter:card" content="' . esc_attr( '' !== $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		$handle = trim( (string) DAEO_Settings::get( 'twitter_handle' ) );
		if ( '' !== $handle ) {
			echo '<meta name="twitter:site" content="' . esc_attr( '@' . ltrim( $handle, '@' ) ) . '">' . "\n";
		}
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( '' !== $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( '' !== $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
		}
	}
}
