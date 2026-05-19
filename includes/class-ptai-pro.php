<?php
/**
 * Passive upgrade-marketing renderer.
 *
 * THIS CLASS IS INTENTIONALLY NON-FUNCTIONAL.
 *
 * PaperTrail AI is 100% free. There is no license enforcement,
 * no feature gating, and no Pro detection anywhere in this plugin.
 *
 * The methods here ONLY render static marketing copy in admin
 * screens that points interested users toward the Ask Adam Pro
 * product at askadamit.com/purchase. Nothing in this file affects
 * plugin behavior, capability, or feature availability.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PTAI_Pro
 *
 * Static marketing-notice helper. No logic, no checks, no gating.
 */
class PTAI_Pro {

	/**
	 * Hardcoded Ask Adam Pro purchase URL.
	 *
	 * @var string
	 */
	const PURCHASE_URL = 'https://askadamit.com/purchase';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Intentionally empty. No hooks. No state. No checks.
	}

	/**
	 * Render the passive upgrade sidebar in admin.
	 *
	 * Outputs a static HTML aside listing Ask Adam Pro features
	 * with a link to the purchase page. Renders the same content
	 * for every user — no conditionals.
	 *
	 * @return void
	 */
	public function render_upgrade_sidebar() {
		?>
		<aside class="ptai-upgrade-sidebar" aria-label="<?php esc_attr_e( 'Ask Adam Pro', 'papertrail-ai' ); ?>">
			<h2 class="ptai-upgrade-sidebar__title">
				<?php esc_html_e( 'Ask Adam Pro', 'papertrail-ai' ); ?>
			</h2>
			<p class="ptai-upgrade-sidebar__lead">
				<?php esc_html_e( 'Take your document workflow further with the full Ask Adam suite.', 'papertrail-ai' ); ?>
			</p>
			<ul class="ptai-upgrade-sidebar__features">
				<?php foreach ( $this->get_pro_features_list() as $ptai_feature ) : ?>
					<li><?php echo esc_html( $ptai_feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button button-primary"
					href="<?php echo esc_url( $this->get_purchase_url() ); ?>"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Learn more at askadamit.com', 'papertrail-ai' ); ?>
				</a>
			</p>
		</aside>
		<?php
	}

	/**
	 * Plain marketing copy. No conditionals. Pure strings.
	 *
	 * @return array<int,string>
	 */
	public function get_pro_features_list() {
		return array(
			__( 'Conversational document Q&A across your full library', 'papertrail-ai' ),
			__( 'Multi-document context retrieval with citations', 'papertrail-ai' ),
			__( 'Bulk embedding generation and re-indexing tools', 'papertrail-ai' ),
			__( 'Advanced usage analytics and reporting', 'papertrail-ai' ),
			__( 'Priority email support from the Ask Adam team', 'papertrail-ai' ),
		);
	}

	/**
	 * Hardcoded purchase URL. No logic.
	 *
	 * @return string
	 */
	public function get_purchase_url() {
		return self::PURCHASE_URL;
	}
}
