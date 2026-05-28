<?php
/**
 * Admin-side controller.
 *
 * @package Ask_Adam_Doc_It
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AADI_Admin
 *
 * Handles admin scripts/styles, meta boxes, list-table columns,
 * the settings submenu, and plugin action links.
 */
class AADI_Admin {

	/**
	 * Page slug for the settings screen (sits under the CPT menu).
	 *
	 * @var string
	 */
	const SETTINGS_PAGE_SLUG = 'aadi-settings';

	/**
	 * Constructor. Intentionally side-effect-free — all hooks live in
	 * AADI_Loader::define_admin_hooks().
	 */
	public function __construct() {}

	/**
	 * Register the settings submenu under the Documents CPT.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . AADI_CPT,
			__( 'Ask Adam Doc It Settings', 'ask-adam-doc-it' ),
			__( 'Settings', 'ask-adam-doc-it' ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Determine whether the current screen is an Ask Adam Doc It screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private function is_plugin_screen( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen instanceof WP_Screen ) {
			if ( AADI_CPT === $screen->post_type ) {
				return true;
			}
			if ( AADI_TAXONOMY === $screen->taxonomy ) {
				return true;
			}
			if ( false !== strpos( (string) $screen->id, self::SETTINGS_PAGE_SLUG ) ) {
				return true;
			}
		}

		return false !== strpos( (string) $hook, self::SETTINGS_PAGE_SLUG );
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		// Tiny global file — loads on every admin page to keep the
		// sidebar clipboard icon teal regardless of current screen.
		wp_enqueue_style(
			'aadi-admin-global',
			AADI_PLUGIN_URL . 'admin/css/admin-global.css',
			array(),
			AADI_VERSION
		);

		// Full admin styles only on plugin screens.
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'aadi-admin',
			AADI_PLUGIN_URL . 'admin/css/admin.css',
			array( 'aadi-admin-global' ),
			AADI_VERSION
		);
	}

	/**
	 * Enqueue admin scripts and the media library.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_script(
			'aadi-admin',
			AADI_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			AADI_VERSION,
			true
		);

		wp_localize_script(
			'aadi-admin',
			'aadiAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'aadi_admin_nonce' ),
				'strings'  => array(
					'select_file'       => __( 'Select or upload a file', 'ask-adam-doc-it' ),
					'use_this_file'     => __( 'Use this file', 'ask-adam-doc-it' ),
					'replace_file'      => __( 'Replace file', 'ask-adam-doc-it' ),
					'remove_file'       => __( 'Remove file', 'ask-adam-doc-it' ),
					'no_file_attached'  => __( 'No file attached.', 'ask-adam-doc-it' ),
					'confirm_remove'    => __( 'Remove the attached file? This will not delete it from the media library.', 'ask-adam-doc-it' ),
				),
			)
		);
	}

	/**
	 * Register meta boxes for the aadi_file CPT.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'aadi_file_details',
			__( 'File Details', 'ask-adam-doc-it' ),
			array( $this, 'render_file_meta_box' ),
			AADI_CPT,
			'side',
			'high'
		);

		add_meta_box(
			'aadi_doc_summary_box',
			__( 'AI Search Summary', 'ask-adam-doc-it' ),
			array( $this, 'render_doc_summary_meta_box' ),
			AADI_CPT,
			'normal',
			'high'
		);

		add_meta_box(
			'aadi_upgrade_sidebar',
			__( 'Ask Adam Pro', 'ask-adam-doc-it' ),
			array( $this, 'render_upgrade_meta_box' ),
			AADI_CPT,
			'side',
			'low'
		);
	}

	/**
	 * Render the AI search summary meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_doc_summary_meta_box( $post ) {
		$summary  = (string) get_post_meta( $post->ID, '_aadi_doc_summary', true );
		$settings = new AADI_Settings();
		$ai_on    = $settings->is_ai_enabled();

		wp_nonce_field( 'aadi_save_doc_summary', 'aadi_doc_summary_nonce' );
		?>
		<p class="aadi-meta-description">
			<?php esc_html_e( 'Used by AI search to understand this document. Write 1-3 sentences describing the content, date, and topic. The more specific, the better the search results.', 'ask-adam-doc-it' ); ?>
		</p>
		<div class="aadi-doc-summary-wrap">
			<textarea
				id="aadi_doc_summary"
				name="_aadi_doc_summary"
				class="large-text"
				rows="4"
				maxlength="500"
			><?php echo esc_textarea( $summary ); ?></textarea>
			<p class="aadi-char-counter">
				<span class="aadi-char-count">0</span> / 500
				<?php esc_html_e( 'characters', 'ask-adam-doc-it' ); ?>
			</p>
		</div>
		<?php if ( ! $ai_on ) : ?>
			<p class="aadi-ai-disabled-notice">
				<?php esc_html_e( 'Configure an AI provider under Settings → Connectors to enable AI search.', 'ask-adam-doc-it' ); ?>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the file details meta box.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public function render_file_meta_box( $post ) {
		wp_nonce_field( 'aadi_save_file_meta', 'aadi_file_meta_nonce' );

		$file_id         = (int) get_post_meta( $post->ID, '_aadi_file_id', true );
		$file_type       = (string) get_post_meta( $post->ID, '_aadi_file_type', true );
		$file_size       = (int) get_post_meta( $post->ID, '_aadi_file_size', true );
		$file_ext        = (string) get_post_meta( $post->ID, '_aadi_file_ext', true );
		$download_count  = (int) get_post_meta( $post->ID, '_aadi_download_count', true );
		$last_downloaded = (string) get_post_meta( $post->ID, '_aadi_last_downloaded', true );

		$attachment_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
		$file_name      = $file_id ? get_the_title( $file_id ) : '';

		echo '<div class="aadi-file-meta">';
		printf(
			'<input type="hidden" id="aadi_file_id" name="aadi_file_id" value="%d" />',
			(int) $file_id
		);

		echo '<div class="aadi-file-meta__current" id="aadi-file-meta-current">';
		if ( $file_id && $attachment_url ) {
			$icon = $this->icon_for_mime( $file_type );
			echo '<p><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span> ';
			echo '<strong>' . esc_html( $file_name ) . '</strong></p>';

			echo '<ul class="aadi-meta-list">';
			if ( $file_ext ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %s: file extension. */
						__( 'Extension: %s', 'ask-adam-doc-it' ),
						strtoupper( $file_ext )
					)
				) . '</li>';
			}
			if ( $file_type ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %s: MIME type. */
						__( 'Type: %s', 'ask-adam-doc-it' ),
						$file_type
					)
				) . '</li>';
			}
			if ( $file_size ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %s: human-readable file size. */
						__( 'Size: %s', 'ask-adam-doc-it' ),
						size_format( $file_size )
					)
				) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p><em>' . esc_html__( 'No file attached.', 'ask-adam-doc-it' ) . '</em></p>';
		}
		echo '</div>';

		printf(
			'<p><button type="button" class="button" id="aadi-attach-file">%s</button> ',
			esc_html( $file_id ? __( 'Replace File', 'ask-adam-doc-it' ) : __( 'Attach File', 'ask-adam-doc-it' ) )
		);
		if ( $file_id ) {
			printf(
				'<button type="button" class="button-link" id="aadi-remove-file">%s</button>',
				esc_html__( 'Remove', 'ask-adam-doc-it' )
			);
		}
		echo '</p>';

		echo '<hr />';
		echo '<p><strong>' . esc_html__( 'Download statistics', 'ask-adam-doc-it' ) . '</strong></p>';
		echo '<ul class="aadi-meta-list aadi-meta-list--flush">';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: number of downloads. */
				_n( '%d download', '%d downloads', $download_count, 'ask-adam-doc-it' ),
				$download_count
			)
		) . '</li>';
		if ( '' !== $last_downloaded ) {
			$timestamp = strtotime( $last_downloaded );
			$display   = $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $last_downloaded;
			echo '<li>' . esc_html(
				sprintf(
					/* translators: %s: formatted date/time. */
					__( 'Last download: %s', 'ask-adam-doc-it' ),
					$display
				)
			) . '</li>';
		} else {
			echo '<li>' . esc_html__( 'Never downloaded.', 'ask-adam-doc-it' ) . '</li>';
		}
		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Render the upgrade sidebar meta box.
	 *
	 * @return void
	 */
	public function render_upgrade_meta_box() {
		if ( class_exists( 'AADI_Pro' ) ) {
			$pro = new AADI_Pro();
			$pro->render_upgrade_sidebar();
		}
	}

	/**
	 * Save meta box data.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_meta_box( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->save_doc_summary( $post_id );

		if ( ! isset( $_POST['aadi_file_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['aadi_file_meta_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'aadi_save_file_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$file_id = isset( $_POST['aadi_file_id'] ) ? absint( wp_unslash( $_POST['aadi_file_id'] ) ) : 0;

		if ( 0 === $file_id ) {
			delete_post_meta( $post_id, '_aadi_file_id' );
			delete_post_meta( $post_id, '_aadi_file_type' );
			delete_post_meta( $post_id, '_aadi_file_size' );
			delete_post_meta( $post_id, '_aadi_file_ext' );
			return;
		}

		// Validate the attachment exists and is an attachment.
		$attachment = get_post( $file_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return;
		}

		$path = get_attached_file( $file_id );
		if ( ! $path ) {
			return;
		}

		$mime = (string) get_post_mime_type( $file_id );
		$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );
		$size = file_exists( $path ) ? (int) filesize( $path ) : 0;

		update_post_meta( $post_id, '_aadi_file_id', $file_id );
		update_post_meta( $post_id, '_aadi_file_type', sanitize_text_field( $mime ) );
		update_post_meta( $post_id, '_aadi_file_size', $size );
		update_post_meta( $post_id, '_aadi_file_ext', sanitize_text_field( $ext ) );
	}

	/**
	 * Add custom columns to the CPT list table.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_admin_columns( $columns ) {
		$new_columns = array();

		if ( isset( $columns['cb'] ) ) {
			$new_columns['cb'] = $columns['cb'];
		}

		$new_columns['title']           = isset( $columns['title'] ) ? $columns['title'] : __( 'Title', 'ask-adam-doc-it' );
		$new_columns['file_type']       = __( 'File Type', 'ask-adam-doc-it' );
		$new_columns['file_size']       = __( 'File Size', 'ask-adam-doc-it' );
		$new_columns['download_count']  = __( 'Downloads', 'ask-adam-doc-it' );
		$new_columns['last_downloaded'] = __( 'Last Downloaded', 'ask-adam-doc-it' );

		// Preserve the taxonomy column if WP added it.
		$tax_col = 'taxonomy-' . AADI_TAXONOMY;
		if ( isset( $columns[ $tax_col ] ) ) {
			$new_columns[ $tax_col ] = $columns[ $tax_col ];
		}

		$new_columns['date'] = isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'ask-adam-doc-it' );

		return $new_columns;
	}

	/**
	 * Populate custom admin columns.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function populate_admin_column( $column, $post_id ) {
		switch ( $column ) {
			case 'file_type':
				$mime = (string) get_post_meta( $post_id, '_aadi_file_type', true );
				$ext  = (string) get_post_meta( $post_id, '_aadi_file_ext', true );
				if ( '' === $mime && '' === $ext ) {
					echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No file', 'ask-adam-doc-it' ) . '</span>';
					return;
				}
				$icon  = $this->icon_for_mime( $mime );
				$label = '' !== $ext ? strtoupper( $ext ) : $mime;
				printf(
					'<span class="dashicons %1$s" aria-hidden="true"></span> <span>%2$s</span>',
					esc_attr( $icon ),
					esc_html( $label )
				);
				break;

			case 'file_size':
				$size = (int) get_post_meta( $post_id, '_aadi_file_size', true );
				echo $size > 0 ? esc_html( size_format( $size ) ) : '<span aria-hidden="true">—</span>';
				break;

			case 'download_count':
				$count = (int) get_post_meta( $post_id, '_aadi_download_count', true );
				echo esc_html( number_format_i18n( $count ) );
				break;

			case 'last_downloaded':
				$last = (string) get_post_meta( $post_id, '_aadi_last_downloaded', true );
				if ( '' === $last ) {
					echo '<span aria-hidden="true">—</span>';
					return;
				}
				$timestamp = strtotime( $last );
				$format    = get_option( 'date_format' );
				echo $timestamp ? esc_html( wp_date( $format, $timestamp ) ) : esc_html( $last );
				break;
		}
	}

	/**
	 * Mark custom columns sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function make_admin_columns_sortable( $columns ) {
		$columns['download_count']  = 'download_count';
		$columns['last_downloaded'] = 'last_downloaded';
		return $columns;
	}

	/**
	 * Translate sortable column requests into meta_query orderby.
	 *
	 * @param WP_Query $query Current query.
	 * @return void
	 */
	public function handle_sortable_columns_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( AADI_CPT !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'download_count' === $orderby ) {
			$query->set( 'meta_key', '_aadi_download_count' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'last_downloaded' === $orderby ) {
			$query->set( 'meta_key', '_aadi_last_downloaded' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Add action links on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$settings_url = admin_url( 'edit.php?post_type=' . AADI_CPT . '&page=' . self::SETTINGS_PAGE_SLUG );

		$prepend = array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'ask-adam-doc-it' )
			),
			'upgrade'  => sprintf(
				'<a href="%1$s" class="aadi-action-link-upgrade" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( 'https://askadamit.com/purchase' ),
				esc_html__( 'Upgrade to Pro', 'ask-adam-doc-it' )
			),
		);

		return array_merge( $prepend, $links );
	}

	/**
	 * Render the plugin settings page (two-column layout).
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap aadi-settings-wrap aadi-no-js">

			<div class="aadi-admin-hero">
				<div class="aadi-admin-hero__inner">
					<div class="aadi-admin-hero__icon" aria-hidden="true">
						<span class="dashicons dashicons-clipboard"></span>
					</div>
					<div class="aadi-admin-hero__text">
						<h1 class="aadi-admin-hero__title">
							<img
								src="<?php echo esc_url( AADI_PLUGIN_URL . 'assets/images/hero.webp' ); ?>"
								alt="<?php esc_attr_e( 'Ask Adam Doc It', 'ask-adam-doc-it' ); ?>"
								class="aadi-admin-hero__logo"
							/>
							<span class="aadi-admin-hero__title-fallback">
								<?php esc_html_e( 'Ask Adam Doc It', 'ask-adam-doc-it' ); ?>
							</span>
						</h1>
						<p class="aadi-admin-hero__subtitle">
							<?php esc_html_e(
								'Smart Document Library — Part of the Ask Adam Suite',
								'ask-adam-doc-it'
							); ?>
						</p>
					</div>
					<div class="aadi-admin-hero__badge">
						<span class="aadi-version-badge">
							v<?php echo esc_html( AADI_VERSION ); ?>
						</span>
					</div>
				</div>
			</div>

			<?php settings_errors(); ?>

			<nav class="aadi-tab-nav" role="tablist"
				aria-label="<?php esc_attr_e( 'Settings sections', 'ask-adam-doc-it' ); ?>">
				<button class="aadi-tab-btn aadi-tab-btn--active"
						id="tab-btn-ai"
						role="tab"
						aria-selected="true"
						aria-controls="aadi-tab-ai"
						data-tab="aadi-tab-ai">
					<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Configuration', 'ask-adam-doc-it' ); ?>
				</button>
				<button class="aadi-tab-btn"
						id="tab-btn-uploads"
						role="tab"
						aria-selected="false"
						aria-controls="aadi-tab-uploads"
						data-tab="aadi-tab-uploads">
					<span class="dashicons dashicons-upload" aria-hidden="true"></span>
					<?php esc_html_e( 'Upload Settings', 'ask-adam-doc-it' ); ?>
				</button>
				<button class="aadi-tab-btn"
						id="tab-btn-access"
						role="tab"
						aria-selected="false"
						aria-controls="aadi-tab-access"
						data-tab="aadi-tab-access">
					<span class="dashicons dashicons-groups" aria-hidden="true"></span>
					<?php esc_html_e( 'Access Control', 'ask-adam-doc-it' ); ?>
				</button>
				<button class="aadi-tab-btn"
						id="tab-btn-advanced"
						role="tab"
						aria-selected="false"
						aria-controls="aadi-tab-advanced"
						data-tab="aadi-tab-advanced">
					<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
					<?php esc_html_e( 'Advanced', 'ask-adam-doc-it' ); ?>
				</button>
				<button class="aadi-tab-btn"
						id="tab-btn-help"
						role="tab"
						aria-selected="false"
						aria-controls="aadi-tab-help"
						data-tab="aadi-tab-help">
					<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
					<?php esc_html_e( 'How to Use', 'ask-adam-doc-it' ); ?>
				</button>
			</nav>

			<div class="aadi-settings-layout">
				<div class="aadi-settings-main">

					<form method="post" action="options.php">
						<?php
						settings_fields( AADI_Settings::SETTINGS_GROUP );

						global $wp_settings_sections;
						$page_sections = isset( $wp_settings_sections[ AADI_Settings::PAGE_SLUG ] )
							? $wp_settings_sections[ AADI_Settings::PAGE_SLUG ]
							: array();

						$sections = array(
							'aadi-tab-ai'       => array(
								'section_id' => 'aadi_section_ai',
								'icon'       => 'superhero-alt',
								'title'      => __( 'AI Configuration', 'ask-adam-doc-it' ),
							),
							'aadi-tab-uploads'  => array(
								'section_id' => 'aadi_section_uploads',
								'icon'       => 'upload',
								'title'      => __( 'Upload Settings', 'ask-adam-doc-it' ),
							),
							'aadi-tab-access'   => array(
								'section_id' => 'aadi_section_access',
								'icon'       => 'groups',
								'title'      => __( 'Access Control', 'ask-adam-doc-it' ),
							),
							'aadi-tab-advanced' => array(
								'section_id' => 'aadi_section_advanced',
								'icon'       => 'admin-tools',
								'title'      => __( 'Advanced', 'ask-adam-doc-it' ),
							),
						);

						$first = true;
						foreach ( $sections as $tab_id => $info ) {
							$section_id = $info['section_id'];
							$btn_id     = str_replace( 'aadi-tab-', 'tab-btn-', $tab_id );
							$active     = $first ? ' aadi-tab-panel--active' : '';
							?>
							<div id="<?php echo esc_attr( $tab_id ); ?>"
								class="aadi-tab-panel<?php echo esc_attr( $active ); ?>"
								role="tabpanel"
								aria-labelledby="<?php echo esc_attr( $btn_id ); ?>">
								<div class="aadi-panel-card">
									<h2 class="aadi-panel-heading">
										<span class="dashicons dashicons-<?php echo esc_attr( $info['icon'] ); ?>"
											aria-hidden="true"></span>
										<?php echo esc_html( $info['title'] ); ?>
									</h2>
									<?php
									// Render the section description callback. The
									// section title is intentionally suppressed here —
									// the panel heading above and the tab button both
									// already name the section. do_settings_fields()
									// below only outputs <tr> rows, so without this
									// the descriptions registered via
									// add_settings_section would silently disappear.
									if ( isset( $page_sections[ $section_id ]['callback'] )
										&& is_callable( $page_sections[ $section_id ]['callback'] ) ) {
										echo '<div class="aadi-panel-description">';
										call_user_func(
											$page_sections[ $section_id ]['callback'],
											$page_sections[ $section_id ]
										);
										echo '</div>';
									}
									?>
									<table class="form-table" role="presentation">
										<tbody>
											<?php
											do_settings_fields(
												AADI_Settings::PAGE_SLUG,
												$section_id
											);
											?>
										</tbody>
									</table>
								</div>
							</div>
							<?php
							$first = false;
						}

						// Render any extra sections registered by add-ons or site
						// code against this page slug that aren't part of our tab
						// layout. They appear as themed cards below the tab panels
						// so their UI stays reachable (and visually consistent)
						// instead of silently disappearing.
						$known_section_ids = array();
						foreach ( $sections as $info ) {
							$known_section_ids[] = $info['section_id'];
						}
						foreach ( $page_sections as $section ) {
							if ( ! is_array( $section ) || empty( $section['id'] ) ) {
								continue;
							}
							if ( in_array( $section['id'], $known_section_ids, true ) ) {
								continue;
							}
							echo '<div class="aadi-panel-card">';
							if ( ! empty( $section['title'] ) ) {
								echo '<h2 class="aadi-panel-heading">'
									. esc_html( $section['title'] )
									. '</h2>';
							}
							if ( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
								echo '<div class="aadi-panel-description">';
								call_user_func( $section['callback'], $section );
								echo '</div>';
							}
							echo '<table class="form-table" role="presentation"><tbody>';
							do_settings_fields( AADI_Settings::PAGE_SLUG, $section['id'] );
							echo '</tbody></table>';
							echo '</div>';
						}
						?>

						<?php submit_button(); ?>
					</form>

					<div id="aadi-tab-help"
						class="aadi-tab-panel"
						role="tabpanel"
						aria-labelledby="tab-btn-help">
						<?php $this->render_help_tab(); ?>
					</div>

				</div>
				<div class="aadi-settings-sidebar">
					<?php
					if ( class_exists( 'AADI_Pro' ) ) {
						$pro = new AADI_Pro();
						$pro->render_upgrade_sidebar();
					}
					?>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Render the "How to Use" help tab.
	 *
	 * Pure static documentation — no form elements, no nonces.
	 *
	 * @return void
	 */
	private function render_help_tab() {
		?>
		<div class="aadi-help">

			<!-- Getting Started -->
			<div class="aadi-help__section">
				<h2 class="aadi-help__heading">
					<span class="dashicons dashicons-rocket"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Getting Started', 'ask-adam-doc-it' ); ?>
				</h2>
				<ol class="aadi-help__steps">
					<li>
						<strong><?php esc_html_e( 'Add a document', 'ask-adam-doc-it' ); ?></strong>
						<p><?php esc_html_e(
							'Go to Ask Adam Doc It → Add New. Give your document a title, attach a file using the File Details meta box, and assign a category.',
							'ask-adam-doc-it'
						); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Write an AI Search Summary', 'ask-adam-doc-it' ); ?></strong>
						<p><?php esc_html_e(
							'Fill in the AI Search Summary field with 1–3 sentences describing the document content, date, and topic. This is what AI search uses to understand your document.',
							'ask-adam-doc-it'
						); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Enable AI search (optional)', 'ask-adam-doc-it' ); ?></strong>
						<p><?php
						printf(
							wp_kses(
								/* translators: %s: Connectors settings URL */
								__( 'AI features use the WordPress 7.0 built-in AI Client. Install an AI provider plugin and configure it under <a href="%s">Settings → Connectors</a>. The plugin works fully without it using keyword search.', 'ask-adam-doc-it' ),
								array( 'a' => array( 'href' => array() ) )
							),
							esc_url( admin_url( 'options-general.php?page=connectors' ) )
						);
						?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Embed your library', 'ask-adam-doc-it' ); ?></strong>
						<p><?php esc_html_e(
							'Use the shortcode or Gutenberg block on any page or post to display your document library.',
							'ask-adam-doc-it'
						); ?></p>
					</li>
				</ol>
			</div>

			<!-- Shortcode Reference -->
			<div class="aadi-help__section">
				<h2 class="aadi-help__heading">
					<span class="dashicons dashicons-shortcode"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Shortcode Reference', 'ask-adam-doc-it' ); ?>
				</h2>
				<p><?php esc_html_e(
					'Use the [ask_adam_doc_it] shortcode on any page or post.',
					'ask-adam-doc-it'
				); ?></p>

				<table class="aadi-help__table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Attribute', 'ask-adam-doc-it' ); ?></th>
							<th><?php esc_html_e( 'Default', 'ask-adam-doc-it' ); ?></th>
							<th><?php esc_html_e( 'Description', 'ask-adam-doc-it' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>category</code></td>
							<td><code>""</code></td>
							<td><?php esc_html_e( 'Category slug or term ID to filter results.', 'ask-adam-doc-it' ); ?></td>
						</tr>
						<tr>
							<td><code>per_page</code></td>
							<td><code>10</code></td>
							<td><?php esc_html_e( 'Number of documents per page (1–50).', 'ask-adam-doc-it' ); ?></td>
						</tr>
						<tr>
							<td><code>columns</code></td>
							<td><code>1</code></td>
							<td><?php esc_html_e( 'Display in 1 or 2 columns.', 'ask-adam-doc-it' ); ?></td>
						</tr>
						<tr>
							<td><code>show_search</code></td>
							<td><code>true</code></td>
							<td><?php esc_html_e( 'Show or hide the search bar.', 'ask-adam-doc-it' ); ?></td>
						</tr>
						<tr>
							<td><code>mode</code></td>
							<td><code>auto</code></td>
							<td><?php esc_html_e( 'Search mode: auto, ai, or core. Auto uses AI when configured, falls back to keyword search.', 'ask-adam-doc-it' ); ?></td>
						</tr>
						<tr>
							<td><code>orderby</code></td>
							<td><code>date</code></td>
							<td><?php esc_html_e( 'Sort by: date, title, or downloads.', 'ask-adam-doc-it' ); ?></td>
						</tr>
						<tr>
							<td><code>order</code></td>
							<td><code>DESC</code></td>
							<td><?php esc_html_e( 'Sort direction: DESC or ASC.', 'ask-adam-doc-it' ); ?></td>
						</tr>
					</tbody>
				</table>

				<!-- Code examples -->
				<div class="aadi-help__examples">
					<p class="aadi-help__example-label">
						<?php esc_html_e( 'Examples:', 'ask-adam-doc-it' ); ?>
					</p>
					<div class="aadi-help__code-block">
						<code>[ask_adam_doc_it]</code>
						<span class="aadi-help__code-note">
							<?php esc_html_e( '— Show all documents', 'ask-adam-doc-it' ); ?>
						</span>
					</div>
					<div class="aadi-help__code-block">
						<code>[ask_adam_doc_it category="newsletters" per_page="20" columns="2"]</code>
						<span class="aadi-help__code-note">
							<?php esc_html_e( '— Newsletters in 2-column grid', 'ask-adam-doc-it' ); ?>
						</span>
					</div>
					<div class="aadi-help__code-block">
						<code>[ask_adam_doc_it mode="core" show_search="false"]</code>
						<span class="aadi-help__code-note">
							<?php esc_html_e( '— Keyword search, no search bar', 'ask-adam-doc-it' ); ?>
						</span>
					</div>
				</div>
			</div>

			<!-- AI Search Explained -->
			<div class="aadi-help__section">
				<h2 class="aadi-help__heading">
					<span class="dashicons dashicons-superhero-alt"
						aria-hidden="true"></span>
					<?php esc_html_e( 'How AI Search Works', 'ask-adam-doc-it' ); ?>
				</h2>
				<p><?php esc_html_e(
					'AI search uses OpenAI embeddings to find documents by meaning rather than exact keywords. A visitor searching for "quarterly financial results" will find documents about "Q3 earnings report" even if those words do not match.',
					'ask-adam-doc-it'
				); ?></p>
				<h3 class="aadi-help__subheading">
					<?php esc_html_e( 'What gets embedded', 'ask-adam-doc-it' ); ?>
				</h3>
				<ul class="aadi-help__list">
					<li><?php esc_html_e( 'Document title', 'ask-adam-doc-it' ); ?></li>
					<li><?php esc_html_e( 'Post excerpt', 'ask-adam-doc-it' ); ?></li>
					<li><?php esc_html_e( 'AI Search Summary field (most important)', 'ask-adam-doc-it' ); ?></li>
					<li><?php esc_html_e( 'Category names', 'ask-adam-doc-it' ); ?></li>
				</ul>
				<h3 class="aadi-help__subheading">
					<?php esc_html_e( 'Embedding status badges', 'ask-adam-doc-it' ); ?>
				</h3>
				<ul class="aadi-help__badge-legend">
					<li>
						<span class="aadi-status-badge aadi-status-badge--current">
							<?php esc_html_e( 'Embedding current', 'ask-adam-doc-it' ); ?>
						</span>
						<?php esc_html_e( '— Document is indexed and ready.', 'ask-adam-doc-it' ); ?>
					</li>
					<li>
						<span class="aadi-status-badge aadi-status-badge--stale">
							<?php esc_html_e( 'Embedding stale', 'ask-adam-doc-it' ); ?>
						</span>
						<?php esc_html_e( '— Document was edited since last index. Will update automatically on next save.', 'ask-adam-doc-it' ); ?>
					</li>
					<li>
						<span class="aadi-status-badge aadi-status-badge--missing">
							<?php esc_html_e( 'No embedding', 'ask-adam-doc-it' ); ?>
						</span>
						<?php esc_html_e( '— Not yet indexed. Save the document or use Regenerate Embedding.', 'ask-adam-doc-it' ); ?>
					</li>
				</ul>
				<p class="aadi-help__note">
					<span class="dashicons dashicons-info-outline"
						aria-hidden="true"></span>
					<?php esc_html_e(
						'API cost note: Ask Adam Doc It uses text-embedding-3-small — one of OpenAI\'s most affordable models. Indexing a typical document summary costs a fraction of a cent.',
						'ask-adam-doc-it'
					); ?>
				</p>
			</div>

			<!-- Download Tracking -->
			<div class="aadi-help__section">
				<h2 class="aadi-help__heading">
					<span class="dashicons dashicons-download"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Download Tracking', 'ask-adam-doc-it' ); ?>
				</h2>
				<p><?php esc_html_e(
					'Every file download is routed through a WordPress REST endpoint which increments the download counter before serving the file. Counts are rate-limited to one per document per hour using a hashed token — no IP addresses or personal data are stored.',
					'ask-adam-doc-it'
				); ?></p>
				<p><?php esc_html_e(
					'Download counts appear in the Documents list table and are sortable. Use them to identify your most popular documents and retire unused ones.',
					'ask-adam-doc-it'
				); ?></p>
			</div>

			<!-- Need More -->
			<div class="aadi-help__section aadi-help__section--cta">
				<h2 class="aadi-help__heading">
					<span class="dashicons dashicons-external"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Need More?', 'ask-adam-doc-it' ); ?>
				</h2>
				<p><?php esc_html_e(
					'Ask Adam Doc It is part of the Ask Adam suite. Ask Adam Pro adds conversational document Q&A, multi-document context retrieval, bulk indexing, analytics, and priority support.',
					'ask-adam-doc-it'
				); ?></p>
				<a href="<?php echo esc_url( 'https://askadamit.com/purchase' ); ?>"
					class="button button-primary aadi-help__cta-btn"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Learn about Ask Adam Pro', 'ask-adam-doc-it' ); ?>
				</a>
				<a href="<?php echo esc_url( 'https://askadamit.com' ); ?>"
					class="button aadi-help__cta-btn"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Visit askadamit.com', 'ask-adam-doc-it' ); ?>
				</a>
			</div>

		</div><!-- .aadi-help -->
		<?php
	}

	/**
	 * Choose a Dashicon for a MIME type.
	 *
	 * @param string $mime MIME type.
	 * @return string Dashicon class name.
	 */
	private function icon_for_mime( $mime ) {
		$mime = (string) $mime;
		if ( 0 === strpos( $mime, 'image/' ) ) {
			return 'dashicons-format-image';
		}
		if ( 0 === strpos( $mime, 'video/' ) ) {
			return 'dashicons-format-video';
		}
		if ( 0 === strpos( $mime, 'audio/' ) ) {
			return 'dashicons-format-audio';
		}
		if ( 'application/pdf' === $mime ) {
			return 'dashicons-pdf';
		}
		if ( false !== strpos( $mime, 'word' ) || false !== strpos( $mime, 'text/' ) ) {
			return 'dashicons-media-document';
		}
		if ( false !== strpos( $mime, 'sheet' ) || false !== strpos( $mime, 'excel' ) || 'text/csv' === $mime ) {
			return 'dashicons-media-spreadsheet';
		}
		if ( false !== strpos( $mime, 'presentation' ) || false !== strpos( $mime, 'powerpoint' ) ) {
			return 'dashicons-media-interactive';
		}
		return 'dashicons-media-default';
	}

	/**
	 * Persist the AI search summary field.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function save_doc_summary( $post_id ) {
		if ( ! isset( $_POST['aadi_doc_summary_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_key( wp_unslash( $_POST['aadi_doc_summary_nonce'] ) ),
			'aadi_save_doc_summary'
		) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$summary = isset( $_POST['_aadi_doc_summary'] )
			? sanitize_textarea_field( wp_unslash( $_POST['_aadi_doc_summary'] ) )
			: '';
		// mb_substr to avoid splitting UTF-8 characters at the 500-char cap.
		$summary = function_exists( 'mb_substr' )
			? mb_substr( $summary, 0, 500, 'UTF-8' )
			: substr( $summary, 0, 500 );

		update_post_meta( $post_id, '_aadi_doc_summary', $summary );
	}

	/**
	 * Render the AI status admin notice on Ask Adam Doc It screens.
	 *
	 * @return void
	 */
	public function render_ai_status_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || AADI_CPT !== $screen->post_type ) {
			return;
		}

		// Bulk regenerate summary takes precedence on the list table.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['aadi_bulk_regen'] ) && 'done' === $_GET['aadi_bulk_regen'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$processed = isset( $_GET['aadi_regen_processed'] ) ? absint( wp_unslash( $_GET['aadi_regen_processed'] ) ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skipped   = isset( $_GET['aadi_regen_skipped'] ) ? absint( wp_unslash( $_GET['aadi_regen_skipped'] ) ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$failed    = isset( $_GET['aadi_regen_failed'] ) ? absint( wp_unslash( $_GET['aadi_regen_failed'] ) ) : 0;

			$message = sprintf(
				/* translators: 1: regenerated count, 2: skipped count */
				__( 'Regenerated %1$d embeddings. Skipped %2$d (already current).', 'ask-adam-doc-it' ),
				$processed,
				$skipped
			);
			if ( $failed > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: failed count */
					__( '%d failed — check your AI provider configuration under Settings → Connectors.', 'ask-adam-doc-it' ),
					$failed
				);
			}

			$class = $failed > 0 ? 'notice-warning' : 'notice-success';
			printf(
				'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $class ),
				esc_html( $message )
			);
		}

		// Regeneration result feedback (must precede other notices).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$regen      = isset( $_GET['aadi_regen'] ) ? sanitize_key( wp_unslash( $_GET['aadi_regen'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$regen_post = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;

		if ( 'success' === $regen && $regen_post > 0 ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Embedding regenerated successfully.', 'ask-adam-doc-it' )
				. '</p></div>';
		} elseif ( 'failed' === $regen && $regen_post > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: Connectors settings URL */
						__(
							'Embedding regeneration failed. <a href="%s">Check your AI provider configuration under Settings → Connectors</a>.',
							'ask-adam-doc-it'
						),
						esc_url( admin_url( 'options-general.php?page=connectors' ) )
					),
					array( 'a' => array( 'href' => array() ) )
				)
			);
		}

		// State 1 — WordPress < 7.0 or AI Client not available.
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			echo '<div class="notice notice-info"><p>' . wp_kses(
				sprintf(
					/* translators: %s: plugin URL */
					__( 'Ask Adam Doc It AI features require WordPress 7.0 or higher with an AI provider configured. <a href="%s">Learn more</a>.', 'ask-adam-doc-it' ),
					esc_url( 'https://wordpress.org/plugins/ai-provider-for-openai/' )
				),
				array( 'a' => array( 'href' => array() ) )
			) . '</p></div>';
			return;
		}

		// State 2 — AI Client present but no provider configured. Reuse the
		// defensively-guarded helper rather than chaining on the prompt here.
		if ( ! AADI_Settings::is_ai_enabled() ) {
			echo '<div class="notice notice-info"><p>' . wp_kses(
				sprintf(
					/* translators: %s: connectors settings URL */
					__( 'AI features are not yet configured. Install an AI provider plugin and configure it under <a href="%s">Settings → Connectors</a>.', 'ask-adam-doc-it' ),
					esc_url( admin_url( 'options-general.php?page=connectors' ) )
				),
				array( 'a' => array( 'href' => array() ) )
			) . '</p></div>';
			return;
		}

		// State 3 — AI active.
		$dismissed_key = 'aadi_ai_notice_dismissed_' . get_current_user_id();
		if ( get_user_meta( get_current_user_id(), $dismissed_key, true ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible aadi-ai-active-notice"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'aadi_dismiss_notice' ) ); ?>">
			<p><?php esc_html_e( 'Ask Adam Doc It: AI search is active.', 'ask-adam-doc-it' ); ?></p>
		</div>
		<?php
	}

	/**
	 * AJAX: persist per-user dismissal of the "AI active" notice.
	 *
	 * @return void
	 */
	public function handle_dismiss_ai_notice() {
		check_ajax_referer( 'aadi_dismiss_notice', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$key = 'aadi_ai_notice_dismissed_' . get_current_user_id();
		update_user_meta( get_current_user_id(), $key, true );

		wp_send_json_success();
		wp_die();
	}

	/**
	 * Add embedding status badge and regenerate link to row actions.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Current post.
	 * @return array
	 */
	public function add_row_actions( $actions, $post ) {
		if ( AADI_CPT !== $post->post_type ) {
			return $actions;
		}

		$embeddings = new AADI_Embeddings();
		$status     = $embeddings->get_embedding_status( $post->ID );
		$settings   = new AADI_Settings();

		$badge_labels = array(
			'current' => __( 'Embedding current', 'ask-adam-doc-it' ),
			'stale'   => __( 'Embedding stale', 'ask-adam-doc-it' ),
			'missing' => __( 'No embedding', 'ask-adam-doc-it' ),
		);

		if ( 'disabled' !== $status ) {
			$label                  = isset( $badge_labels[ $status ] ) ? $badge_labels[ $status ] : '';
			$actions['aadi_status'] = sprintf(
				'<span class="aadi-status-badge aadi-status-badge--%s">%s</span>',
				esc_attr( $status ),
				esc_html( $label )
			);
		}

		if (
			$settings->is_ai_enabled()
			&& 'disabled' !== $status
			&& current_user_can( 'edit_post', $post->ID )
		) {
			$regen_url = wp_nonce_url(
				admin_url(
					'admin-post.php?action=aadi_regenerate_embedding&post_id=' . absint( $post->ID )
				),
				'aadi_regenerate_' . $post->ID
			);
			$actions['aadi_regenerate'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $regen_url ),
				esc_html__( 'Regenerate Embedding', 'ask-adam-doc-it' )
			);
		}

		return $actions;
	}

	/**
	 * Handle the row-action regenerate request.
	 *
	 * @return void
	 */
	public function handle_regenerate_embedding() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
		if ( $post_id < 1 ) {
			wp_die( esc_html__( 'Invalid post.', 'ask-adam-doc-it' ) );
		}

		check_admin_referer( 'aadi_regenerate_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'ask-adam-doc-it' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || AADI_CPT !== $post->post_type ) {
			wp_die( esc_html__( 'Invalid post.', 'ask-adam-doc-it' ) );
		}

		$embeddings = new AADI_Embeddings();
		$result     = $embeddings->generate_embedding( $post_id );

		$redirect = add_query_arg(
			array(
				'post_type'  => AADI_CPT,
				'aadi_regen' => $result ? 'success' : 'failed',
				'post_id'    => $post_id,
			),
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Register bulk actions on the CPT list table.
	 *
	 * @param array $actions Existing bulk actions.
	 * @return array
	 */
	public function add_bulk_actions( $actions ) {
		$actions['aadi_regen_missing'] = __( 'Regenerate missing/stale embeddings', 'ask-adam-doc-it' );
		$actions['aadi_regen_all']     = __( 'Force regenerate ALL embeddings', 'ask-adam-doc-it' );
		return $actions;
	}

	/**
	 * Handle the embedding bulk regenerate actions.
	 *
	 * WP core verifies the list-table bulk-action nonce before invoking
	 * this filter (`_wpnonce` on the form). We just need a capability check.
	 *
	 * @param string $redirect_url Redirect target.
	 * @param string $action       Action key.
	 * @param array  $post_ids     Selected post IDs.
	 * @return string
	 */
	public function handle_bulk_action( $redirect_url, $action, $post_ids ) {
		if ( ! in_array( $action, array( 'aadi_regen_missing', 'aadi_regen_all' ), true ) ) {
			return $redirect_url;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect_url;
		}

		$embeddings = new AADI_Embeddings();
		$processed  = 0;
		$skipped    = 0;
		$failed     = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id < 1 ) {
				continue;
			}
			if ( AADI_CPT !== get_post_type( $post_id ) ) {
				continue;
			}

			if ( 'aadi_regen_missing' === $action ) {
				$status = $embeddings->get_embedding_status( $post_id );
				if ( 'current' === $status ) {
					$skipped++;
					continue;
				}
			}

			$result = $embeddings->generate_embedding( $post_id );
			if ( $result ) {
				$processed++;
			} else {
				$failed++;
			}
		}

		return add_query_arg(
			array(
				'aadi_bulk_regen'      => 'done',
				'aadi_regen_processed' => $processed,
				'aadi_regen_skipped'   => $skipped,
				'aadi_regen_failed'    => $failed,
			),
			$redirect_url
		);
	}

}
