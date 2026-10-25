<?php
/**
 * Admin: menu, suggestions dashboard, settings screen and Pro info page.
 *
 * @package DemfilzAISEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * All wp-admin screens for the plugin.
 */
class DAEO_Admin {

	/**
	 * The screen hook suffixes of our pages, captured from add_menu_page /
	 * add_submenu_page so asset loading survives menu-title translation.
	 *
	 * @var string[]
	 */
	private static $hooks = array();

	/**
	 * The registered page hook suffixes (also used by the Pro add-on).
	 *
	 * @return string[]
	 */
	public static function hooks() {
		return self::$hooks;
	}

	/**
	 * Let the Pro add-on register its page hooks for asset loading.
	 *
	 * @param string $hook Hook suffix returned by add_submenu_page().
	 * @return void
	 */
	public static function add_hook( $hook ) {
		if ( is_string( $hook ) && '' !== $hook ) {
			self::$hooks[] = $hook;
		}
	}

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_daeo_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_daeo_scan_all', array( __CLASS__, 'scan_all' ) );
		add_action( 'admin_post_daeo_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . DAEO_BASENAME, array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );
	}

	/**
	 * Add a Settings link on the Plugins screen row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=daeo-settings' ) ) . '">' . esc_html__( 'Settings', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a>' );
		return $links;
	}

	/**
	 * One-time pointer to the Settings page until settings are saved once.
	 *
	 * @return void
	 */
	public static function setup_notice() {
		if ( ! current_user_can( 'manage_options' ) || get_option( 'daeo_setup_done' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'dashboard' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>';
		echo esc_html__( 'AI SEO is active with sensible defaults. Set your site identity and AI crawler policy to finish setting up.', 'demfilz-ai-agentic-engine-optimization-and-seo' );
		echo ' <a href="' . esc_url( admin_url( 'admin.php?page=daeo-settings' ) ) . '">' . esc_html__( 'Open AI SEO Settings', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Register the menu and pages.
	 *
	 * @return void
	 */
	public static function menu() {
		self::$hooks[] = add_menu_page(
			__( 'AI SEO', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'AI SEO', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			'manage_options',
			'daeo',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-search',
			81
		);
		self::$hooks[] = add_submenu_page(
			'daeo',
			__( 'Suggestions', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'Suggestions', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			'manage_options',
			'daeo',
			array( __CLASS__, 'render_dashboard' )
		);
		self::$hooks[] = add_submenu_page(
			'daeo',
			__( 'AI SEO Settings', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'Settings', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			'manage_options',
			'daeo-settings',
			array( __CLASS__, 'render_settings' )
		);
		if ( ! class_exists( 'DAEOP_Plugin' ) ) {
			self::$hooks[] = add_submenu_page(
				'daeo',
				__( 'AI SEO Pro', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				__( 'Pro', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				'manage_options',
				'daeo-pro',
				array( __CLASS__, 'render_pro' )
			);
		}
		self::$hooks = array_values( array_filter( self::$hooks ) );
	}

	/**
	 * Enqueue admin CSS/JS on plugin screens and enabled edit screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function assets( $hook ) {
		$is_plugin_page = in_array( $hook, self::$hooks, true );
		$is_edit_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_plugin_page && ! $is_edit_screen ) {
			return;
		}
		if ( $is_edit_screen ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && ! in_array( $screen->post_type, DAEO_Settings::post_types(), true ) ) {
				return;
			}
		}

		wp_enqueue_style(
			'daeo-admin',
			DAEO_URL . 'admin/css/daeo-admin.css',
			array(),
			DAEO_VERSION
		);
		wp_enqueue_script(
			'daeo-admin',
			DAEO_URL . 'admin/js/daeo-admin.js',
			array( 'jquery' ),
			DAEO_VERSION,
			true
		);
		wp_localize_script(
			'daeo-admin',
			'DAEO_ADMIN',
			array(
				'faqRowTemplate' => DAEO_Faq::row_html( '__i__' ),
				'confirmRemove'  => __( 'Remove this question?', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			)
		);
	}

	/**
	 * Handle the settings form.
	 *
	 * @return void
	 */
	public static function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		}
		check_admin_referer( 'daeo_settings' );

		$raw = array();
		if ( isset( $_POST['daeo'] ) && is_array( $_POST['daeo'] ) ) {
			$raw = map_deep( wp_unslash( $_POST['daeo'] ), 'sanitize_text_field' );
		}

		$public_types = get_post_types( array( 'public' => true ), 'names' );
		$post_types   = array();
		foreach ( (array) ( isset( $raw['post_types'] ) ? $raw['post_types'] : array() ) as $pt ) {
			$pt = sanitize_key( $pt );
			if ( isset( $public_types[ $pt ] ) && 'attachment' !== $pt ) {
				$post_types[] = $pt;
			}
		}
		if ( empty( $post_types ) ) {
			$post_types = array( 'post', 'page' );
		}

		$bots = array();
		foreach ( array_keys( DAEO_Robots::bots() ) as $bot_key ) {
			$value = isset( $raw['bots'][ $bot_key ] ) ? (string) $raw['bots'][ $bot_key ] : '';
			if ( in_array( $value, array( 'allow', 'block' ), true ) ) {
				$bots[ $bot_key ] = $value;
			}
		}

		DAEO_Settings::save(
			array(
				'post_types'          => $post_types,
				'module_meta'         => empty( $raw['module_meta'] ) ? 0 : 1,
				'module_social'       => empty( $raw['module_social'] ) ? 0 : 1,
				'module_schema'       => empty( $raw['module_schema'] ) ? 0 : 1,
				'module_sitemap'      => empty( $raw['module_sitemap'] ) ? 0 : 1,
				'module_llms'         => empty( $raw['module_llms'] ) ? 0 : 1,
				'module_md'           => empty( $raw['module_md'] ) ? 0 : 1,
				'org_name'            => isset( $raw['org_name'] ) ? sanitize_text_field( $raw['org_name'] ) : '',
				'org_logo'            => isset( $raw['org_logo'] ) ? esc_url_raw( $raw['org_logo'] ) : '',
				'org_sameas'          => isset( $raw['org_sameas'] ) ? sanitize_textarea_field( $raw['org_sameas'] ) : '',
				'social_image'        => isset( $raw['social_image'] ) ? esc_url_raw( $raw['social_image'] ) : '',
				'twitter_handle'      => isset( $raw['twitter_handle'] ) ? sanitize_text_field( $raw['twitter_handle'] ) : '',
				'llms_summary'        => isset( $raw['llms_summary'] ) ? sanitize_textarea_field( $raw['llms_summary'] ) : '',
				'llms_max'            => isset( $raw['llms_max'] ) ? max( 10, min( 500, absint( $raw['llms_max'] ) ) ) : 100,
				'title_site_name'     => empty( $raw['title_site_name'] ) ? 0 : 1,
				'bots'                => $bots,
				'delete_on_uninstall' => empty( $raw['delete_on_uninstall'] ) ? 0 : 1,
			)
		);
		update_option( 'daeo_setup_done', 1 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'daeo-settings',
					'daeo_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Compute and cache the analysis score for every published item, so the
	 * worst-first sort covers the whole site.
	 *
	 * @return void
	 */
	public static function scan_all() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		}
		check_admin_referer( 'daeo_scan_all' );

		$posts = DAEO_Sitemap::content_posts( 500 );
		foreach ( $posts as $post ) {
			DAEO_Analysis::get( $post->ID );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'daeo',
					'daeo_scanned' => count( $posts ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * The importable SEO plugins and their per-post meta keys.
	 *
	 * @return array source => { label, title, desc }
	 */
	public static function import_sources() {
		return array(
			'yoast'    => array(
				'label' => 'Yoast SEO',
				'title' => '_yoast_wpseo_title',
				'desc'  => '_yoast_wpseo_metadesc',
			),
			'rankmath' => array(
				'label' => 'Rank Math',
				'title' => 'rank_math_title',
				'desc'  => 'rank_math_description',
			),
			'aioseo'   => array(
				'label' => 'All in One SEO',
				'title' => '_aioseo_title',
				'desc'  => '_aioseo_description',
			),
			'seopress' => array(
				'label' => 'SEOPress',
				'title' => '_seopress_titles_title',
				'desc'  => '_seopress_titles_desc',
			),
		);
	}

	/**
	 * Copy another SEO plugin's per-post titles/descriptions into our meta,
	 * never overwriting values that already exist here and skipping values
	 * that contain that plugin's template variables.
	 *
	 * @param string $source Source key from import_sources().
	 * @return array { imported: int, skipped: int }
	 */
	public static function import_source( $source ) {
		$sources = self::import_sources();
		if ( ! isset( $sources[ $source ] ) ) {
			return array(
				'imported' => 0,
				'skipped'  => 0,
			);
		}
		$map      = $sources[ $source ];
		$imported = 0;
		$skipped  = 0;

		foreach ( array( 'title', 'desc' ) as $field ) {
			$ours   = 'title' === $field ? DAEO_Meta_Box::META_TITLE : DAEO_Meta_Box::META_DESC;
			$theirs = $map[ $field ];
			$posts  = get_posts(
				array(
					'post_type'      => DAEO_Settings::post_types(),
					'post_status'    => array( 'publish', 'draft', 'future', 'private' ),
					'posts_per_page' => 500, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one-off admin-triggered import batch, ids only.
					'fields'         => 'ids',
					'meta_key'       => $theirs, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- one-off manual import.
				)
			);
			foreach ( $posts as $post_id ) {
				$value = trim( (string) get_post_meta( $post_id, $theirs, true ) );
				if ( '' === $value ) {
					continue;
				}
				// Skip templated values like %%title%% or %sitename% - they
				// only resolve inside the source plugin.
				if ( preg_match( '/%%[^%]+%%|%[a-z_]+%/i', $value ) ) {
					++$skipped;
					continue;
				}
				if ( '' !== (string) get_post_meta( $post_id, $ours, true ) ) {
					++$skipped;
					continue;
				}
				update_post_meta( $post_id, $ours, sanitize_textarea_field( $value ) );
				DAEO_Analysis::invalidate( $post_id );
				++$imported;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
		);
	}

	/**
	 * Handle the import form.
	 *
	 * @return void
	 */
	public static function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		}
		check_admin_referer( 'daeo_import' );

		$source = isset( $_POST['daeo_source'] ) ? sanitize_key( wp_unslash( $_POST['daeo_source'] ) ) : '';
		$result = self::import_source( $source );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'daeo-settings',
					'daeo_imported' => (int) $result['imported'],
					'daeo_skipped'  => (int) $result['skipped'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Detect other active SEO plugins whose output would duplicate ours.
	 *
	 * @return string[] Plugin names.
	 */
	public static function conflicting_plugins() {
		$found = array();
		if ( defined( 'WPSEO_VERSION' ) ) {
			$found[] = 'Yoast SEO';
		}
		if ( class_exists( 'RankMath' ) ) {
			$found[] = 'Rank Math';
		}
		if ( defined( 'AIOSEO_VERSION' ) ) {
			$found[] = 'All in One SEO';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$found[] = 'SEOPress';
		}
		return $found;
	}

	/**
	 * The suggestions dashboard.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$type  = isset( $_GET['daeo_type'] ) ? sanitize_key( wp_unslash( $_GET['daeo_type'] ) ) : '';
		$paged = isset( $_GET['daeo_paged'] ) ? max( 1, absint( wp_unslash( $_GET['daeo_paged'] ) ) ) : 1;
		$sort  = isset( $_GET['daeo_sort'] ) && 'score' === sanitize_key( wp_unslash( $_GET['daeo_sort'] ) ) ? 'score' : 'recent';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$types = DAEO_Settings::post_types();
		if ( '' !== $type && ! in_array( $type, $types, true ) ) {
			$type = '';
		}

		$per_page = 20;
		$args     = array(
			'post_type'      => '' !== $type ? $type : $types,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);
		if ( 'score' === $sort ) {
			// Worst pages first. Items are scored on save or by Scan all;
			// unscanned items have no score meta yet and are listed under
			// the recent sort instead.
			$args['meta_key'] = DAEO_Analysis::META_SCORE; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-only list sort.
			$args['orderby']  = 'meta_value_num';
			$args['order']    = 'ASC';
		}
		$query = new WP_Query( $args );

		echo '<div class="wrap daeo-wrap">';
		echo '<h1>' . esc_html__( 'AI SEO Suggestions', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h1>';

		self::conflict_notice();

		/**
		 * Fires at the top of the suggestions dashboard, under the heading.
		 */
		do_action( 'daeo_dashboard_notices' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our redirect.
		if ( isset( $_GET['daeo_scanned'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag set by our redirect.
			$scanned = absint( wp_unslash( $_GET['daeo_scanned'] ) );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: %s: number of items scanned. */
					_n( 'Scanned %s item. Every item now has a current score.', 'Scanned %s items. Every item now has a current score.', $scanned, 'demfilz-ai-agentic-engine-optimization-and-seo' ),
					number_format_i18n( $scanned )
				)
			) . '</p></div>';
		}

		self::summary_cards( $query );

		// Sort controls + full scan.
		$base = admin_url( 'admin.php?page=daeo' );
		echo '<div class="daeo-toolbar">';
		echo '<span>' . esc_html__( 'Sort:', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . ' ';
		echo '<a href="' . esc_url( '' !== $type ? add_query_arg( 'daeo_type', $type, $base ) : $base ) . '"' . ( 'recent' === $sort ? ' class="daeo-sort-current"' : '' ) . '>' . esc_html__( 'Recently updated', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a> | ';
		$score_url = add_query_arg( 'daeo_sort', 'score', '' !== $type ? add_query_arg( 'daeo_type', $type, $base ) : $base );
		echo '<a href="' . esc_url( $score_url ) . '"' . ( 'score' === $sort ? ' class="daeo-sort-current"' : '' ) . '>' . esc_html__( 'Worst score first', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a></span>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="daeo-scan-form">';
		wp_nonce_field( 'daeo_scan_all' );
		echo '<input type="hidden" name="action" value="daeo_scan_all">';
		echo '<button type="submit" class="button">' . esc_html__( 'Scan all content', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</button> ';
		echo '<span class="daeo-muted">' . esc_html__( 'Run this once before "Worst score first" so every item has a score.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span>';
		echo '</form>';
		echo '</div>';

		// Type filter tabs.
		echo '<ul class="subsubsub daeo-type-tabs">';
		echo '<li><a href="' . esc_url( $base ) . '"' . ( '' === $type ? ' class="current"' : '' ) . '>' . esc_html__( 'All', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a> |</li>';
		foreach ( $types as $i => $pt ) {
			$pt_object = get_post_type_object( $pt );
			if ( ! $pt_object ) {
				continue;
			}
			$sep = count( $types ) - 1 === $i ? '' : ' |';
			echo '<li><a href="' . esc_url( add_query_arg( 'daeo_type', $pt, $base ) ) . '"' . ( $type === $pt ? ' class="current"' : '' ) . '>' . esc_html( $pt_object->labels->name ) . '</a>' . esc_html( $sep ) . '</li>';
		}
		echo '</ul>';

		echo '<table class="widefat striped daeo-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Content', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th>';
		echo '<th class="daeo-col-score">' . esc_html__( 'Score', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Suggestions', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th>';
		echo '<th class="daeo-col-actions">' . esc_html__( 'Actions', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( ! $query->have_posts() ) {
			echo '<tr><td colspan="4">' . esc_html__( 'No published content found for the enabled post types yet. Publish a page or post and it will appear here with its optimization score.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</td></tr>';
		}

		foreach ( $query->posts as $post ) {
			$analysis  = DAEO_Analysis::get( $post->ID );
			$pt_object = get_post_type_object( $post->post_type );

			echo '<tr>';
			echo '<td><strong><a href="' . esc_url( (string) get_edit_post_link( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></strong>';
			echo '<br><span class="daeo-muted">' . esc_html( $pt_object ? $pt_object->labels->singular_name : $post->post_type ) . '</span></td>';
			echo '<td class="daeo-col-score">' . wp_kses_post( self::score_badge( $analysis['score'] ) ) . '</td>';
			echo '<td>';
			if ( empty( $analysis['issues'] ) ) {
				echo '<span class="daeo-ok">' . esc_html__( 'Looking good. All checks pass.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span>';
			} else {
				echo '<ul class="daeo-issues">';
				foreach ( $analysis['issues'] as $issue ) {
					echo '<li>' . esc_html( $issue ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</td>';
			echo '<td class="daeo-col-actions"><a class="button button-small" href="' . esc_url( (string) get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Edit', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a> ';
			echo '<a class="button button-small" href="' . esc_url( (string) get_permalink( $post->ID ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a>';
			/**
			 * Fires in the actions cell of a suggestions dashboard row.
			 *
			 * @param int   $post_id  The row's post ID.
			 * @param array $analysis The analysis result.
			 */
			do_action( 'daeo_dashboard_row_actions', $post->ID, $analysis );
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Pagination.
		$total_pages = (int) $query->max_num_pages;
		if ( $total_pages > 1 ) {
			$links = paginate_links(
				array(
					'base'      => add_query_arg( 'daeo_paged', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $total_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
				)
			);
			if ( $links ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $links ) . '</div></div>';
			}
		}

		echo '</div>';
	}

	/**
	 * Summary cards above the dashboard table.
	 *
	 * @param WP_Query $query The current page's query (for found_posts).
	 * @return void
	 */
	private static function summary_cards( $query ) {
		$scores    = array();
		$missing   = 0;
		$faq_count = 0;
		foreach ( $query->posts as $post ) {
			$analysis = DAEO_Analysis::get( $post->ID );
			$scores[] = $analysis['score'];
			if ( '' === (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_TITLE, true ) || '' === (string) get_post_meta( $post->ID, DAEO_Meta_Box::META_DESC, true ) ) {
				++$missing;
			}
			if ( count( DAEO_Faq::items( $post->ID ) ) > 0 ) {
				++$faq_count;
			}
		}
		$avg = empty( $scores ) ? 0 : (int) round( array_sum( $scores ) / count( $scores ) );

		echo '<div class="daeo-cards">';
		self::card( (string) number_format_i18n( (int) $query->found_posts ), __( 'Published items', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		self::card( $avg . '%', __( 'Average score (this page)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		self::card( (string) number_format_i18n( $missing ), __( 'Missing meta (this page)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		self::card( (string) number_format_i18n( $faq_count ), __( 'With FAQ schema (this page)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		echo '</div>';
	}

	/**
	 * One summary card.
	 *
	 * @param string $value Big value.
	 * @param string $label Label under it.
	 * @return void
	 */
	private static function card( $value, $label ) {
		echo '<div class="daeo-card"><span class="daeo-card-value">' . esc_html( $value ) . '</span><span class="daeo-card-label">' . esc_html( $label ) . '</span></div>';
	}

	/**
	 * A coloured score badge.
	 *
	 * @param int $score Score 0-100.
	 * @return string
	 */
	public static function score_badge( $score ) {
		$score = (int) $score;
		$class = 'daeo-badge-bad';
		if ( $score >= 80 ) {
			$class = 'daeo-badge-good';
		} elseif ( $score >= 50 ) {
			$class = 'daeo-badge-mid';
		}
		return '<span class="daeo-badge ' . esc_attr( $class ) . '">' . esc_html( (string) $score ) . '</span>';
	}

	/**
	 * Warn when another SEO plugin is active.
	 *
	 * @return void
	 */
	private static function conflict_notice() {
		$conflicts = self::conflicting_plugins();
		if ( empty( $conflicts ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: comma-separated plugin names. */
				__( 'Also active: %s. To avoid duplicate meta tags or sitemaps, turn off the overlapping modules on the Settings page of one plugin.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
				implode( ', ', $conflicts )
			)
		);
		echo '</p></div>';
	}

	/**
	 * The settings screen.
	 *
	 * @return void
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s = DAEO_Settings::all();

		echo '<div class="wrap daeo-wrap">';
		echo '<h1>' . esc_html__( 'AI SEO Settings', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h1>';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only flags set by our redirects.
		if ( isset( $_GET['daeo_saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p></div>';
		}
		if ( isset( $_GET['daeo_imported'] ) ) {
			$imported = absint( wp_unslash( $_GET['daeo_imported'] ) );
			$skipped  = isset( $_GET['daeo_skipped'] ) ? absint( wp_unslash( $_GET['daeo_skipped'] ) ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: number imported, 2: number skipped. */
					__( 'Import finished: %1$s values imported, %2$s skipped (already set here, or templated).', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
					number_format_i18n( $imported ),
					number_format_i18n( $skipped )
				)
			) . '</p></div>';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		self::conflict_notice();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'daeo_settings' );
		echo '<input type="hidden" name="action" value="daeo_save_settings">';

		echo '<h2>' . esc_html__( 'Modules', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Post types', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th><td>';
		$public_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $public_types as $pt => $object ) {
			if ( 'attachment' === $pt ) {
				continue;
			}
			echo '<label class="daeo-inline"><input type="checkbox" name="daeo[post_types][]" value="' . esc_attr( $pt ) . '"' . checked( in_array( $pt, (array) $s['post_types'], true ), true, false ) . '> ' . esc_html( $object->labels->name ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'The content types that get the SEO and FAQ boxes, sitemap entries and suggestions.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		echo '</td></tr>';

		$modules = array(
			'module_meta'    => array( __( 'Meta titles and descriptions', 'demfilz-ai-agentic-engine-optimization-and-seo' ), __( 'Title tag, meta description, canonical URL and noindex output.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) ),
			'module_social'  => array( __( 'Social cards', 'demfilz-ai-agentic-engine-optimization-and-seo' ), __( 'Open Graph and Twitter card tags.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) ),
			'module_schema'  => array( __( 'Schema.org structured data', 'demfilz-ai-agentic-engine-optimization-and-seo' ), __( 'Organization, WebSite, Article, BreadcrumbList and FAQPage JSON-LD.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) ),
			'module_sitemap' => array( __( 'XML sitemap', 'demfilz-ai-agentic-engine-optimization-and-seo' ), __( 'Serves /sitemap.xml and advertises it in robots.txt. Replaces the core wp-sitemap.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) ),
			'module_llms'    => array( __( 'llms.txt for AI assistants', 'demfilz-ai-agentic-engine-optimization-and-seo' ), __( 'Serves /llms.txt and /llms-full.txt so AI assistants can read the site accurately.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) ),
			'module_md'      => array( __( 'Markdown page mirrors', 'demfilz-ai-agentic-engine-optimization-and-seo' ), __( 'Serves a clean Markdown copy of each page at its URL plus .md, for AI crawlers.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) ),
		);
		foreach ( $modules as $key => $labels ) {
			echo '<tr><th scope="row">' . esc_html( $labels[0] ) . '</th><td>';
			echo '<label><input type="checkbox" name="daeo[' . esc_attr( $key ) . ']" value="1"' . checked( ! empty( $s[ $key ] ), true, false ) . '> ' . esc_html__( 'Enabled', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label>';
			echo '<p class="description">' . esc_html( $labels[1] ) . '</p>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Site identity', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="daeo-org-name">' . esc_html__( 'Organization name', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="daeo-org-name" name="daeo[org_name]" value="' . esc_attr( $s['org_name'] ) . '" placeholder="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="daeo-org-logo">' . esc_html__( 'Logo URL', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></th><td>';
		echo '<input type="url" class="regular-text" id="daeo-org-logo" name="daeo[org_logo]" value="' . esc_attr( $s['org_logo'] ) . '">';
		echo '<p class="description">' . esc_html__( 'Used in the Organization schema. Paste a URL from the Media Library.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="daeo-org-sameas">' . esc_html__( 'Profiles (sameAs)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></th><td>';
		echo '<textarea class="large-text" rows="3" id="daeo-org-sameas" name="daeo[org_sameas]" placeholder="https://www.facebook.com/yourpage&#10;https://www.linkedin.com/company/yourcompany">' . esc_textarea( $s['org_sameas'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One URL per line: social profiles and directories that represent this organization.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="daeo-social-image">' . esc_html__( 'Default social image URL', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></th><td>';
		echo '<input type="url" class="regular-text" id="daeo-social-image" name="daeo[social_image]" value="' . esc_attr( $s['social_image'] ) . '">';
		echo '<p class="description">' . esc_html__( 'Shown when a page has no featured image. Recommended 1200 x 630 pixels.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="daeo-twitter">' . esc_html__( 'X / Twitter handle', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" id="daeo-twitter" name="daeo[twitter_handle]" value="' . esc_attr( $s['twitter_handle'] ) . '" placeholder="@yourhandle">';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Title branding', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th><td>';
		echo '<label><input type="checkbox" name="daeo[title_site_name]" value="1"' . checked( ! empty( $s['title_site_name'] ), true, false ) . '> ' . esc_html__( 'Append the site name to custom meta titles (Title - Site Name)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="daeo-llms-summary">' . esc_html__( 'Site summary for AI', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></th><td>';
		echo '<textarea class="large-text" rows="3" id="daeo-llms-summary" name="daeo[llms_summary]">' . esc_textarea( $s['llms_summary'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One or two sentences describing the site. Used in llms.txt and as the front-page description. Falls back to the site tagline.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		echo '</td></tr>';
		echo '<tr><th scope="row"><label for="daeo-llms-max">' . esc_html__( 'Max links in llms.txt', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label></th><td>';
		echo '<input type="number" min="10" max="500" id="daeo-llms-max" name="daeo[llms_max]" value="' . esc_attr( (string) $s['llms_max'] ) . '">';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'AI crawlers', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h2>';
		if ( file_exists( ABSPATH . 'robots.txt' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'A physical robots.txt file exists on your server, so WordPress (and this plugin) cannot modify robots.txt. Delete or rename that file for the crawler policy and sitemap line below to take effect.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p></div>';
		}
		echo '<p class="description">' . esc_html__( 'Controls robots.txt for AI crawlers. Default leaves no rule (crawlers may visit). Being readable by AI assistants is usually good for visibility; blocking training bots keeps your content out of model training data.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		echo '<p><button type="button" class="button" id="daeo-block-training">' . esc_html__( 'Block all training bots', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</button> ';
		echo '<button type="button" class="button" id="daeo-reset-bots">' . esc_html__( 'Reset all to default', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</button></p>';
		echo '<table class="widefat striped daeo-bots-table"><thead><tr><th>' . esc_html__( 'Crawler', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th><th>' . esc_html__( 'Purpose', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th><th>' . esc_html__( 'Policy', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th></tr></thead><tbody>';
		$type_labels = array(
			'training'  => __( 'Model training', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			'search'    => __( 'AI search index', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			'assistant' => __( 'On-demand assistant visits', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
		);
		foreach ( DAEO_Robots::bots() as $bot_key => $bot ) {
			$policy = DAEO_Robots::policy( $bot_key );
			echo '<tr><td><code>' . esc_html( $bot['agent'] ) . '</code><br><span class="daeo-muted">' . esc_html( $bot['label'] ) . '</span></td>';
			echo '<td>' . esc_html( isset( $type_labels[ $bot['type'] ] ) ? $type_labels[ $bot['type'] ] : $bot['type'] ) . '</td>';
			echo '<td><select name="daeo[bots][' . esc_attr( $bot_key ) . ']" data-daeo-bot-type="' . esc_attr( $bot['type'] ) . '">';
			echo '<option value=""' . selected( '', $policy, false ) . '>' . esc_html__( 'Default (no rule)', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</option>';
			echo '<option value="allow"' . selected( 'allow', $policy, false ) . '>' . esc_html__( 'Allow', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</option>';
			echo '<option value="block"' . selected( 'block', $policy, false ) . '>' . esc_html__( 'Block', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</option>';
			echo '</select></td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Advanced', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'On uninstall', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</th><td>';
		echo '<label><input type="checkbox" name="daeo[delete_on_uninstall]" value="1"' . checked( ! empty( $s['delete_on_uninstall'] ), true, false ) . '> ' . esc_html__( 'Also delete all stored SEO fields and FAQ items when the plugin is deleted', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</label>';
		echo '</td></tr>';
		echo '</tbody></table>';

		submit_button( __( 'Save settings', 'demfilz-ai-agentic-engine-optimization-and-seo' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Import from another SEO plugin', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Copies per-page meta titles and descriptions written in another SEO plugin into this one. Values already set here are never overwritten, and templated values (like %%title%%) are skipped. Up to 500 items per field per run; run again for very large sites.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';
		echo '<p class="daeo-import-buttons">';
		foreach ( self::import_sources() as $source_key => $source ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="daeo-import-form">';
			wp_nonce_field( 'daeo_import' );
			echo '<input type="hidden" name="action" value="daeo_import">';
			echo '<input type="hidden" name="daeo_source" value="' . esc_attr( $source_key ) . '">';
			echo '<button type="submit" class="button">' . esc_html(
				sprintf(
					/* translators: %s: source SEO plugin name. */
					__( 'Import from %s', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
					$source['label']
				)
			) . '</button>';
			echo '</form>';
		}
		echo '</p>';

		echo '<h2>' . esc_html__( 'Your live files', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h2>';
		echo '<p>';
		$live_files = array();
		if ( ! empty( $s['module_sitemap'] ) ) {
			$live_files[] = home_url( '/sitemap.xml' );
		}
		if ( ! empty( $s['module_llms'] ) ) {
			$live_files[] = home_url( '/llms.txt' );
			$live_files[] = home_url( '/llms-full.txt' );
		}
		$live_files[] = home_url( '/robots.txt' );
		foreach ( $live_files as $file_url ) {
			echo '<a href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener">' . esc_html( $file_url ) . '</a><br>';
		}
		if ( empty( $s['module_sitemap'] ) || empty( $s['module_llms'] ) ) {
			echo '<span class="daeo-muted">' . esc_html__( 'Files for disabled modules are not listed because they are not being served.', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span>';
		}
		echo '</p>';

		echo '</div>';
	}

	/**
	 * The Pro information page (free plugin: information only, no
	 * licence code and no remote calls).
	 *
	 * @return void
	 */
	public static function render_pro() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap daeo-wrap">';
		echo '<h1>' . esc_html__( 'AI SEO Pro', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</h1>';
		echo '<p class="daeo-pro-intro">' . esc_html__( 'Everything in the free plugin is complete and stays free. AI SEO Pro is a separate add-on plugin that puts an AI copywriter inside WordPress:', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</p>';

		echo '<ul class="daeo-pro-features">';
		$features = array(
			__( 'Generate meta titles and descriptions with AI, one click per page.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'Bulk generate metas for every page that is missing them.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'Auto-create FAQ questions and answers from your page content.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'AI Writer: draft a complete page or post (content, meta and FAQ) from a topic.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'AI page advice: concrete, prioritised recommendations for any page in the dashboard.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
			__( 'Managed AI included, or bring your own Anthropic API key.', 'demfilz-ai-agentic-engine-optimization-and-seo' ),
		);
		foreach ( $features as $feature ) {
			echo '<li>' . esc_html( $feature ) . '</li>';
		}
		echo '</ul>';

		echo '<div class="daeo-cards daeo-pro-tiers">';
		echo '<div class="daeo-card"><span class="daeo-card-value">$19.95<span class="daeo-per">/mo</span></span><span class="daeo-card-label">' . esc_html__( 'Pro: all features, 200 AI generations per month included', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span></div>';
		echo '<div class="daeo-card"><span class="daeo-card-value">$39.95<span class="daeo-per">/mo</span></span><span class="daeo-card-label">' . esc_html__( 'Pro Plus: all features, 1,000 AI generations per month included', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</span></div>';
		echo '</div>';

		echo '<p><a class="button button-primary button-hero" href="https://demfilz.com/ai-seo" target="_blank" rel="noopener">' . esc_html__( 'Learn more at demfilz.com', 'demfilz-ai-agentic-engine-optimization-and-seo' ) . '</a></p>';
		echo '</div>';
	}
}
