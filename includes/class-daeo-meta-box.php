<?php
/**
 * Edit-screen SEO meta box: title, description, canonical, noindex.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO meta box registration, rendering and saving.
 */
class DAEO_Meta_Box {

	const META_TITLE     = '_daeo_title';
	const META_DESC      = '_daeo_desc';
	const META_CANONICAL = '_daeo_canonical';
	const META_NOINDEX   = '_daeo_noindex';

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );
	}

	/**
	 * Register the meta box on every enabled post type.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( DAEO_Settings::post_types() as $pt ) {
			add_meta_box(
				'daeo_seo',
				__( 'AI SEO', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				array( __CLASS__, 'render' ),
				$pt,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render( $post ) {
		wp_nonce_field( 'daeo_meta', 'daeo_meta_nonce' );

		$title     = (string) get_post_meta( $post->ID, self::META_TITLE, true );
		$desc      = (string) get_post_meta( $post->ID, self::META_DESC, true );
		$canonical = (string) get_post_meta( $post->ID, self::META_CANONICAL, true );
		$noindex   = '1' === (string) get_post_meta( $post->ID, self::META_NOINDEX, true );

		echo '<div class="daeo-meta-box">';

		echo '<p class="daeo-field">';
		echo '<label for="daeo-title"><strong>' . esc_html__( 'Meta title', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</strong> <span class="daeo-counter" data-daeo-counter-for="daeo-title" data-daeo-max="60"></span></label>';
		echo '<input type="text" class="large-text" id="daeo-title" name="daeo_seo[title]" value="' . esc_attr( $title ) . '" placeholder="' . esc_attr( get_the_title( $post ) ) . '">';
		echo '<span class="description">' . esc_html__( 'Shown as the page title in search results and browser tabs. Aim for 30-60 characters. Leave blank to use the normal page title.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span>';
		echo '</p>';

		echo '<p class="daeo-field">';
		echo '<label for="daeo-desc"><strong>' . esc_html__( 'Meta description', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</strong> <span class="daeo-counter" data-daeo-counter-for="daeo-desc" data-daeo-max="160"></span></label>';
		echo '<textarea class="large-text" rows="3" id="daeo-desc" name="daeo_seo[desc]">' . esc_textarea( $desc ) . '</textarea>';
		echo '<span class="description">' . esc_html__( 'The snippet search engines and AI assistants show under the title. Aim for 70-160 characters.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span>';
		echo '</p>';

		echo '<div class="daeo-snippet" aria-hidden="true">';
		echo '<span class="daeo-snippet-title" id="daeo-snippet-title">' . esc_html( '' !== $title ? $title : get_the_title( $post ) ) . '</span>';
		echo '<span class="daeo-snippet-url">' . esc_html( get_permalink( $post ) ) . '</span>';
		echo '<span class="daeo-snippet-desc" id="daeo-snippet-desc">' . esc_html( '' !== $desc ? $desc : wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 25 ) ) . '</span>';
		echo '</div>';

		echo '<p class="daeo-field">';
		echo '<label for="daeo-canonical"><strong>' . esc_html__( 'Canonical URL', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</strong></label>';
		echo '<input type="url" class="large-text" id="daeo-canonical" name="daeo_seo[canonical]" value="' . esc_attr( $canonical ) . '" placeholder="' . esc_attr( get_permalink( $post ) ) . '">';
		echo '<span class="description">' . esc_html__( 'Only set this when the content lives at another URL you want search engines to credit instead.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span>';
		echo '</p>';

		echo '<p class="daeo-field">';
		echo '<label><input type="checkbox" name="daeo_seo[noindex]" value="1"' . checked( $noindex, true, false ) . '> ' . esc_html__( 'Hide this page from search engines (noindex)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label>';
		echo '</p>';

		$llms_pin     = '1' === (string) get_post_meta( $post->ID, DAEO_Llms::META_PIN, true );
		$llms_exclude = '1' === (string) get_post_meta( $post->ID, DAEO_Llms::META_EXCLUDE, true );
		echo '<p class="daeo-field">';
		echo '<strong>' . esc_html__( 'AI files (llms.txt and markdown mirror)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</strong><br>';
		echo '<label><input type="checkbox" name="daeo_seo[llms_pin]" value="1"' . checked( $llms_pin, true, false ) . '> ' . esc_html__( 'Feature at the top of llms.txt (key page)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label><br>';
		echo '<label><input type="checkbox" name="daeo_seo[llms_exclude]" value="1"' . checked( $llms_exclude, true, false ) . '> ' . esc_html__( 'Exclude from llms.txt and the markdown mirror', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label>';
		echo '</p>';

		/**
		 * Fires at the end of the SEO meta box, after the built-in fields.
		 *
		 * @param WP_Post $post The post being edited.
		 */
		do_action( 'daeo_meta_box_after', $post );

		echo '</div>';
	}

	/**
	 * Save submitted values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['daeo_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['daeo_meta_nonce'] ) ), 'daeo_meta' ) ) {
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

		$raw = array();
		if ( isset( $_POST['daeo_seo'] ) && is_array( $_POST['daeo_seo'] ) ) {
			$raw = map_deep( wp_unslash( $_POST['daeo_seo'] ), 'sanitize_text_field' );
		}

		self::store( $post_id, self::META_TITLE, isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '' );
		self::store( $post_id, self::META_DESC, isset( $raw['desc'] ) ? sanitize_textarea_field( $raw['desc'] ) : '' );
		self::store( $post_id, self::META_CANONICAL, isset( $raw['canonical'] ) ? esc_url_raw( $raw['canonical'] ) : '' );
		self::store( $post_id, self::META_NOINDEX, ! empty( $raw['noindex'] ) ? '1' : '' );
		self::store( $post_id, DAEO_Llms::META_PIN, ! empty( $raw['llms_pin'] ) ? '1' : '' );
		self::store( $post_id, DAEO_Llms::META_EXCLUDE, ! empty( $raw['llms_exclude'] ) ? '1' : '' );

		// Invalidate the cached analysis for the suggestions dashboard.
		delete_post_meta( $post_id, DAEO_Analysis::META_SCORE );
		delete_post_meta( $post_id, DAEO_Analysis::META_RESULT );
	}

	/**
	 * Store a meta value, deleting when empty.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param string $value   Value.
	 * @return void
	 */
	private static function store( $post_id, $key, $value ) {
		if ( '' === (string) $value ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $value );
		}
	}
}
