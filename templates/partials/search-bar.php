<?php
/**
 * Search bar partial.
 *
 * Optional context variable:
 *   - $ptai_search_context array{
 *       category_id?: int,
 *       mode?:        string,
 *   }
 *
 * Themes may override via `papertrail-ai/partials/search-bar.php`.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

$ptai_search_context = isset( $ptai_search_context ) && is_array( $ptai_search_context )
	? $ptai_search_context
	: array();
$ptai_search_category = isset( $ptai_search_context['category_id'] ) ? absint( $ptai_search_context['category_id'] ) : 0;
$ptai_search_mode     = isset( $ptai_search_context['mode'] ) ? sanitize_key( $ptai_search_context['mode'] ) : 'auto';

$ptai_search_value = isset( $_GET['ptai_q'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	? sanitize_text_field( wp_unslash( $_GET['ptai_q'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	: '';

$ptai_search_action = get_post_type_archive_link( PTAI_CPT );
if ( ! $ptai_search_action ) {
	$ptai_search_action = home_url( '/' );
}

// Per-render unique input ID — multiple [papertrail] shortcodes can
// share a page and must not collide on DOM ids.
static $ptai_search_render_counter = 0;
$ptai_search_render_counter++;
$ptai_search_input_id = 'ptai-search-input-' . (int) $ptai_search_render_counter;
?>
<form class="ptai-search-form" method="get" action="<?php echo esc_url( $ptai_search_action ); ?>" role="search">
	<?php wp_nonce_field( 'ptai_search', 'ptai_search_nonce' ); ?>

	<label for="<?php echo esc_attr( $ptai_search_input_id ); ?>" class="screen-reader-text">
		<?php esc_html_e( 'Search documents', 'papertrail-ai' ); ?>
	</label>
	<input
		type="search"
		id="<?php echo esc_attr( $ptai_search_input_id ); ?>"
		name="ptai_q"
		class="ptai-search-input"
		value="<?php echo esc_attr( $ptai_search_value ); ?>"
		placeholder="<?php esc_attr_e( 'Search documents...', 'papertrail-ai' ); ?>"
	/>

	<?php if ( $ptai_search_category > 0 ) : ?>
		<input type="hidden" name="ptai_category" value="<?php echo esc_attr( (string) $ptai_search_category ); ?>" />
	<?php endif; ?>
	<input type="hidden" name="ptai_mode" value="<?php echo esc_attr( $ptai_search_mode ); ?>" />

	<button type="submit" class="ptai-search-btn button">
		<?php esc_html_e( 'Search', 'papertrail-ai' ); ?>
	</button>
</form>
