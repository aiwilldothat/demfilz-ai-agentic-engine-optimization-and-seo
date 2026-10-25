<?php
/**
 * FAQ manager: per-page question/answer repeater, shortcode output and
 * the data source for the FAQPage schema.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * FAQ meta box, storage and rendering.
 */
class DAEO_Faq {

	const META_ITEMS  = '_daeo_faqs';
	const META_APPEND = '_daeo_faq_append';

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
		add_shortcode( 'daeo_faq', array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'maybe_append' ), 30 );
	}

	/**
	 * The FAQ items stored for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array Arrays with q + a keys.
	 */
	public static function items( $post_id ) {
		$items = get_post_meta( $post_id, self::META_ITEMS, true );
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $item ) {
			if ( is_array( $item ) && '' !== trim( (string) ( isset( $item['q'] ) ? $item['q'] : '' ) ) && '' !== trim( (string) ( isset( $item['a'] ) ? $item['a'] : '' ) ) ) {
				$out[] = array(
					'q' => (string) $item['q'],
					'a' => (string) $item['a'],
				);
			}
		}
		return $out;
	}

	/**
	 * Whether a post's FAQ items are actually rendered on the page
	 * (auto-append on, or the [daeo_faq] shortcode in the content).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_visible( $post_id ) {
		if ( '1' === (string) get_post_meta( $post_id, self::META_APPEND, true ) ) {
			return true;
		}
		$post = get_post( $post_id );
		return $post instanceof WP_Post && has_shortcode( (string) $post->post_content, 'daeo_faq' );
	}

	/**
	 * Register the FAQ meta box on every enabled post type.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( DAEO_Settings::post_types() as $pt ) {
			add_meta_box(
				'daeo_faq',
				__( 'FAQ with schema (AI SEO)', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				array( __CLASS__, 'render' ),
				$pt,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render the FAQ meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( 'daeo_faqs', 'daeo_faqs_nonce' );

		$items  = self::items( $post->ID );
		$append = '1' === (string) get_post_meta( $post->ID, self::META_APPEND, true );

		echo '<div class="daeo-faq-box" id="daeo-faq-box">';
		echo '<p class="description">' . esc_html__( 'Add questions and answers for this page. They can be shown with the [daeo_faq] shortcode or appended automatically, and are published as FAQPage schema so search engines and AI assistants can quote them.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';

		if ( ! empty( $items ) && ! self::is_visible( $post->ID ) ) {
			echo '<p class="daeo-faq-warning">' . esc_html__( 'These FAQs are not shown anywhere yet: tick "append automatically" below or place the [daeo_faq] shortcode in the content. The FAQPage schema is only published once they are visible.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		}

		echo '<div class="daeo-faq-rows" id="daeo-faq-rows">';
		if ( empty( $items ) ) {
			echo '<p class="daeo-faq-empty">' . esc_html__( 'No FAQ items yet. Click "Add question" to create the first one.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		}
		foreach ( $items as $index => $item ) {
			echo wp_kses( self::row_html( $index, $item['q'], $item['a'] ), self::allowed_html() );
		}
		echo '</div>';

		echo '<p><button type="button" class="button" id="daeo-faq-add">' . esc_html__( 'Add question', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</button></p>';

		echo '<p><label><input type="checkbox" name="daeo_faq_append" value="1"' . checked( $append, true, false ) . '> ' . esc_html__( 'Automatically append the FAQ section to this page content', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></p>';

		/**
		 * Fires at the end of the FAQ meta box, after the built-in fields.
		 *
		 * @param WP_Post $post The post being edited.
		 */
		do_action( 'daeo_faq_box_after', $post );

		echo '</div>';
	}

	/**
	 * One repeater row of the FAQ editor.
	 *
	 * @param int|string $index Row index (or placeholder for the JS template).
	 * @param string     $q     Question.
	 * @param string     $a     Answer.
	 * @return string
	 */
	public static function row_html( $index, $q = '', $a = '' ) {
		$html  = '<div class="daeo-faq-row">';
		$html .= '<div class="daeo-faq-row-fields">';
		$html .= '<input type="text" class="large-text daeo-faq-q" name="daeo_faqs[' . esc_attr( (string) $index ) . '][q]" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Question', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '">';
		$html .= '<textarea class="large-text daeo-faq-a" rows="3" name="daeo_faqs[' . esc_attr( (string) $index ) . '][a]" placeholder="' . esc_attr__( 'Answer', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '">' . esc_textarea( $a ) . '</textarea>';
		$html .= '</div>';
		$html .= '<button type="button" class="button-link daeo-faq-remove" aria-label="' . esc_attr__( 'Remove this question', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '">&times;</button>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Allowed HTML for the repeater rows.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'div'      => array(
				'class' => true,
				'id'    => true,
			),
			'input'    => array(
				'type'        => true,
				'class'       => true,
				'name'        => true,
				'value'       => true,
				'placeholder' => true,
			),
			'textarea' => array(
				'class'       => true,
				'rows'        => true,
				'name'        => true,
				'placeholder' => true,
			),
			'button'   => array(
				'type'       => true,
				'class'      => true,
				'aria-label' => true,
			),
		);
	}

	/**
	 * Save submitted FAQ items.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['daeo_faqs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['daeo_faqs_nonce'] ) ), 'daeo_faqs' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, DAEO_Settings::post_types(), true ) ) {
			return;
		}

		$items = array();
		if ( isset( $_POST['daeo_faqs'] ) && is_array( $_POST['daeo_faqs'] ) ) {
			// Answers may carry limited HTML; each field is sanitised below.
			$raw = wp_unslash( $_POST['daeo_faqs'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per-field in the loop.
			foreach ( (array) $raw as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$q = sanitize_text_field( isset( $item['q'] ) ? (string) $item['q'] : '' );
				$a = wp_kses_post( isset( $item['a'] ) ? (string) $item['a'] : '' );
				if ( '' !== trim( $q ) && '' !== trim( wp_strip_all_tags( $a ) ) ) {
					$items[] = array(
						'q' => $q,
						'a' => $a,
					);
				}
			}
		}

		if ( empty( $items ) ) {
			delete_post_meta( $post_id, self::META_ITEMS );
		} else {
			update_post_meta( $post_id, self::META_ITEMS, $items );
		}

		if ( ! empty( $_POST['daeo_faq_append'] ) ) {
			update_post_meta( $post_id, self::META_APPEND, '1' );
		} else {
			delete_post_meta( $post_id, self::META_APPEND );
		}
	}

	/**
	 * [daeo_faq] shortcode: renders the current page's FAQ as an accordion.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'title' => __( 'Frequently asked questions', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			),
			$atts,
			'daeo_faq'
		);

		$post_id = absint( $atts['id'] );
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return '';
		}
		return self::render_list( $post_id, sanitize_text_field( $atts['title'] ) );
	}

	/**
	 * Render the FAQ accordion markup for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $title   Section heading ('' for none).
	 * @return string
	 */
	public static function render_list( $post_id, $title = '' ) {
		$items = self::items( $post_id );
		if ( empty( $items ) ) {
			return '';
		}

		$html = '<section class="daeo-faq">';
		if ( '' !== $title ) {
			$html .= '<h2 class="daeo-faq-title">' . esc_html( $title ) . '</h2>';
		}
		foreach ( $items as $item ) {
			$html .= '<details class="daeo-faq-item">';
			$html .= '<summary>' . esc_html( $item['q'] ) . '</summary>';
			$html .= '<div class="daeo-faq-answer">' . wp_kses_post( wpautop( $item['a'] ) ) . '</div>';
			$html .= '</details>';
		}
		$html .= '</section>';
		return $html;
	}

	/**
	 * Append the FAQ section to the content when the post opted in and the
	 * shortcode is not already used.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function maybe_append( $content ) {
		// The markdown mirrors simulate a loop; they add their own FAQ
		// section, so skip the append there.
		if ( class_exists( 'DAEO_Llms' ) && DAEO_Llms::is_rendering() ) {
			return $content;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		if ( ! $post_id || '1' !== (string) get_post_meta( $post_id, self::META_APPEND, true ) ) {
			return $content;
		}
		if ( has_shortcode( $content, 'daeo_faq' ) ) {
			return $content;
		}
		return $content . self::render_list( $post_id, __( 'Frequently asked questions', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
	}
}
