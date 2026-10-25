<?php
/**
 * LLM files: /llms.txt, /llms-full.txt and per-page Markdown mirrors.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serves the llms.txt index, the full-content file and .md page mirrors.
 */
class DAEO_Llms {

	const FULL_MAX     = 50;
	const META_PIN     = '_daeo_llms_pin';
	const META_EXCLUDE = '_daeo_llms_exclude';

	/**
	 * True while a markdown mirror is being rendered (lets other components
	 * skip loop-only behaviour like the FAQ auto-append).
	 *
	 * @var bool
	 */
	private static $rendering = false;

	/**
	 * Whether a markdown mirror render is in progress.
	 *
	 * @return bool
	 */
	public static function is_rendering() {
		return self::$rendering;
	}

	/**
	 * Hook the interception.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	/**
	 * The current request path (no query string, leading slash, decoded).
	 *
	 * @return string
	 */
	public static function request_path() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '' === $path ) {
			return '/';
		}
		// Strip a subdirectory installation prefix so /blog/llms.txt matches.
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '/' !== $home_path && 0 === strpos( $path, untrailingslashit( $home_path ) ) ) {
			$path = substr( $path, strlen( untrailingslashit( $home_path ) ) );
		}
		return '' === $path ? '/' : rawurldecode( $path );
	}

	/**
	 * Serve llms.txt, llms-full.txt or a markdown mirror when requested.
	 *
	 * @return void
	 */
	public static function maybe_serve() {
		if ( DAEO_Settings::module_on( 'llms' ) ) {
			$path = self::request_path();
			if ( '/llms.txt' === $path ) {
				self::output( self::build_index() );
			}
			if ( '/llms-full.txt' === $path ) {
				self::output( self::build_full() );
			}
		}

		if ( DAEO_Settings::module_on( 'md' ) ) {
			$post = self::requested_md_post();
			if ( $post instanceof WP_Post ) {
				self::output( self::markdown( $post ) );
			}
		}
	}

	/**
	 * Resolve the post for a markdown mirror request, if this is one.
	 *
	 * Two request forms are supported: <permalink>.md (pretty permalinks)
	 * and ?daeo_md=<id> (always available).
	 *
	 * @return WP_Post|null
	 */
	public static function requested_md_post() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public read-only endpoint.
		$id = isset( $_GET['daeo_md'] ) ? absint( wp_unslash( $_GET['daeo_md'] ) ) : 0;
		if ( $id ) {
			return self::mirrorable_post( $id );
		}

		$path = self::request_path();
		if ( '.md' !== substr( $path, -3 ) ) {
			return null;
		}
		$clean = substr( $path, 0, -3 );
		if ( '' === trim( $clean, '/' ) ) {
			return null;
		}

		$post_id = url_to_postid( home_url( user_trailingslashit( $clean ) ) );
		if ( ! $post_id ) {
			$post_id = url_to_postid( home_url( $clean ) );
		}
		if ( ! $post_id ) {
			$page = get_page_by_path( trim( $clean, '/' ), OBJECT, DAEO_Settings::post_types() );
			if ( $page instanceof WP_Post ) {
				$post_id = $page->ID;
			}
		}

		return $post_id ? self::mirrorable_post( $post_id ) : null;
	}

	/**
	 * The post when it is published, an enabled type, not noindexed and not
	 * excluded from the AI files.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	private static function mirrorable_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
			return null;
		}
		if ( ! in_array( $post->post_type, DAEO_Settings::post_types(), true ) ) {
			return null;
		}
		if ( '1' === (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_NOINDEX, true ) ) {
			return null;
		}
		if ( '1' === (string) get_post_meta( $post->ID, self::META_EXCLUDE, true ) ) {
			return null;
		}

		/**
		 * Whether a post may be mirrored into llms.txt / .md output.
		 * Membership and paywall plugins can hook this to exclude gated posts.
		 *
		 * @param bool    $mirrorable Whether the post may be mirrored.
		 * @param WP_Post $post       The post.
		 */
		if ( ! apply_filters( 'daeo_is_mirrorable', true, $post ) ) {
			return null;
		}
		return $post;
	}

	/**
	 * Public check used by other components (e.g. the rel=alternate link).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_mirrorable( $post_id ) {
		return null !== self::mirrorable_post( $post_id );
	}

	/**
	 * The markdown mirror URL for a post.
	 *
	 * @param WP_Post $post The post.
	 * @return string
	 */
	public static function md_url( $post ) {
		$permalink = untrailingslashit( (string) get_permalink( $post ) );
		// The static front page's permalink is the bare home URL; appending
		// .md there would produce an invalid host, so use the query form.
		// got_url_rewrite() is admin-only; permalink_structure works everywhere.
		if ( '' !== (string) get_option( 'permalink_structure' ) && untrailingslashit( home_url( '/' ) ) !== $permalink ) {
			return $permalink . '.md';
		}
		return add_query_arg( 'daeo_md', (string) $post->ID, home_url( '/' ) );
	}

	/**
	 * Render a post's content through the_content the way a public visitor
	 * would see it: with the global post and query flags set up so
	 * restriction plugins (membership, paywall) judge the RIGHT post and
	 * actually engage. Without this, raw content leaks and filters can act
	 * on whichever post the main query happened to hold.
	 *
	 * @param WP_Post $post The post.
	 * @return string Rendered HTML.
	 */
	public static function rendered_content( $post ) {
		global $wp_query;

		self::$rendering = true;

		$prev_post  = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$prev_flags = array();
		$flags      = array(
			'in_the_loop'       => true,
			'is_singular'       => true,
			'is_single'         => 'page' !== $post->post_type && 'attachment' !== $post->post_type,
			'is_page'           => 'page' === $post->post_type,
			'is_home'           => false,
			'is_404'            => false,
			'queried_object'    => $post,
			'queried_object_id' => (int) $post->ID,
		);

		$has_query = isset( $wp_query ) && is_object( $wp_query );
		if ( $has_query ) {
			foreach ( $flags as $key => $value ) {
				$prev_flags[ $key ] = isset( $wp_query->$key ) ? $wp_query->$key : null;
				$wp_query->$key     = $value;
			}
		}

		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- deliberate loop simulation, restored below.
		if ( function_exists( 'setup_postdata' ) ) {
			setup_postdata( $post );
		}

		/**
		 * Filters the mirrored content before conversion to Markdown.
		 *
		 * @param string  $content Rendered HTML.
		 * @param WP_Post $post    The post.
		 */
		$html = apply_filters(
			'daeo_mirror_content',
			apply_filters( 'the_content', $post->post_content ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- applying the core content filter, not defining a hook.
			$post
		);

		if ( $has_query ) {
			foreach ( $prev_flags as $key => $value ) {
				$wp_query->$key = $value;
			}
		}
		$GLOBALS['post'] = $prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring the original global.
		if ( $prev_post instanceof WP_Post && function_exists( 'setup_postdata' ) ) {
			setup_postdata( $prev_post );
		} elseif ( function_exists( 'wp_reset_postdata' ) ) {
			wp_reset_postdata();
		}

		self::$rendering = false;
		return (string) $html;
	}

	/**
	 * Send a plain-text response and stop.
	 *
	 * @param string $body Response body.
	 * @return void
	 */
	private static function output( $body ) {
		if ( ! headers_sent() ) {
			status_header( 200 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Robots-Tag: noindex' );
		}
		// Plain-text (markdown) response; HTML escaping would corrupt it and
		// the builders already strip markup.
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain body built from sanitised parts.
		exit;
	}

	/**
	 * Build the llms.txt index.
	 *
	 * @return string
	 */
	public static function build_index() {
		$limit = max( 10, absint( DAEO_Settings::get( 'llms_max', 100 ) ) );
		$posts = self::listable_posts( $limit );

		$sections = array();
		foreach ( $posts as $post ) {
			$sections[ $post->post_type ][] = $post;
		}

		$out  = '# ' . self::plain( get_bloginfo( 'name' ) ) . "\n\n";
		$sum  = self::plain( DAEO_Settings::site_summary() );
		$out .= '' !== $sum ? '> ' . $sum . "\n\n" : '';
		$out .= 'This file follows the llms.txt convention and lists the key pages of ' . home_url( '/' ) . " for AI assistants.\n";
		if ( DAEO_Settings::module_on( 'md' ) ) {
			$out .= "Each page links to a clean Markdown mirror of its content.\n";
		}
		$out .= "\n";

		$labels = array(
			'page'    => 'Pages',
			'post'    => 'Posts',
			'product' => 'Products',
		);

		foreach ( $sections as $type => $items ) {
			$pt_object = get_post_type_object( $type );
			$label     = isset( $labels[ $type ] ) ? $labels[ $type ] : ( $pt_object ? (string) $pt_object->labels->name : $type );
			$out      .= '## ' . $label . "\n\n";
			foreach ( $items as $post ) {
				$url  = DAEO_Settings::module_on( 'md' ) ? self::md_url( $post ) : (string) get_permalink( $post );
				$desc = self::plain( self::post_summary( $post ) );
				$out .= '- [' . self::plain( get_the_title( $post ) ) . '](' . $url . ')';
				$out .= '' !== $desc ? ': ' . $desc : '';
				$out .= "\n";
			}
			$out .= "\n";
		}

		if ( DAEO_Settings::module_on( 'llms' ) ) {
			$out .= "## Optional\n\n- [Full content](" . home_url( '/llms-full.txt' ) . ")\n";
		}

		return $out;
	}

	/**
	 * The posts listed in the LLM files: sitemap content minus per-page
	 * exclusions, with pinned posts first.
	 *
	 * @param int $limit Maximum number of posts.
	 * @return WP_Post[]
	 */
	public static function listable_posts( $limit ) {
		$pinned = array();
		$rest   = array();
		foreach ( DAEO_Sitemap::content_posts( $limit ) as $post ) {
			if ( '1' === (string) get_post_meta( $post->ID, self::META_EXCLUDE, true ) ) {
				continue;
			}
			if ( ! apply_filters( 'daeo_is_mirrorable', true, $post ) ) {
				continue;
			}
			if ( '1' === (string) get_post_meta( $post->ID, self::META_PIN, true ) ) {
				$pinned[] = $post;
			} else {
				$rest[] = $post;
			}
		}
		return array_merge( $pinned, $rest );
	}

	/**
	 * Build llms-full.txt: concatenated markdown of key content.
	 *
	 * @return string
	 */
	public static function build_full() {
		$posts = self::listable_posts( self::FULL_MAX );

		$out = '# ' . self::plain( get_bloginfo( 'name' ) ) . ' - full content for AI assistants' . "\n\n";
		foreach ( $posts as $post ) {
			$out .= self::markdown( $post ) . "\n\n---\n\n";
		}
		return rtrim( $out, "-\n " ) . "\n";
	}

	/**
	 * Markdown mirror of one post.
	 *
	 * @param WP_Post $post The post.
	 * @return string
	 */
	public static function markdown( $post ) {
		$out = '# ' . self::plain( get_the_title( $post ) ) . "\n\n";

		$desc = (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_DESC, true );
		if ( '' !== $desc ) {
			$out .= '> ' . self::plain( $desc ) . "\n\n";
		}
		$out .= 'Source: ' . get_permalink( $post ) . "\n\n";

		$out .= DAEO_Markdown::convert( self::rendered_content( $post ) );

		$faqs = DAEO_Faq::items( $post->ID );
		if ( ! empty( $faqs ) ) {
			$out .= "\n\n## Frequently asked questions\n";
			foreach ( $faqs as $item ) {
				$out .= "\n### " . self::plain( $item['q'] ) . "\n\n" . self::plain( $item['a'] ) . "\n";
			}
		}

		return trim( $out ) . "\n";
	}

	/**
	 * A one-line summary of a post: the meta description, else the manual
	 * excerpt, else nothing. The raw post body is deliberately NOT used -
	 * membership/paywall plugins gate it at render time, so deriving
	 * summaries from it would leak restricted content.
	 *
	 * @param WP_Post $post The post.
	 * @return string
	 */
	private static function post_summary( $post ) {
		$desc = (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_DESC, true );
		if ( '' !== $desc ) {
			return $desc;
		}
		if ( '' !== (string) $post->post_excerpt ) {
			return wp_trim_words( wp_strip_all_tags( strip_shortcodes( (string) $post->post_excerpt ) ), 20, '...' );
		}
		return '';
	}

	/**
	 * Plain single-line text for markdown/text output: tags stripped,
	 * leftover shortcodes removed, entities decoded (this is text/plain,
	 * not HTML).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function plain( $text ) {
		$text = wp_strip_all_tags( (string) $text );
		$text = preg_replace( '/\[[^\]\[]*\]/', '', $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}
}
