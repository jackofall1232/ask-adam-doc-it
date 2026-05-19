<?php
/**
 * File card partial.
 *
 * Expected variables:
 *   - $post WP_Post — the document to render.
 *
 * Themes may override via `papertrail-ai/partials/file-card.php`.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $post ) || ! ( $post instanceof WP_Post ) ) {
	return;
}

$ptai_card_id     = (int) $post->ID;
$ptai_card_type   = (string) get_post_meta( $ptai_card_id, '_ptai_file_type', true );
$ptai_card_ext    = (string) get_post_meta( $ptai_card_id, '_ptai_file_ext', true );
$ptai_card_size   = absint( get_post_meta( $ptai_card_id, '_ptai_file_size', true ) );
$ptai_card_downs  = absint( get_post_meta( $ptai_card_id, '_ptai_download_count', true ) );
$ptai_card_fid    = absint( get_post_meta( $ptai_card_id, '_ptai_file_id', true ) );
$ptai_card_dl_url = $ptai_card_fid > 0
	? get_rest_url( null, 'papertrail-ai/v1/download/' . $ptai_card_id )
	: '';

$ptai_card_icon = 'ptai-icon-file';
if ( 'application/pdf' === $ptai_card_type ) {
	$ptai_card_icon = 'ptai-icon-pdf';
} elseif ( 0 === strpos( $ptai_card_type, 'image/' ) ) {
	$ptai_card_icon = 'ptai-icon-image';
} elseif ( 0 === strpos( $ptai_card_type, 'audio/' ) ) {
	$ptai_card_icon = 'ptai-icon-audio';
} elseif ( 0 === strpos( $ptai_card_type, 'video/' ) ) {
	$ptai_card_icon = 'ptai-icon-video';
}

$ptai_card_label = '' !== $ptai_card_ext ? strtoupper( $ptai_card_ext ) : $ptai_card_type;
?>
<li class="ptai-file-card">
	<div class="ptai-file-icon">
		<span class="<?php echo esc_attr( $ptai_card_icon ); ?>" aria-hidden="true"></span>
	</div>
	<div class="ptai-file-info">
		<a class="ptai-file-title" href="<?php echo esc_url( get_permalink( $post ) ); ?>">
			<?php echo esc_html( get_the_title( $post ) ); ?>
		</a>
		<span class="ptai-file-meta">
			<?php
			$ptai_meta_bits = array();
			if ( '' !== $ptai_card_label ) {
				$ptai_meta_bits[] = $ptai_card_label;
			}
			if ( $ptai_card_size > 0 ) {
				$ptai_meta_bits[] = size_format( $ptai_card_size );
			}
			$ptai_meta_bits[] = sprintf(
				/* translators: %s: download count. */
				_n( '%s download', '%s downloads', $ptai_card_downs, 'papertrail-ai' ),
				number_format_i18n( $ptai_card_downs )
			);
			echo esc_html( implode( ' · ', $ptai_meta_bits ) );
			?>
		</span>
		<?php if ( $ptai_card_fid > 0 ) : ?>
			<a class="ptai-download-link" href="<?php echo esc_url( $ptai_card_dl_url ); ?>">
				<?php esc_html_e( 'Download', 'papertrail-ai' ); ?>
			</a>
		<?php endif; ?>
	</div>
</li>
