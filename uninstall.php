<?php
/**
 * Uninstall cleanup: options always, content meta only when opted in.
 *
 * @package DemfilzAISEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$daeo_settings = get_option( 'daeo_settings', array() );
$daeo_purge    = is_array( $daeo_settings ) && ! empty( $daeo_settings['delete_on_uninstall'] );

delete_option( 'daeo_settings' );
delete_option( 'daeo_setup_done' );

if ( $daeo_purge ) {
	foreach ( array( '_daeo_title', '_daeo_desc', '_daeo_canonical', '_daeo_noindex', '_daeo_faqs', '_daeo_faq_append', '_daeo_llms_pin', '_daeo_llms_exclude', '_daeo_score', '_daeo_analysis' ) as $daeo_key ) {
		delete_post_meta_by_key( $daeo_key );
	}
}
