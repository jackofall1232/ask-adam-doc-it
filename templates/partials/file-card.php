<?php
/**
 * File card partial.
 *
 * Expected variables:
 *   - $post WP_Post — the document to render.
 *
 * Themes may override via `ask-adam-doc-it/partials/file-card.php`.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $post ) || ! ( $post instanceof WP_Post ) ) {
	return;
}

$aadi_card_id     = (int) $post->ID;
$aadi_card_type   = (string) get_post_meta( $aadi_card_id, '_aadi_file_type', true );
$aadi_card_ext    = (string) get_post_meta( $aadi_card_id, '_aadi_file_ext', true );
$aadi_card_size   = absint( get_post_meta( $aadi_card_id, '_aadi_file_size', true ) );
$aadi_card_downs  = absint( get_post_meta( $aadi_card_id, '_aadi_download_count', true ) );
$aadi_card_fid    = absint( get_post_meta( $aadi_card_id, '_aadi_file_id', true ) );
$aadi_card_dl_url = $aadi_card_fid > 0
	? get_rest_url( null, 'ask-adam-doc-it/v1/download/' . $aadi_card_id )
	: '';

$aadi_card_icon = 'aadi-icon-file';
if ( 'application/pdf' === $aadi_card_type ) {
	$aadi_card_icon = 'aadi-icon-pdf';
} elseif ( 0 === strpos( $aadi_card_type, 'image/' ) ) {
	$aadi_card_icon = 'aadi-icon-image';
} elseif ( 0 === strpos( $aadi_card_type, 'audio/' ) ) {
	$aadi_card_icon = 'aadi-icon-audio';
} elseif ( 0 === strpos( $aadi_card_type, 'video/' ) ) {
	$aadi_card_icon = 'aadi-icon-video';
}

$aadi_card_label = '' !== $aadi_card_ext ? strtoupper( $aadi_card_ext ) : $aadi_card_type;
?>
<li class="aadi-file-card">
	<div class="aadi-file-icon">
		<span class="<?php echo esc_attr( $aadi_card_icon ); ?>" aria-hidden="true"></span>
	</div>
	<div class="aadi-file-info">
		<a class="aadi-file-title" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
			<?php echo esc_html( get_the_title( $post ) ); ?>
		</a>
		<span class="aadi-file-meta">
			<?php
			$aadi_meta_bits = array();
			if ( '' !== $aadi_card_label ) {
				$aadi_meta_bits[] = $aadi_card_label;
			}
			if ( $aadi_card_size > 0 ) {
				$aadi_meta_bits[] = size_format( $aadi_card_size );
			}
			$aadi_meta_bits[] = sprintf(
				/* translators: %s: download count. */
				_n( '%s download', '%s downloads', $aadi_card_downs, 'ask-adam-doc-it' ),
				number_format_i18n( $aadi_card_downs )
			);
			echo esc_html( implode( ' · ', $aadi_meta_bits ) );
			?>
		</span>
		<?php if ( $aadi_card_fid > 0 ) : ?>
			<a class="aadi-download-link" href="<?php echo esc_url( $aadi_card_dl_url ); ?>">
				<?php esc_html_e( 'Download', 'ask-adam-doc-it' ); ?>
			</a>
		<?php endif; ?>
		<?php if ( AADI_Settings::is_summarize_enabled() ) : ?>
			<button type="button" class="aadi-summarize-btn" data-post-id="<?php echo esc_attr( $aadi_card_id ); ?>">
				<?php esc_html_e( 'Summarize', 'ask-adam-doc-it' ); ?>
			</button>
			<div class="aadi-summary-output" aria-live="polite"></div>
		<?php endif; ?>
	</div>
</li>
