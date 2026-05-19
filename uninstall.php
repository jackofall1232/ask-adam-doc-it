<?php
/**
 * Ask Adam Doc It uninstall routine.
 *
 * Triggered when the plugin is deleted via the WordPress admin.
 * Respects the standalone `aadi_delete_data_on_uninstall` option —
 * if it is not set to a truthy value, this file exits without
 * touching any data.
 *
 * @package Ask_Adam_Doc_It
 */

// Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Opt-in cleanup: leave user data alone unless explicitly enabled.
if ( ! get_option( 'aadi_delete_data_on_uninstall' ) ) {
	return;
}

global $wpdb;

/*
 * 1. Delete all aadi_file posts (and their postmeta), in batches
 *    to keep memory bounded on large libraries.
 */
$aadi_batch_size = 200;

do {
	$aadi_post_ids = get_posts(
		array(
			'post_type'        => 'aadi_file',
			'post_status'      => 'any',
			'numberposts'      => $aadi_batch_size,
			'fields'           => 'ids',
			'no_found_rows'    => true,
		)
	);

	foreach ( $aadi_post_ids as $aadi_post_id ) {
		wp_delete_post( $aadi_post_id, true );
	}
} while ( count( $aadi_post_ids ) === $aadi_batch_size );

/*
 * 2. Delete all aadi_category taxonomy terms.
 */
$aadi_term_ids = get_terms(
	array(
		'taxonomy'   => 'aadi_category',
		'hide_empty' => false,
		'fields'     => 'ids',
	)
);

if ( ! is_wp_error( $aadi_term_ids ) && ! empty( $aadi_term_ids ) ) {
	foreach ( $aadi_term_ids as $aadi_term_id ) {
		wp_delete_term( $aadi_term_id, 'aadi_category' );
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
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aadi_%'" );

/*
 * 4. Delete plugin options.
 */
$aadi_options = array(
	'aadi_settings',
	'aadi_db_version',
	'aadi_activated_at',
	'aadi_flush_rewrite',
	'aadi_delete_data_on_uninstall',
	'aadi_openai_auth_failed',
	'aadi_rewrite_version',
);

foreach ( $aadi_options as $aadi_option ) {
	delete_option( $aadi_option );
	delete_site_option( $aadi_option );
}

/*
 * 4b. Delete plugin transients.
 *
 * The published-post-count cache has a single known key.
 * Download and search rate-limit transients use aadi_dl_ / aadi_srch_
 * prefixes — too many to enumerate, so wipe them by LIKE pattern.
 * Table identifier and LIKE pattern are hard-coded literals; no values
 * are interpolated from user input.
 */
delete_transient( 'aadi_post_count_cache' );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_aadi\_dl\_%'
		OR option_name LIKE '\_transient\_timeout\_aadi\_dl\_%'
		OR option_name LIKE '\_transient\_aadi\_srch\_%'
		OR option_name LIKE '\_transient\_timeout\_aadi\_srch\_%'"
);

/*
 * 5. Custom DB tables.
 *
 * Reserved: if a future version introduces a custom table
 * (e.g. {$wpdb->prefix}aadi_embeddings), drop it here.
 */

/*
 * 6. Clear any scheduled cron events.
 *
 * The active hook name must match AADI_Embeddings::CRON_HOOK exactly.
 * The legacy hook names below are cleared too in case earlier dev
 * builds left orphan jobs in the schedule.
 */
wp_clear_scheduled_hook( 'aadi_generate_embedding' );
wp_clear_scheduled_hook( 'aadi_daily_maintenance' );
wp_clear_scheduled_hook( 'aadi_regenerate_embeddings' );

/*
 * 7. Flush rewrite rules.
 */
flush_rewrite_rules();
