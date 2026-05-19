<?php
/**
 * Passive upgrade-marketing renderer.
 *
 * THIS CLASS IS INTENTIONALLY NON-FUNCTIONAL.
 *
 * Ask Adam Doc It is 100% free. There is no license enforcement,
 * no feature gating, and no Pro detection anywhere in this plugin.
 *
 * The methods here ONLY render static marketing copy in admin
 * screens that points interested users toward the Ask Adam Pro
 * product at askadamit.com/purchase. Nothing in this file affects
 * plugin behavior, capability, or feature availability.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Pro
 *
 * Static marketing-notice helper. No logic, no checks, no gating.
 */
class AADI_Pro {

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
		<aside class="aadi-upgrade-sidebar" aria-label="<?php esc_attr_e( 'Ask Adam Pro', 'ask-adam-doc-it' ); ?>">
			<h2 class="aadi-upgrade-sidebar__title">
				<?php esc_html_e( 'Ask Adam Pro', 'ask-adam-doc-it' ); ?>
			</h2>
			<p class="aadi-upgrade-sidebar__lead">
				<?php esc_html_e( 'Take your document workflow further with the full Ask Adam suite.', 'ask-adam-doc-it' ); ?>
			</p>
			<ul class="aadi-upgrade-sidebar__features">
				<?php foreach ( $this->get_pro_features_list() as $aadi_feature ) : ?>
					<li><?php echo esc_html( $aadi_feature ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a class="button button-primary"
					href="<?php echo esc_url( $this->get_purchase_url() ); ?>"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Learn more at askadamit.com', 'ask-adam-doc-it' ); ?>
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
			__( 'Conversational document Q&A across your full library', 'ask-adam-doc-it' ),
			__( 'Multi-document context retrieval with citations', 'ask-adam-doc-it' ),
			__( 'Bulk embedding generation and re-indexing tools', 'ask-adam-doc-it' ),
			__( 'Advanced usage analytics and reporting', 'ask-adam-doc-it' ),
			__( 'Priority email support from the Ask Adam team', 'ask-adam-doc-it' ),
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
