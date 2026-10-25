<?php
/**
 * Schema.org JSON-LD output: WebSite, Organization, Article,
 * BreadcrumbList and FAQPage.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and prints the JSON-LD graph for the current view.
 */
class DAEO_Schema {

	/**
	 * Hook the output.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_schema' ), 6 );
	}

	/**
	 * The JSON-LD graph for the current view.
	 *
	 * @return array
	 */
	public static function graph() {
		$graph = array();

		if ( is_front_page() ) {
			$graph[] = self::website();
		}

		// The Organization node is included on the front page AND on every
		// singular page: the Article node references it as publisher, and a
		// dangling @id reference breaks rich results.
		if ( is_front_page() || is_singular() ) {
			$graph[] = self::organization();
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				if ( 'post' === $post->post_type ) {
					$graph[] = self::article( $post );
				}
				$breadcrumbs = self::breadcrumbs();
				if ( ! empty( $breadcrumbs ) ) {
					$graph[] = $breadcrumbs;
				}
				$faq = self::faq_page( $post->ID );
				if ( ! empty( $faq ) ) {
					$graph[] = $faq;
				}
			}
		}

		return array_values( array_filter( $graph ) );
	}

	/**
	 * Print the graph as a single JSON-LD script tag.
	 *
	 * @return void
	 */
	public static function print_schema() {
		$graph = self::graph();
		if ( empty( $graph ) ) {
			return;
		}
		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
		// wp_json_encode output is safe for a script context: it escapes
		// forward slashes, so a closing </script> can never appear.
		echo '<script type="application/ld+json">' . wp_json_encode( $data ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * WebSite node with a SearchAction.
	 *
	 * @return array
	 */
	public static function website() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#website' ),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'description'     => DAEO_Settings::site_summary(),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	/**
	 * Organization node.
	 *
	 * @return array
	 */
	public static function organization() {
		$org = array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => DAEO_Settings::org_name(),
			'url'   => home_url( '/' ),
		);

		$logo = trim( (string) DAEO_Settings::get( 'org_logo' ) );
		if ( '' !== $logo ) {
			$org['logo'] = array(
				'@type' => 'ImageObject',
				'url'   => $logo,
			);
		}

		$same_as = DAEO_Settings::same_as();
		if ( ! empty( $same_as ) ) {
			$org['sameAs'] = $same_as;
		}

		return $org;
	}

	/**
	 * Article node for a post.
	 *
	 * @param WP_Post $post The post.
	 * @return array
	 */
	public static function article( $post ) {
		$custom_title = (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_TITLE, true );

		$article = array(
			'@type'            => 'Article',
			'headline'         => '' !== $custom_title ? $custom_title : get_the_title( $post ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'mainEntityOfPage' => (string) get_permalink( $post ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
			),
			'publisher'        => array(
				'@id' => home_url( '/#organization' ),
			),
		);

		if ( has_post_thumbnail( $post ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( $post ), 'large' );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$article['image'] = (string) $src[0];
			}
		}

		return $article;
	}

	/**
	 * BreadcrumbList node for the current view.
	 *
	 * @return array Empty when the trail is trivial.
	 */
	public static function breadcrumbs() {
		$trail = DAEO_Breadcrumbs::trail();
		if ( count( $trail ) < 2 ) {
			return array();
		}

		$items = array();
		foreach ( $trail as $i => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb['name'],
			);
			if ( '' !== $crumb['url'] ) {
				$item['item'] = $crumb['url'];
			}
			$items[] = $item;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		);
	}

	/**
	 * FAQPage node for a post's FAQ items.
	 *
	 * @param int $post_id Post ID.
	 * @return array Empty when the post has no FAQ items.
	 */
	public static function faq_page( $post_id ) {
		$items = DAEO_Faq::items( $post_id );
		if ( empty( $items ) ) {
			return array();
		}

		// Google requires FAQ schema to match visible content: only emit it
		// when the FAQ section is actually rendered on the page (auto-append
		// on, or the shortcode present in the content).
		if ( ! DAEO_Faq::is_visible( $post_id ) ) {
			return array();
		}

		$questions = array();
		foreach ( $items as $item ) {
			$questions[] = array(
				'@type'          => 'Question',
				'name'           => $item['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_kses_post( $item['a'] ),
				),
			);
		}

		return array(
			'@type'      => 'FAQPage',
			'mainEntity' => $questions,
		);
	}
}
