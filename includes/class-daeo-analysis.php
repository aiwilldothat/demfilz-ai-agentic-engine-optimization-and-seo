<?php
/**
 * Content analysis for the optimization suggestions dashboard.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Scores a post 0-100 against on-page SEO/AEO checks.
 */
class DAEO_Analysis {

	const META_SCORE  = '_daeo_score';
	const META_RESULT = '_daeo_analysis';

	/**
	 * Hook cache invalidation.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'invalidate' ) );
	}

	/**
	 * Drop the cached analysis when a post changes.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function invalidate( $post_id ) {
		delete_post_meta( $post_id, self::META_SCORE );
		delete_post_meta( $post_id, self::META_RESULT );
	}

	/**
	 * The cached analysis for a post, computing it when missing.
	 *
	 * @param int $post_id Post ID.
	 * @return array { score: int, issues: string[], passed: string[] }
	 */
	public static function get( $post_id ) {
		$cached = get_post_meta( $post_id, self::META_RESULT, true );
		if ( is_array( $cached ) && isset( $cached['score'], $cached['issues'], $cached['passed'] ) ) {
			return $cached;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return array(
				'score'  => 0,
				'issues' => array(),
				'passed' => array(),
			);
		}

		$result = self::analyze( $post );
		update_post_meta( $post_id, self::META_RESULT, $result );
		update_post_meta( $post_id, self::META_SCORE, $result['score'] );
		return $result;
	}

	/**
	 * The checks: key => label, weight, callback( context ) => bool|null.
	 * Returning null skips the check (not applicable).
	 *
	 * @return array
	 */
	public static function checks() {
		return array(
			'title'          => array(
				'weight' => 15,
				'label'  => __( 'Meta title set', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'No meta title. Add one in the AI SEO box.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					return '' !== $ctx['title'];
				},
			),
			'title_length'   => array(
				'weight' => 10,
				'label'  => __( 'Meta title 30-60 characters', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'Meta title length is outside 30-60 characters.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					if ( '' === $ctx['title'] ) {
						return null;
					}
					$len = mb_strlen( $ctx['title'] );
					return $len >= 30 && $len <= 60;
				},
			),
			'desc'           => array(
				'weight' => 15,
				'label'  => __( 'Meta description set', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'No meta description. Add one in the AI SEO box.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					return '' !== $ctx['desc'];
				},
			),
			'desc_length'    => array(
				'weight' => 10,
				'label'  => __( 'Meta description 70-160 characters', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'Meta description length is outside 70-160 characters.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					if ( '' === $ctx['desc'] ) {
						return null;
					}
					$len = mb_strlen( $ctx['desc'] );
					return $len >= 70 && $len <= 160;
				},
			),
			'content_length' => array(
				'weight' => 10,
				'label'  => __( 'At least 300 words of content', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'Thin content: fewer than 300 words.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					return $ctx['words'] >= 300;
				},
			),
			'headings'       => array(
				'weight' => 10,
				'label'  => __( 'Uses H2 subheadings', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'No H2 subheadings found in the content.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					if ( $ctx['words'] < 150 ) {
						return null;
					}
					return $ctx['h2_count'] > 0;
				},
			),
			'single_h1'      => array(
				'weight' => 5,
				'label'  => __( 'No extra H1 headings in the content', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'The content contains H1 headings; the page title is already the H1.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					return 0 === $ctx['h1_count'];
				},
			),
			'image_alt'      => array(
				'weight' => 10,
				'label'  => __( 'All images have alt text', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'Some content images are missing alt text.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					if ( 0 === $ctx['img_count'] ) {
						return null;
					}
					return 0 === $ctx['img_missing_alt'];
				},
			),
			'internal_links' => array(
				'weight' => 10,
				'label'  => __( 'Links to other pages on this site', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'No internal links found. Link to related pages.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					if ( $ctx['words'] < 150 ) {
						return null;
					}
					return $ctx['internal_links'] > 0;
				},
			),
			'featured_image' => array(
				'weight' => 5,
				'label'  => __( 'Featured image set', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'No featured image. Social shares and AI previews look bare without one.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					return $ctx['has_thumbnail'];
				},
			),
			'faq'            => array(
				'weight' => 10,
				'label'  => __( 'Has FAQ items with schema', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'issue'  => __( 'No FAQ items. Question-and-answer content is what AI assistants quote most.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'test'   => function ( $ctx ) {
					return $ctx['faq_count'] > 0;
				},
			),
		);
	}

	/**
	 * Analyze a post.
	 *
	 * @param WP_Post $post The post.
	 * @return array { score: int, issues: string[], passed: string[] }
	 */
	public static function analyze( $post ) {
		$content = (string) $post->post_content;
		$text    = wp_strip_all_tags( strip_shortcodes( $content ) );

		preg_match_all( '/<h1[\s>]/i', $content, $h1s );
		preg_match_all( '/<h2[\s>]/i', $content, $h2s );
		preg_match_all( '/<img\b[^>]*>/i', $content, $imgs );

		$missing_alt = 0;
		foreach ( $imgs[0] as $img ) {
			if ( ! preg_match( '/\balt\s*=\s*("[^"]+"|\'[^\']+\')/i', $img ) ) {
				++$missing_alt;
			}
		}

		$home_host      = (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		$internal_links = 0;
		if ( preg_match_all( '/<a\b[^>]*href\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $content, $links ) ) {
			foreach ( $links[0] as $i => $unused ) {
				$href = '' !== $links[2][ $i ] ? $links[2][ $i ] : $links[3][ $i ];
				if ( '' === $href || '#' === substr( $href, 0, 1 ) ) {
					continue;
				}
				$host = (string) wp_parse_url( $href, PHP_URL_HOST );
				if ( '' === $host || strtolower( $host ) === strtolower( $home_host ) ) {
					++$internal_links;
				}
			}
		}

		$ctx = array(
			'title'           => (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_TITLE, true ),
			'desc'            => (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_DESC, true ),
			'words'           => str_word_count( $text ),
			'h1_count'        => count( $h1s[0] ),
			'h2_count'        => count( $h2s[0] ),
			'img_count'       => count( $imgs[0] ),
			'img_missing_alt' => $missing_alt,
			'internal_links'  => $internal_links,
			'has_thumbnail'   => has_post_thumbnail( $post ),
			'faq_count'       => count( DAEO_Faq::items( $post->ID ) ),
		);

		$issues       = array();
		$passed       = array();
		$total_weight = 0;
		$earned       = 0;

		foreach ( self::checks() as $check ) {
			$result = call_user_func( $check['test'], $ctx );
			if ( null === $result ) {
				continue;
			}
			$total_weight += $check['weight'];
			if ( $result ) {
				$earned  += $check['weight'];
				$passed[] = $check['label'];
			} else {
				$issues[] = $check['issue'];
			}
		}

		return array(
			'score'  => $total_weight > 0 ? (int) round( 100 * $earned / $total_weight ) : 0,
			'issues' => $issues,
			'passed' => $passed,
		);
	}
}
