<?php
/**
 * Single template for an Ask Adam Doc It document.
 *
 * Themes may override this by placing `ask-adam-doc-it/single-aadi.php`
 * in the active stylesheet.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main aadi-single">

	<?php
	while ( have_posts() ) :
		the_post();

		$aadi_post_id     = get_the_ID();
		$aadi_file_id     = absint( get_post_meta( $aadi_post_id, '_aadi_file_id', true ) );
		$aadi_file_type   = (string) get_post_meta( $aadi_post_id, '_aadi_file_type', true );
		$aadi_file_size   = absint( get_post_meta( $aadi_post_id, '_aadi_file_size', true ) );
		$aadi_file_ext    = (string) get_post_meta( $aadi_post_id, '_aadi_file_ext', true );
		$aadi_downloads   = absint( get_post_meta( $aadi_post_id, '_aadi_download_count', true ) );
		$aadi_doc_summary = (string) get_post_meta( $aadi_post_id, '_aadi_doc_summary', true );

		$aadi_download_url = $aadi_file_id > 0
			? get_rest_url( null, 'ask-adam-doc-it/v1/download/' . $aadi_post_id )
			: '';

		// Pick a dashicon-style class hint based on MIME type.
		$aadi_icon = 'aadi-icon-file';
		if ( 'application/pdf' === $aadi_file_type ) {
			$aadi_icon = 'aadi-icon-pdf';
		} elseif ( 0 === strpos( $aadi_file_type, 'image/' ) ) {
			$aadi_icon = 'aadi-icon-image';
		} elseif ( 0 === strpos( $aadi_file_type, 'audio/' ) ) {
			$aadi_icon = 'aadi-icon-audio';
		} elseif ( 0 === strpos( $aadi_file_type, 'video/' ) ) {
			$aadi_icon = 'aadi-icon-video';
		}
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'aadi-single__article' ); ?>>

			<header class="aadi-single__header">
				<h1 class="aadi-single__title"><?php the_title(); ?></h1>

				<?php
				$aadi_terms = get_the_term_list( $aadi_post_id, AADI_TAXONOMY, '<span class="aadi-single__terms-label">' . esc_html__( 'Categories:', 'ask-adam-doc-it' ) . '</span> ', ', ', '' );
				if ( $aadi_terms && ! is_wp_error( $aadi_terms ) ) {
					echo '<div class="aadi-single__terms">' . wp_kses_post( $aadi_terms ) . '</div>';
				}
				?>
			</header>

			<div class="aadi-single__meta">
				<span class="aadi-single__icon <?php echo esc_attr( $aadi_icon ); ?>" aria-hidden="true"></span>
				<?php if ( '' !== $aadi_file_ext ) : ?>
					<span class="aadi-single__ext"><?php echo esc_html( strtoupper( $aadi_file_ext ) ); ?></span>
				<?php endif; ?>
				<?php if ( $aadi_file_size > 0 ) : ?>
					<span class="aadi-single__size"><?php echo esc_html( size_format( $aadi_file_size ) ); ?></span>
				<?php endif; ?>
				<span class="aadi-single__downloads">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: download count. */
							_n( '%s download', '%s downloads', $aadi_downloads, 'ask-adam-doc-it' ),
							number_format_i18n( $aadi_downloads )
						)
					);
					?>
				</span>
			</div>

			<?php if ( $aadi_file_id > 0 ) : ?>
				<p class="aadi-single__download">
					<a href="<?php echo esc_url( $aadi_download_url ); ?>" class="aadi-download-btn button button-primary">
						<?php esc_html_e( 'Download', 'ask-adam-doc-it' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p class="aadi-single__no-file">
					<em><?php esc_html_e( 'No file is attached to this document.', 'ask-adam-doc-it' ); ?></em>
				</p>
			<?php endif; ?>

			<div class="aadi-single__content">
				<?php the_content(); ?>
			</div>

			<?php if ( '' !== trim( $aadi_doc_summary ) ) : ?>
				<aside class="aadi-single__summary">
					<h2 class="aadi-single__summary-title">
						<?php esc_html_e( 'Summary', 'ask-adam-doc-it' ); ?>
					</h2>
					<p><?php echo esc_html( $aadi_doc_summary ); ?></p>
				</aside>
			<?php endif; ?>

		</article>
		<?php
	endwhile;
	?>

</main>

<?php
get_footer();
