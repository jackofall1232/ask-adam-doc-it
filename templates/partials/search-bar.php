<?php
/**
 * Search bar partial.
 *
 * Optional context variable:
 *   - $aadi_search_context array{
 *       category_id?: int,
 *       mode?:        string,
 *   }
 *
 * Themes may override via `ask-adam-doc-it/partials/search-bar.php`.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

$aadi_search_context = isset( $aadi_search_context ) && is_array( $aadi_search_context )
	? $aadi_search_context
	: array();
$aadi_search_category = isset( $aadi_search_context['category_id'] ) ? absint( $aadi_search_context['category_id'] ) : 0;
$aadi_search_mode     = isset( $aadi_search_context['mode'] ) ? sanitize_key( $aadi_search_context['mode'] ) : 'auto';

$aadi_search_value = isset( $_GET['aadi_q'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	? sanitize_text_field( wp_unslash( $_GET['aadi_q'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	: '';

$aadi_search_action = get_post_type_archive_link( AADI_CPT );
if ( ! $aadi_search_action ) {
	$aadi_search_action = home_url( '/' );
}

// Per-render unique input ID — multiple [ask_adam_doc_it] shortcodes can
// share a page and must not collide on DOM ids.
static $aadi_search_render_counter = 0;
$aadi_search_render_counter++;
$aadi_search_input_id = 'aadi-search-input-' . (int) $aadi_search_render_counter;
?>
<form class="aadi-search-form" method="get" action="<?php echo esc_url( $aadi_search_action ); ?>" role="search">
	<?php wp_nonce_field( 'aadi_search', 'aadi_search_nonce' ); ?>

	<label for="<?php echo esc_attr( $aadi_search_input_id ); ?>" class="screen-reader-text">
		<?php esc_html_e( 'Search documents', 'ask-adam-doc-it' ); ?>
	</label>
	<input
		type="search"
		id="<?php echo esc_attr( $aadi_search_input_id ); ?>"
		name="aadi_q"
		class="aadi-search-input"
		value="<?php echo esc_attr( $aadi_search_value ); ?>"
		placeholder="<?php esc_attr_e( 'Search documents...', 'ask-adam-doc-it' ); ?>"
	/>

	<?php if ( $aadi_search_category > 0 ) : ?>
		<input type="hidden" name="aadi_category" value="<?php echo esc_attr( (string) $aadi_search_category ); ?>" />
	<?php endif; ?>
	<input type="hidden" name="aadi_mode" value="<?php echo esc_attr( $aadi_search_mode ); ?>" />

	<button type="submit" class="aadi-search-btn button">
		<?php esc_html_e( 'Search', 'ask-adam-doc-it' ); ?>
	</button>
</form>
