<?php
/**
 * Admin-side controller.
 *
 * @package PaperTrail_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PTAI_Admin
 *
 * Handles admin scripts/styles, meta boxes, list-table columns,
 * the settings submenu, and plugin action links.
 */
class PTAI_Admin {

	/**
	 * Page slug for the settings screen (sits under the CPT menu).
	 *
	 * @var string
	 */
	const SETTINGS_PAGE_SLUG = 'ptai-settings';

	/**
	 * Constructor. Intentionally side-effect-free — all hooks live in
	 * PTAI_Loader::define_admin_hooks().
	 */
	public function __construct() {}

	/**
	 * Register the settings submenu under the Documents CPT.
	 *
	 * @return void
	 */
	public function register_settings_page() {
		add_submenu_page(
			'edit.php?post_type=' . PTAI_CPT,
			__( 'PaperTrail AI Settings', 'papertrail-ai' ),
			__( 'Settings', 'papertrail-ai' ),
			'manage_options',
			self::SETTINGS_PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Determine whether the current screen is a PaperTrail AI screen.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private function is_plugin_screen( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen instanceof WP_Screen ) {
			if ( PTAI_CPT === $screen->post_type ) {
				return true;
			}
			if ( PTAI_TAXONOMY === $screen->taxonomy ) {
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
			'ptai-admin-global',
			PTAI_PLUGIN_URL . 'admin/css/admin-global.css',
			array(),
			PTAI_VERSION
		);

		// Full admin styles only on plugin screens.
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'ptai-admin',
			PTAI_PLUGIN_URL . 'admin/css/admin.css',
			array( 'ptai-admin-global' ),
			PTAI_VERSION
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
			'ptai-admin',
			PTAI_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			PTAI_VERSION,
			true
		);

		wp_localize_script(
			'ptai-admin',
			'ptaiAdmin',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ptai_admin_nonce' ),
				'strings'  => array(
					'select_file'       => __( 'Select or upload a file', 'papertrail-ai' ),
					'use_this_file'     => __( 'Use this file', 'papertrail-ai' ),
					'replace_file'      => __( 'Replace file', 'papertrail-ai' ),
					'remove_file'       => __( 'Remove file', 'papertrail-ai' ),
					'no_file_attached'  => __( 'No file attached.', 'papertrail-ai' ),
					'confirm_remove'    => __( 'Remove the attached file? This will not delete it from the media library.', 'papertrail-ai' ),
				),
			)
		);
	}

	/**
	 * Register meta boxes for the ptai_file CPT.
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'ptai_file_details',
			__( 'File Details', 'papertrail-ai' ),
			array( $this, 'render_file_meta_box' ),
			PTAI_CPT,
			'side',
			'high'
		);

		add_meta_box(
			'ptai_doc_summary_box',
			__( 'AI Search Summary', 'papertrail-ai' ),
			array( $this, 'render_doc_summary_meta_box' ),
			PTAI_CPT,
			'normal',
			'high'
		);

		add_meta_box(
			'ptai_upgrade_sidebar',
			__( 'Ask Adam Pro', 'papertrail-ai' ),
			array( $this, 'render_upgrade_meta_box' ),
			PTAI_CPT,
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
		$summary  = (string) get_post_meta( $post->ID, '_ptai_doc_summary', true );
		$settings = new PTAI_Settings();
		$ai_on    = $settings->is_ai_enabled();

		wp_nonce_field( 'ptai_save_doc_summary', 'ptai_doc_summary_nonce' );
		?>
		<p class="ptai-meta-description">
			<?php esc_html_e( 'Used by AI search to understand this document. Write 1-3 sentences describing the content, date, and topic. The more specific, the better the search results.', 'papertrail-ai' ); ?>
		</p>
		<div class="ptai-doc-summary-wrap">
			<textarea
				id="ptai_doc_summary"
				name="_ptai_doc_summary"
				class="large-text"
				rows="4"
				maxlength="500"
			><?php echo esc_textarea( $summary ); ?></textarea>
			<p class="ptai-char-counter">
				<span class="ptai-char-count">0</span> / 500
				<?php esc_html_e( 'characters', 'papertrail-ai' ); ?>
			</p>
		</div>
		<?php if ( ! $ai_on ) : ?>
			<p class="ptai-ai-disabled-notice">
				<?php esc_html_e( 'Add your OpenAI API key in Settings to enable AI search.', 'papertrail-ai' ); ?>
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
		wp_nonce_field( 'ptai_save_file_meta', 'ptai_file_meta_nonce' );

		$file_id         = (int) get_post_meta( $post->ID, '_ptai_file_id', true );
		$file_type       = (string) get_post_meta( $post->ID, '_ptai_file_type', true );
		$file_size       = (int) get_post_meta( $post->ID, '_ptai_file_size', true );
		$file_ext        = (string) get_post_meta( $post->ID, '_ptai_file_ext', true );
		$download_count  = (int) get_post_meta( $post->ID, '_ptai_download_count', true );
		$last_downloaded = (string) get_post_meta( $post->ID, '_ptai_last_downloaded', true );

		$attachment_url = $file_id ? wp_get_attachment_url( $file_id ) : '';
		$file_name      = $file_id ? get_the_title( $file_id ) : '';

		echo '<div class="ptai-file-meta">';
		printf(
			'<input type="hidden" id="ptai_file_id" name="ptai_file_id" value="%d" />',
			(int) $file_id
		);

		echo '<div class="ptai-file-meta__current" id="ptai-file-meta-current">';
		if ( $file_id && $attachment_url ) {
			$icon = $this->icon_for_mime( $file_type );
			echo '<p><span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span> ';
			echo '<strong>' . esc_html( $file_name ) . '</strong></p>';

			echo '<ul class="ptai-meta-list">';
			if ( $file_ext ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %s: file extension. */
						__( 'Extension: %s', 'papertrail-ai' ),
						strtoupper( $file_ext )
					)
				) . '</li>';
			}
			if ( $file_type ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %s: MIME type. */
						__( 'Type: %s', 'papertrail-ai' ),
						$file_type
					)
				) . '</li>';
			}
			if ( $file_size ) {
				echo '<li>' . esc_html(
					sprintf(
						/* translators: %s: human-readable file size. */
						__( 'Size: %s', 'papertrail-ai' ),
						size_format( $file_size )
					)
				) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p><em>' . esc_html__( 'No file attached.', 'papertrail-ai' ) . '</em></p>';
		}
		echo '</div>';

		printf(
			'<p><button type="button" class="button" id="ptai-attach-file">%s</button> ',
			esc_html( $file_id ? __( 'Replace File', 'papertrail-ai' ) : __( 'Attach File', 'papertrail-ai' ) )
		);
		if ( $file_id ) {
			printf(
				'<button type="button" class="button-link" id="ptai-remove-file">%s</button>',
				esc_html__( 'Remove', 'papertrail-ai' )
			);
		}
		echo '</p>';

		echo '<hr />';
		echo '<p><strong>' . esc_html__( 'Download statistics', 'papertrail-ai' ) . '</strong></p>';
		echo '<ul class="ptai-meta-list ptai-meta-list--flush">';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: number of downloads. */
				_n( '%d download', '%d downloads', $download_count, 'papertrail-ai' ),
				$download_count
			)
		) . '</li>';
		if ( '' !== $last_downloaded ) {
			$timestamp = strtotime( $last_downloaded );
			$display   = $timestamp ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : $last_downloaded;
			echo '<li>' . esc_html(
				sprintf(
					/* translators: %s: formatted date/time. */
					__( 'Last download: %s', 'papertrail-ai' ),
					$display
				)
			) . '</li>';
		} else {
			echo '<li>' . esc_html__( 'Never downloaded.', 'papertrail-ai' ) . '</li>';
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
		if ( class_exists( 'PTAI_Pro' ) ) {
			$pro = new PTAI_Pro();
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

		if ( ! isset( $_POST['ptai_file_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['ptai_file_meta_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'ptai_save_file_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$file_id = isset( $_POST['ptai_file_id'] ) ? absint( wp_unslash( $_POST['ptai_file_id'] ) ) : 0;

		if ( 0 === $file_id ) {
			delete_post_meta( $post_id, '_ptai_file_id' );
			delete_post_meta( $post_id, '_ptai_file_type' );
			delete_post_meta( $post_id, '_ptai_file_size' );
			delete_post_meta( $post_id, '_ptai_file_ext' );
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

		update_post_meta( $post_id, '_ptai_file_id', $file_id );
		update_post_meta( $post_id, '_ptai_file_type', sanitize_text_field( $mime ) );
		update_post_meta( $post_id, '_ptai_file_size', $size );
		update_post_meta( $post_id, '_ptai_file_ext', sanitize_text_field( $ext ) );
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

		$new_columns['title']           = isset( $columns['title'] ) ? $columns['title'] : __( 'Title', 'papertrail-ai' );
		$new_columns['file_type']       = __( 'File Type', 'papertrail-ai' );
		$new_columns['file_size']       = __( 'File Size', 'papertrail-ai' );
		$new_columns['download_count']  = __( 'Downloads', 'papertrail-ai' );
		$new_columns['last_downloaded'] = __( 'Last Downloaded', 'papertrail-ai' );

		// Preserve the taxonomy column if WP added it.
		$tax_col = 'taxonomy-' . PTAI_TAXONOMY;
		if ( isset( $columns[ $tax_col ] ) ) {
			$new_columns[ $tax_col ] = $columns[ $tax_col ];
		}

		$new_columns['date'] = isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'papertrail-ai' );

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
				$mime = (string) get_post_meta( $post_id, '_ptai_file_type', true );
				$ext  = (string) get_post_meta( $post_id, '_ptai_file_ext', true );
				if ( '' === $mime && '' === $ext ) {
					echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'No file', 'papertrail-ai' ) . '</span>';
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
				$size = (int) get_post_meta( $post_id, '_ptai_file_size', true );
				echo $size > 0 ? esc_html( size_format( $size ) ) : '<span aria-hidden="true">—</span>';
				break;

			case 'download_count':
				$count = (int) get_post_meta( $post_id, '_ptai_download_count', true );
				echo esc_html( number_format_i18n( $count ) );
				break;

			case 'last_downloaded':
				$last = (string) get_post_meta( $post_id, '_ptai_last_downloaded', true );
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
		if ( PTAI_CPT !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'download_count' === $orderby ) {
			$query->set( 'meta_key', '_ptai_download_count' );
			$query->set( 'orderby', 'meta_value_num' );
		} elseif ( 'last_downloaded' === $orderby ) {
			$query->set( 'meta_key', '_ptai_last_downloaded' );
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
		$settings_url = admin_url( 'edit.php?post_type=' . PTAI_CPT . '&page=' . self::SETTINGS_PAGE_SLUG );

		$prepend = array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'papertrail-ai' )
			),
			'upgrade'  => sprintf(
				'<a href="%1$s" class="ptai-action-link-upgrade" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( 'https://askadamit.com/purchase' ),
				esc_html__( 'Upgrade to Pro', 'papertrail-ai' )
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
		<div class="wrap ptai-settings-wrap ptai-no-js">

			<div class="ptai-admin-hero">
				<div class="ptai-admin-hero__inner">
					<div class="ptai-admin-hero__icon" aria-hidden="true">
						<span class="dashicons dashicons-clipboard"></span>
					</div>
					<div class="ptai-admin-hero__text">
						<h1 class="ptai-admin-hero__title">
							<?php esc_html_e( 'PaperTrail AI', 'papertrail-ai' ); ?>
						</h1>
						<p class="ptai-admin-hero__subtitle">
							<?php esc_html_e(
								'Smart Document Library — Part of the Ask Adam Suite',
								'papertrail-ai'
							); ?>
						</p>
					</div>
					<div class="ptai-admin-hero__badge">
						<span class="ptai-version-badge">
							v<?php echo esc_html( PTAI_VERSION ); ?>
						</span>
					</div>
				</div>
			</div>

			<?php settings_errors(); ?>

			<nav class="ptai-tab-nav" role="tablist"
				aria-label="<?php esc_attr_e( 'Settings sections', 'papertrail-ai' ); ?>">
				<button class="ptai-tab-btn ptai-tab-btn--active"
						id="tab-btn-ai"
						role="tab"
						aria-selected="true"
						aria-controls="ptai-tab-ai"
						data-tab="ptai-tab-ai">
					<span class="dashicons dashicons-superhero-alt" aria-hidden="true"></span>
					<?php esc_html_e( 'AI Configuration', 'papertrail-ai' ); ?>
				</button>
				<button class="ptai-tab-btn"
						id="tab-btn-uploads"
						role="tab"
						aria-selected="false"
						aria-controls="ptai-tab-uploads"
						data-tab="ptai-tab-uploads">
					<span class="dashicons dashicons-upload" aria-hidden="true"></span>
					<?php esc_html_e( 'Upload Settings', 'papertrail-ai' ); ?>
				</button>
				<button class="ptai-tab-btn"
						id="tab-btn-access"
						role="tab"
						aria-selected="false"
						aria-controls="ptai-tab-access"
						data-tab="ptai-tab-access">
					<span class="dashicons dashicons-groups" aria-hidden="true"></span>
					<?php esc_html_e( 'Access Control', 'papertrail-ai' ); ?>
				</button>
				<button class="ptai-tab-btn"
						id="tab-btn-advanced"
						role="tab"
						aria-selected="false"
						aria-controls="ptai-tab-advanced"
						data-tab="ptai-tab-advanced">
					<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
					<?php esc_html_e( 'Advanced', 'papertrail-ai' ); ?>
				</button>
				<button class="ptai-tab-btn"
						id="tab-btn-help"
						role="tab"
						aria-selected="false"
						aria-controls="ptai-tab-help"
						data-tab="ptai-tab-help">
					<span class="dashicons dashicons-editor-help" aria-hidden="true"></span>
					<?php esc_html_e( 'How to Use', 'papertrail-ai' ); ?>
				</button>
			</nav>

			<div class="ptai-settings-layout">
				<div class="ptai-settings-main">

					<form method="post" action="options.php">
						<?php
						settings_fields( PTAI_Settings::SETTINGS_GROUP );

						global $wp_settings_sections;
						$page_sections = isset( $wp_settings_sections[ PTAI_Settings::PAGE_SLUG ] )
							? $wp_settings_sections[ PTAI_Settings::PAGE_SLUG ]
							: array();

						$sections = array(
							'ptai-tab-ai'       => array(
								'section_id' => 'ptai_section_ai',
								'icon'       => 'superhero-alt',
								'title'      => __( 'AI Configuration', 'papertrail-ai' ),
							),
							'ptai-tab-uploads'  => array(
								'section_id' => 'ptai_section_uploads',
								'icon'       => 'upload',
								'title'      => __( 'Upload Settings', 'papertrail-ai' ),
							),
							'ptai-tab-access'   => array(
								'section_id' => 'ptai_section_access',
								'icon'       => 'groups',
								'title'      => __( 'Access Control', 'papertrail-ai' ),
							),
							'ptai-tab-advanced' => array(
								'section_id' => 'ptai_section_advanced',
								'icon'       => 'admin-tools',
								'title'      => __( 'Advanced', 'papertrail-ai' ),
							),
						);

						$first = true;
						foreach ( $sections as $tab_id => $info ) {
							$section_id = $info['section_id'];
							$btn_id     = str_replace( 'ptai-tab-', 'tab-btn-', $tab_id );
							$active     = $first ? ' ptai-tab-panel--active' : '';
							?>
							<div id="<?php echo esc_attr( $tab_id ); ?>"
								class="ptai-tab-panel<?php echo esc_attr( $active ); ?>"
								role="tabpanel"
								aria-labelledby="<?php echo esc_attr( $btn_id ); ?>">
								<div class="ptai-panel-card">
									<h2 class="ptai-panel-heading">
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
										echo '<div class="ptai-panel-description">';
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
												PTAI_Settings::PAGE_SLUG,
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
							echo '<div class="ptai-panel-card">';
							if ( ! empty( $section['title'] ) ) {
								echo '<h2 class="ptai-panel-heading">'
									. esc_html( $section['title'] )
									. '</h2>';
							}
							if ( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
								echo '<div class="ptai-panel-description">';
								call_user_func( $section['callback'], $section );
								echo '</div>';
							}
							echo '<table class="form-table" role="presentation"><tbody>';
							do_settings_fields( PTAI_Settings::PAGE_SLUG, $section['id'] );
							echo '</tbody></table>';
							echo '</div>';
						}
						?>

						<?php submit_button(); ?>
					</form>

					<div id="ptai-tab-help"
						class="ptai-tab-panel"
						role="tabpanel"
						aria-labelledby="tab-btn-help">
						<?php $this->render_help_tab(); ?>
					</div>

				</div>
				<div class="ptai-settings-sidebar">
					<?php
					if ( class_exists( 'PTAI_Pro' ) ) {
						$pro = new PTAI_Pro();
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
		<div class="ptai-help">

			<!-- Getting Started -->
			<div class="ptai-help__section">
				<h2 class="ptai-help__heading">
					<span class="dashicons dashicons-rocket"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Getting Started', 'papertrail-ai' ); ?>
				</h2>
				<ol class="ptai-help__steps">
					<li>
						<strong><?php esc_html_e( 'Add a document', 'papertrail-ai' ); ?></strong>
						<p><?php esc_html_e(
							'Go to PaperTrail → Add New. Give your document a title, attach a file using the File Details meta box, and assign a category.',
							'papertrail-ai'
						); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Write an AI Search Summary', 'papertrail-ai' ); ?></strong>
						<p><?php esc_html_e(
							'Fill in the AI Search Summary field with 1–3 sentences describing the document content, date, and topic. This is what AI search uses to understand your document.',
							'papertrail-ai'
						); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Enable AI search (optional)', 'papertrail-ai' ); ?></strong>
						<p><?php
						printf(
							wp_kses(
								/* translators: %s: settings tab link */
								__( 'Add your OpenAI API key in the <a href="%s">AI Configuration tab</a>. The plugin works fully without it using keyword search.', 'papertrail-ai' ),
								array( 'a' => array( 'href' => array() ) )
							),
							esc_url( admin_url( 'edit.php?post_type=' . PTAI_CPT . '&page=' . self::SETTINGS_PAGE_SLUG ) )
						);
						?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Embed your library', 'papertrail-ai' ); ?></strong>
						<p><?php esc_html_e(
							'Use the shortcode or Gutenberg block on any page or post to display your document library.',
							'papertrail-ai'
						); ?></p>
					</li>
				</ol>
			</div>

			<!-- Shortcode Reference -->
			<div class="ptai-help__section">
				<h2 class="ptai-help__heading">
					<span class="dashicons dashicons-shortcode"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Shortcode Reference', 'papertrail-ai' ); ?>
				</h2>
				<p><?php esc_html_e(
					'Use the [papertrail] shortcode on any page or post.',
					'papertrail-ai'
				); ?></p>

				<table class="ptai-help__table widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Attribute', 'papertrail-ai' ); ?></th>
							<th><?php esc_html_e( 'Default', 'papertrail-ai' ); ?></th>
							<th><?php esc_html_e( 'Description', 'papertrail-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>category</code></td>
							<td><code>""</code></td>
							<td><?php esc_html_e( 'Category slug or term ID to filter results.', 'papertrail-ai' ); ?></td>
						</tr>
						<tr>
							<td><code>per_page</code></td>
							<td><code>10</code></td>
							<td><?php esc_html_e( 'Number of documents per page (1–50).', 'papertrail-ai' ); ?></td>
						</tr>
						<tr>
							<td><code>columns</code></td>
							<td><code>1</code></td>
							<td><?php esc_html_e( 'Display in 1 or 2 columns.', 'papertrail-ai' ); ?></td>
						</tr>
						<tr>
							<td><code>show_search</code></td>
							<td><code>true</code></td>
							<td><?php esc_html_e( 'Show or hide the search bar.', 'papertrail-ai' ); ?></td>
						</tr>
						<tr>
							<td><code>mode</code></td>
							<td><code>auto</code></td>
							<td><?php esc_html_e( 'Search mode: auto, ai, or core. Auto uses AI when configured, falls back to keyword search.', 'papertrail-ai' ); ?></td>
						</tr>
						<tr>
							<td><code>orderby</code></td>
							<td><code>date</code></td>
							<td><?php esc_html_e( 'Sort by: date, title, or downloads.', 'papertrail-ai' ); ?></td>
						</tr>
						<tr>
							<td><code>order</code></td>
							<td><code>DESC</code></td>
							<td><?php esc_html_e( 'Sort direction: DESC or ASC.', 'papertrail-ai' ); ?></td>
						</tr>
					</tbody>
				</table>

				<!-- Code examples -->
				<div class="ptai-help__examples">
					<p class="ptai-help__example-label">
						<?php esc_html_e( 'Examples:', 'papertrail-ai' ); ?>
					</p>
					<div class="ptai-help__code-block">
						<code>[papertrail]</code>
						<span class="ptai-help__code-note">
							<?php esc_html_e( '— Show all documents', 'papertrail-ai' ); ?>
						</span>
					</div>
					<div class="ptai-help__code-block">
						<code>[papertrail category="newsletters" per_page="20" columns="2"]</code>
						<span class="ptai-help__code-note">
							<?php esc_html_e( '— Newsletters in 2-column grid', 'papertrail-ai' ); ?>
						</span>
					</div>
					<div class="ptai-help__code-block">
						<code>[papertrail mode="core" show_search="false"]</code>
						<span class="ptai-help__code-note">
							<?php esc_html_e( '— Keyword search, no search bar', 'papertrail-ai' ); ?>
						</span>
					</div>
				</div>
			</div>

			<!-- AI Search Explained -->
			<div class="ptai-help__section">
				<h2 class="ptai-help__heading">
					<span class="dashicons dashicons-superhero-alt"
						aria-hidden="true"></span>
					<?php esc_html_e( 'How AI Search Works', 'papertrail-ai' ); ?>
				</h2>
				<p><?php esc_html_e(
					'AI search uses OpenAI embeddings to find documents by meaning rather than exact keywords. A visitor searching for "quarterly financial results" will find documents about "Q3 earnings report" even if those words do not match.',
					'papertrail-ai'
				); ?></p>
				<h3 class="ptai-help__subheading">
					<?php esc_html_e( 'What gets embedded', 'papertrail-ai' ); ?>
				</h3>
				<ul class="ptai-help__list">
					<li><?php esc_html_e( 'Document title', 'papertrail-ai' ); ?></li>
					<li><?php esc_html_e( 'Post excerpt', 'papertrail-ai' ); ?></li>
					<li><?php esc_html_e( 'AI Search Summary field (most important)', 'papertrail-ai' ); ?></li>
					<li><?php esc_html_e( 'Category names', 'papertrail-ai' ); ?></li>
				</ul>
				<h3 class="ptai-help__subheading">
					<?php esc_html_e( 'Embedding status badges', 'papertrail-ai' ); ?>
				</h3>
				<ul class="ptai-help__badge-legend">
					<li>
						<span class="ptai-status-badge ptai-status-badge--current">
							<?php esc_html_e( 'Embedding current', 'papertrail-ai' ); ?>
						</span>
						<?php esc_html_e( '— Document is indexed and ready.', 'papertrail-ai' ); ?>
					</li>
					<li>
						<span class="ptai-status-badge ptai-status-badge--stale">
							<?php esc_html_e( 'Embedding stale', 'papertrail-ai' ); ?>
						</span>
						<?php esc_html_e( '— Document was edited since last index. Will update automatically on next save.', 'papertrail-ai' ); ?>
					</li>
					<li>
						<span class="ptai-status-badge ptai-status-badge--missing">
							<?php esc_html_e( 'No embedding', 'papertrail-ai' ); ?>
						</span>
						<?php esc_html_e( '— Not yet indexed. Save the document or use Regenerate Embedding.', 'papertrail-ai' ); ?>
					</li>
				</ul>
				<p class="ptai-help__note">
					<span class="dashicons dashicons-info-outline"
						aria-hidden="true"></span>
					<?php esc_html_e(
						'API cost note: PaperTrail AI uses text-embedding-3-small — one of OpenAI\'s most affordable models. Indexing a typical document summary costs a fraction of a cent.',
						'papertrail-ai'
					); ?>
				</p>
			</div>

			<!-- Download Tracking -->
			<div class="ptai-help__section">
				<h2 class="ptai-help__heading">
					<span class="dashicons dashicons-download"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Download Tracking', 'papertrail-ai' ); ?>
				</h2>
				<p><?php esc_html_e(
					'Every file download is routed through a WordPress REST endpoint which increments the download counter before serving the file. Counts are rate-limited to one per document per hour using a hashed token — no IP addresses or personal data are stored.',
					'papertrail-ai'
				); ?></p>
				<p><?php esc_html_e(
					'Download counts appear in the Documents list table and are sortable. Use them to identify your most popular documents and retire unused ones.',
					'papertrail-ai'
				); ?></p>
			</div>

			<!-- Need More -->
			<div class="ptai-help__section ptai-help__section--cta">
				<h2 class="ptai-help__heading">
					<span class="dashicons dashicons-external"
						aria-hidden="true"></span>
					<?php esc_html_e( 'Need More?', 'papertrail-ai' ); ?>
				</h2>
				<p><?php esc_html_e(
					'PaperTrail AI is part of the Ask Adam suite. Ask Adam Pro adds conversational document Q&A, multi-document context retrieval, bulk indexing, analytics, and priority support.',
					'papertrail-ai'
				); ?></p>
				<a href="<?php echo esc_url( 'https://askadamit.com/purchase' ); ?>"
					class="button button-primary ptai-help__cta-btn"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Learn about Ask Adam Pro', 'papertrail-ai' ); ?>
				</a>
				<a href="<?php echo esc_url( 'https://askadamit.com' ); ?>"
					class="button ptai-help__cta-btn"
					target="_blank"
					rel="noopener noreferrer">
					<?php esc_html_e( 'Visit askadamit.com', 'papertrail-ai' ); ?>
				</a>
			</div>

		</div><!-- .ptai-help -->
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
		if ( ! isset( $_POST['ptai_doc_summary_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce(
			sanitize_key( wp_unslash( $_POST['ptai_doc_summary_nonce'] ) ),
			'ptai_save_doc_summary'
		) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$summary = isset( $_POST['_ptai_doc_summary'] )
			? sanitize_textarea_field( wp_unslash( $_POST['_ptai_doc_summary'] ) )
			: '';
		// mb_substr to avoid splitting UTF-8 characters at the 500-char cap.
		$summary = function_exists( 'mb_substr' )
			? mb_substr( $summary, 0, 500, 'UTF-8' )
			: substr( $summary, 0, 500 );

		update_post_meta( $post_id, '_ptai_doc_summary', $summary );
	}

	/**
	 * Render the AI status admin notice on PaperTrail screens.
	 *
	 * @return void
	 */
	public function render_ai_status_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PTAI_CPT !== $screen->post_type ) {
			return;
		}

		$settings     = new PTAI_Settings();
		$settings_url = admin_url( 'edit.php?post_type=' . PTAI_CPT . '&page=' . self::SETTINGS_PAGE_SLUG );

		// Regeneration result feedback (must precede other notices).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$regen      = isset( $_GET['ptai_regen'] ) ? sanitize_key( wp_unslash( $_GET['ptai_regen'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$regen_post = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;

		if ( 'success' === $regen && $regen_post > 0 ) {
			echo '<div class="notice notice-success is-dismissible"><p>'
				. esc_html__( 'Embedding regenerated successfully.', 'papertrail-ai' )
				. '</p></div>';
		} elseif ( 'failed' === $regen && $regen_post > 0 ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: settings page URL */
						__(
							'Embedding regeneration failed. <a href="%s">Check your OpenAI API key in Settings</a>.',
							'papertrail-ai'
						),
						esc_url( $settings_url )
					),
					array( 'a' => array( 'href' => array() ) )
				)
			);
		}

		// State 1 — circuit breaker.
		if ( get_option( 'ptai_openai_auth_failed' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: %s: settings page URL */
							__(
								'PaperTrail AI: Your OpenAI API key was rejected (401). AI search is disabled. <a href="%s">Update your API key</a> to re-enable.',
								'papertrail-ai'
							),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( $settings_url )
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		// State 2 — no key / AI disabled.
		if ( ! $settings->is_ai_enabled() ) {
			?>
			<div class="notice notice-info">
				<p>
					<?php
					printf(
						wp_kses(
							/* translators: %s: settings page URL */
							__(
								'PaperTrail AI is running in basic search mode. <a href="%s">Add your OpenAI API key</a> to enable AI-powered semantic search.',
								'papertrail-ai'
							),
							array( 'a' => array( 'href' => array() ) )
						),
						esc_url( $settings_url )
					);
					?>
				</p>
			</div>
			<?php
			return;
		}

		// State 3 — AI active.
		$dismissed_key = 'ptai_ai_notice_dismissed_' . get_current_user_id();
		if ( get_user_meta( get_current_user_id(), $dismissed_key, true ) ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible ptai-ai-active-notice"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'ptai_dismiss_notice' ) ); ?>">
			<p><?php esc_html_e( 'PaperTrail AI: AI search is active.', 'papertrail-ai' ); ?></p>
		</div>
		<?php
	}

	/**
	 * AJAX: persist per-user dismissal of the "AI active" notice.
	 *
	 * @return void
	 */
	public function handle_dismiss_ai_notice() {
		check_ajax_referer( 'ptai_dismiss_notice', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$key = 'ptai_ai_notice_dismissed_' . get_current_user_id();
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
		if ( PTAI_CPT !== $post->post_type ) {
			return $actions;
		}

		$embeddings = new PTAI_Embeddings();
		$status     = $embeddings->get_embedding_status( $post->ID );
		$settings   = new PTAI_Settings();

		$badge_labels = array(
			'current' => __( 'Embedding current', 'papertrail-ai' ),
			'stale'   => __( 'Embedding stale', 'papertrail-ai' ),
			'missing' => __( 'No embedding', 'papertrail-ai' ),
		);

		if ( 'disabled' !== $status ) {
			$label                  = isset( $badge_labels[ $status ] ) ? $badge_labels[ $status ] : '';
			$actions['ptai_status'] = sprintf(
				'<span class="ptai-status-badge ptai-status-badge--%s">%s</span>',
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
					'admin-post.php?action=ptai_regenerate_embedding&post_id=' . absint( $post->ID )
				),
				'ptai_regenerate_' . $post->ID
			);
			$actions['ptai_regenerate'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $regen_url ),
				esc_html__( 'Regenerate Embedding', 'papertrail-ai' )
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
			wp_die( esc_html__( 'Invalid post.', 'papertrail-ai' ) );
		}

		check_admin_referer( 'ptai_regenerate_' . $post_id );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'papertrail-ai' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post || PTAI_CPT !== $post->post_type ) {
			wp_die( esc_html__( 'Invalid post.', 'papertrail-ai' ) );
		}

		$embeddings = new PTAI_Embeddings();
		$result     = $embeddings->generate_embedding( $post_id );

		$redirect = add_query_arg(
			array(
				'post_type'  => PTAI_CPT,
				'ptai_regen' => $result ? 'success' : 'failed',
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
		$actions['ptai_regen_missing'] = __( 'Regenerate missing/stale embeddings', 'papertrail-ai' );
		$actions['ptai_regen_all']     = __( 'Force regenerate ALL embeddings', 'papertrail-ai' );
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
		if ( ! in_array( $action, array( 'ptai_regen_missing', 'ptai_regen_all' ), true ) ) {
			return $redirect_url;
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $redirect_url;
		}

		$embeddings = new PTAI_Embeddings();
		$processed  = 0;
		$skipped    = 0;
		$failed     = 0;

		foreach ( (array) $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id < 1 ) {
				continue;
			}
			if ( PTAI_CPT !== get_post_type( $post_id ) ) {
				continue;
			}

			if ( 'ptai_regen_missing' === $action ) {
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
				'ptai_bulk_regen'      => 'done',
				'ptai_regen_processed' => $processed,
				'ptai_regen_skipped'   => $skipped,
				'ptai_regen_failed'    => $failed,
			),
			$redirect_url
		);
	}

	/**
	 * Render notice if the library exceeds the in-PHP scoring limit.
	 *
	 * @return void
	 */
	public function render_size_limit_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || PTAI_CPT !== $screen->post_type ) {
			return;
		}

		// Bulk regenerate summary takes precedence on the list table.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['ptai_bulk_regen'] ) && 'done' === $_GET['ptai_bulk_regen'] ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$processed = isset( $_GET['ptai_regen_processed'] ) ? absint( wp_unslash( $_GET['ptai_regen_processed'] ) ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$skipped   = isset( $_GET['ptai_regen_skipped'] ) ? absint( wp_unslash( $_GET['ptai_regen_skipped'] ) ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$failed    = isset( $_GET['ptai_regen_failed'] ) ? absint( wp_unslash( $_GET['ptai_regen_failed'] ) ) : 0;

			$message = sprintf(
				/* translators: 1: regenerated count, 2: skipped count */
				__( 'Regenerated %1$d embeddings. Skipped %2$d (already current).', 'papertrail-ai' ),
				$processed,
				$skipped
			);
			if ( $failed > 0 ) {
				$message .= ' ' . sprintf(
					/* translators: %d: failed count */
					__( '%d failed — check your OpenAI API key.', 'papertrail-ai' ),
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

		if ( class_exists( 'PTAI_Search' ) && PTAI_Search::is_over_limit() ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: document count threshold, 2: number of documents scored per AI search. */
						__( 'PaperTrail AI: Your library exceeds %1$d documents. The free version scores only the most recent %2$d during AI search. Upgrade to Pro for full-library vector search.', 'papertrail-ai' ),
						PTAI_Search::MAX_SCORED_POSTS,
						PTAI_Search::MAX_SCORED_POSTS
					)
				)
			);
		}
	}
}
