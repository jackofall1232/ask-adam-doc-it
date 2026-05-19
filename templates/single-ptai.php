<?php
/**
 * Single template for a PaperTrail AI document.
 *
 * Themes may override this by placing `papertrail-ai/single-ptai.php`
 * in the active stylesheet.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="primary" class="site-main ptai-single">

	<?php
	while ( have_posts() ) :
		the_post();

		$ptai_post_id     = get_the_ID();
		$ptai_file_id     = absint( get_post_meta( $ptai_post_id, '_ptai_file_id', true ) );
		$ptai_file_type   = (string) get_post_meta( $ptai_post_id, '_ptai_file_type', true );
		$ptai_file_size   = absint( get_post_meta( $ptai_post_id, '_ptai_file_size', true ) );
		$ptai_file_ext    = (string) get_post_meta( $ptai_post_id, '_ptai_file_ext', true );
		$ptai_downloads   = absint( get_post_meta( $ptai_post_id, '_ptai_download_count', true ) );
		$ptai_doc_summary = (string) get_post_meta( $ptai_post_id, '_ptai_doc_summary', true );

		$ptai_download_url = $ptai_file_id > 0
			? get_rest_url( null, 'papertrail-ai/v1/download/' . $ptai_post_id )
			: '';

		// Pick a dashicon-style class hint based on MIME type.
		$ptai_icon = 'ptai-icon-file';
		if ( 'application/pdf' === $ptai_file_type ) {
			$ptai_icon = 'ptai-icon-pdf';
		} elseif ( 0 === strpos( $ptai_file_type, 'image/' ) ) {
			$ptai_icon = 'ptai-icon-image';
		} elseif ( 0 === strpos( $ptai_file_type, 'audio/' ) ) {
			$ptai_icon = 'ptai-icon-audio';
		} elseif ( 0 === strpos( $ptai_file_type, 'video/' ) ) {
			$ptai_icon = 'ptai-icon-video';
		}
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'ptai-single__article' ); ?>>

			<header class="ptai-single__header">
				<h1 class="ptai-single__title"><?php the_title(); ?></h1>

				<?php
				$ptai_terms = get_the_term_list( $ptai_post_id, PTAI_TAXONOMY, '<span class="ptai-single__terms-label">' . esc_html__( 'Categories:', 'papertrail-ai' ) . '</span> ', ', ', '' );
				if ( $ptai_terms && ! is_wp_error( $ptai_terms ) ) {
					echo '<div class="ptai-single__terms">' . wp_kses_post( $ptai_terms ) . '</div>';
				}
				?>
			</header>

			<div class="ptai-single__meta">
				<span class="ptai-single__icon <?php echo esc_attr( $ptai_icon ); ?>" aria-hidden="true"></span>
				<?php if ( '' !== $ptai_file_ext ) : ?>
					<span class="ptai-single__ext"><?php echo esc_html( strtoupper( $ptai_file_ext ) ); ?></span>
				<?php endif; ?>
				<?php if ( $ptai_file_size > 0 ) : ?>
					<span class="ptai-single__size"><?php echo esc_html( size_format( $ptai_file_size ) ); ?></span>
				<?php endif; ?>
				<span class="ptai-single__downloads">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: download count. */
							_n( '%s download', '%s downloads', $ptai_downloads, 'papertrail-ai' ),
							number_format_i18n( $ptai_downloads )
						)
					);
					?>
				</span>
			</div>

			<?php if ( $ptai_file_id > 0 ) : ?>
				<p class="ptai-single__download">
					<a href="<?php echo esc_url( $ptai_download_url ); ?>" class="ptai-download-btn button button-primary">
						<?php esc_html_e( 'Download', 'papertrail-ai' ); ?>
					</a>
				</p>
			<?php else : ?>
				<p class="ptai-single__no-file">
					<em><?php esc_html_e( 'No file is attached to this document.', 'papertrail-ai' ); ?></em>
				</p>
			<?php endif; ?>

			<div class="ptai-single__content">
				<?php the_content(); ?>
			</div>

			<?php if ( '' !== trim( $ptai_doc_summary ) ) : ?>
				<aside class="ptai-single__summary">
					<h2 class="ptai-single__summary-title">
						<?php esc_html_e( 'Summary', 'papertrail-ai' ); ?>
					</h2>
					<p><?php echo esc_html( $ptai_doc_summary ); ?></p>
				</aside>
			<?php endif; ?>

		</article>
		<?php
	endwhile;
	?>

</main>

<?php
get_footer();
