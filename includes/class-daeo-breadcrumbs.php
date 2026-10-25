<?php
/**
 * Breadcrumb trail builder, shortcode and schema source.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the breadcrumb trail for the current view.
 */
class DAEO_Breadcrumbs {

	/**
	 * Hook the shortcode.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'daeo_breadcrumbs', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * The breadcrumb trail: arrays of name + url (last item = current, no url).
	 *
	 * @return array
	 */
	public static function trail() {
		$trail   = array();
		$trail[] = array(
			'name' => __( 'Home', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			'url'  => home_url( '/' ),
		);

		if ( is_front_page() ) {
			return array( $trail[0] );
		}

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof WP_Post ) {
				return $trail;
			}

			if ( 'post' === $post->post_type ) {
				$cats = get_the_category( $post->ID );
				if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
					$trail[] = array(
						'name' => $cats[0]->name,
						'url'  => (string) get_category_link( $cats[0] ),
					);
				}
			} elseif ( 'page' === $post->post_type ) {
				foreach ( array_reverse( get_post_ancestors( $post ) ) as $ancestor_id ) {
					$trail[] = array(
						'name' => get_the_title( $ancestor_id ),
						'url'  => (string) get_permalink( $ancestor_id ),
					);
				}
			} else {
				$pt_object = get_post_type_object( $post->post_type );
				$archive   = get_post_type_archive_link( $post->post_type );
				if ( $pt_object && $archive ) {
					$trail[] = array(
						'name' => $pt_object->labels->name,
						'url'  => (string) $archive,
					);
				}
			}

			$trail[] = array(
				'name' => get_the_title( $post ),
				'url'  => '',
			);
		}

		return $trail;
	}

	/**
	 * [daeo_breadcrumbs] shortcode.
	 *
	 * @return string
	 */
	public static function shortcode() {
		$trail = self::trail();
		if ( count( $trail ) < 2 ) {
			return '';
		}

		$html = '<nav class="daeo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '"><ol>';
		foreach ( $trail as $i => $crumb ) {
			$html .= '<li>';
			if ( '' !== $crumb['url'] && count( $trail ) - 1 !== $i ) {
				$html .= '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['name'] ) . '</a>';
			} else {
				$html .= '<span aria-current="page">' . esc_html( $crumb['name'] ) . '</span>';
			}
			$html .= '</li>';
		}
		$html .= '</ol></nav>';
		return $html;
	}
}
