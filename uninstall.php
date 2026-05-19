<?php
/**
 * PaperTrail AI uninstall routine.
 *
 * Triggered when the plugin is deleted via the WordPress admin.
 * Respects the standalone `ptai_delete_data_on_uninstall` option —
 * if it is not set to a truthy value, this file exits without
 * touching any data.
 *
 * @package PaperTrail_AI
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Opt-in cleanup: leave user data alone unless explicitly enabled.
if ( ! get_option( 'ptai_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

/*
 * 1. Delete all ptai_file posts (and their postmeta), in batches
 *    to keep memory bounded on large libraries.
 */
$ptai_batch_size = 200;

do {
	$ptai_post_ids = get_posts(
		array(
			'post_type'        => 'ptai_file',
			'post_status'      => 'any',
			'numberposts'      => $ptai_batch_size,
			'fields'           => 'ids',
			'no_found_rows'    => true,
		)
	);

	foreach ( $ptai_post_ids as $ptai_post_id ) {
		wp_delete_post( $ptai_post_id, true );
	}
} while ( count( $ptai_post_ids ) === $ptai_batch_size );

/*
 * 2. Delete all ptai_category taxonomy terms.
 */
$ptai_term_ids = get_terms(
	array(
		'taxonomy'   => 'ptai_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $ptai_term_ids ) && ! empty( $ptai_term_ids ) ) {
	foreach ( $ptai_term_ids as $ptai_term_id ) {
		wp_delete_term( $ptai_term_id, 'ptai_category' );
	}
}

/*
 * 3. Defensive sweep: remove any leftover plugin postmeta from
 *    any post type (in case files were converted or reattached).
 *
 * The table identifier ({$wpdb->postmeta}) cannot be parameterized via
 * $wpdb->prepare() — it's an identifier, not a value. The LIKE pattern
 * is a hard-coded literal.
 */
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_ptai_%'" );

/*
 * 4. Delete plugin options.
 */
$ptai_options = array(
	'ptai_settings',
	'ptai_db_version',
	'ptai_activated_at',
	'ptai_flush_rewrite',
	'ptai_delete_data_on_uninstall',
	'ptai_openai_auth_failed',
	'ptai_rewrite_version',
);

foreach ( $ptai_options as $ptai_option ) {
	delete_option( $ptai_option );
	delete_site_option( $ptai_option );
}

/*
 * 4b. Delete plugin transients.
 *
 * The published-post-count cache has a single known key.
 * Download and search rate-limit transients use ptai_dl_ / ptai_srch_
 * prefixes — too many to enumerate, so wipe them by LIKE pattern.
 * Table identifier and LIKE pattern are hard-coded literals; no values
 * are interpolated from user input.
 */
delete_transient( 'ptai_post_count_cache' );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_ptai\_dl\_%'
		OR option_name LIKE '\_transient\_timeout\_ptai\_dl\_%'
		OR option_name LIKE '\_transient\_ptai\_srch\_%'
		OR option_name LIKE '\_transient\_timeout\_ptai\_srch\_%'"
);

/*
 * 5. Custom DB tables.
 *
 * Reserved: if a future version introduces a custom table
 * (e.g. {$wpdb->prefix}ptai_embeddings), drop it here.
 */

/*
 * 6. Clear any scheduled cron events.
 *
 * The active hook name must match PTAI_Embeddings::CRON_HOOK exactly.
 * The legacy hook names below are cleared too in case earlier dev
 * builds left orphan jobs in the schedule.
 */
wp_clear_scheduled_hook( 'ptai_generate_embedding' );
wp_clear_scheduled_hook( 'ptai_daily_maintenance' );
wp_clear_scheduled_hook( 'ptai_regenerate_embeddings' );

/*
 * 7. Flush rewrite rules.
 */
flush_rewrite_rules();
