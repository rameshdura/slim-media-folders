<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Admin {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'plugin_action_links_' . SWMF_PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );
		add_action( 'restrict_manage_posts', [ $this, 'render_folder_filter' ] );
		add_action( 'pre_get_posts', [ $this, 'filter_posts_by_folder' ] );
		add_action( 'admin_post_swmf_save_content_type_settings', [ $this, 'save_content_type_settings' ] );
	}

	/**
	 * Add a direct link to the plugin settings from the Plugins screen.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[]
	 */
	public function add_plugin_action_links( $links ) {
		// The Settings tab is restricted to administrators, so do not expose a
		// link that would only fall back to the overview for other roles.
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$settings_url = admin_url( 'admin.php?page=slim-media-folders&swmf-tab=settings' );

		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'slim-media-folders' )
		);

		array_unshift( $links, $settings_link );
		return $links;
	}

	public function render_folder_filter() {
		$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post';
		if ( ! post_type_exists( $post_type ) || ! in_array( $post_type, array_keys( $this->get_supported_post_types() ), true ) ) {
			return;
		}
		$current = isset( $_GET['swmf_folder_id'] ) ? absint( wp_unslash( $_GET['swmf_folder_id'] ) ) : 0;
		$folders = SWMF_Folder::get_all( [ 'parent_id' => 0, 'object_type' => $post_type ] );
		if ( empty( $folders ) ) {
			return;
		}
		echo '<select name="swmf_folder_id" class="swmf-list-folder-filter"><option value="0">' . esc_html__( 'All folders', 'slim-media-folders' ) . '</option>';
		$this->render_list_folder_options( $folders, $current );
		echo '</select>';
	}

	private function render_list_folder_options( $folders, $current, $depth = 0 ) {
		foreach ( $folders as $folder ) {
			echo '<option value="' . esc_attr( $folder->id ) . '" ' . selected( $current, $folder->id, false ) . '>' . esc_html( str_repeat( '— ', $depth ) . $folder->name ) . '</option>';
			$children = SWMF_Folder::get_all( [ 'parent_id' => $folder->id, 'object_type' => $folder->object_type ] );
			$this->render_list_folder_options( $children, $current, $depth + 1 );
		}
	}

	public function filter_posts_by_folder( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['swmf_folder_id'] ) ) {
			return;
		}
		$folder_id = sanitize_text_field( wp_unslash( $_GET['swmf_folder_id'] ) );
		if ( 'uncategorized' === $folder_id ) {
			$query->set( 'meta_query', [
				'relation' => 'OR',
				[
					'key'     => '_swmf_folder_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_swmf_folder_id',
					'value'   => '0',
					'compare' => '=',
				],
			] );
			return;
		}
		$folder_id = (int) $folder_id;
		if ( $folder_id > 0 ) {
			$query->set( 'meta_query', [ [ 'key' => '_swmf_folder_id', 'value' => $folder_id, 'compare' => '=' ] ] );
		}
	}

	/**
	 * Add folder management where content is managed: the Posts, Pages, and
	 * custom post type list screens. The main plugin page remains an overview.
	 */
	public function render_list_folder_manager() {
		global $typenow;

		$post_type = $typenow ? $typenow : 'post';
		if ( ! in_array( $post_type, array_keys( $this->get_supported_post_types() ), true ) ) {
			return;
		}
		$counts = SWMF_Folder::get_object_counts( $post_type );
		$current_folder = isset( $_GET['swmf_folder_id'] ) ? sanitize_text_field( wp_unslash( $_GET['swmf_folder_id'] ) ) : '';
		?>
		<section class="swmf-folder-manager swmf-list-folder-manager" aria-labelledby="swmf-folder-manager-title" style="display: none;">
			<input type="hidden" id="swmf-object-type" value="<?php echo esc_attr( $post_type ); ?>" />
			<div class="swmf-section-heading">
				<div>
					<h2 id="swmf-folder-manager-title"><span class="dashicons dashicons-category" aria-hidden="true"></span><?php esc_html_e( 'Folders', 'slim-media-folders' ); ?></h2>
				</div>
				<button type="button" class="button button-primary" id="swmf-create-root-folder"><?php esc_html_e( 'New Folder', 'slim-media-folders' ); ?></button>
			</div>
			<div class="swmf-list-folder-search"><input type="search" id="swmf-folder-search" placeholder="<?php esc_attr_e( 'Search folders…', 'slim-media-folders' ); ?>" /></div>
			<div class="swmf-list-folder-shortcuts">
				<button type="button" class="swmf-list-folder-shortcut<?php echo '' === $current_folder ? ' is-active' : ''; ?>" data-folder-id=""><span class="dashicons dashicons-category" aria-hidden="true"></span><span><?php esc_html_e( 'All items', 'slim-media-folders' ); ?></span><b><?php echo esc_html( number_format_i18n( $counts['total'] ) ); ?></b></button>
				<button type="button" class="swmf-list-folder-shortcut<?php echo 'uncategorized' === $current_folder ? ' is-active' : ''; ?>" data-folder-id="uncategorized"><span class="dashicons dashicons-open-folder" aria-hidden="true"></span><span><?php esc_html_e( 'Uncategorized', 'slim-media-folders' ); ?></span><b><?php echo esc_html( number_format_i18n( $counts['uncategorized'] ) ); ?></b></button>
			</div>
			<div id="swmf-bulk-actions" class="swmf-bulk-actions" style="display: none;">
				<label for="swmf-bulk-color"><?php esc_html_e( 'Color', 'slim-media-folders' ); ?></label><input type="color" id="swmf-bulk-color" value="#3498db" />
				<label><input type="checkbox" id="swmf-bulk-lock" /> <?php esc_html_e( 'Lock folders', 'slim-media-folders' ); ?></label>
				<button type="button" class="button" id="swmf-bulk-apply"><?php esc_html_e( 'Apply', 'slim-media-folders' ); ?></button>
				<button type="button" class="button-link-delete" id="swmf-bulk-delete"><?php esc_html_e( 'Delete selected', 'slim-media-folders' ); ?></button>
				<button type="button" class="button-link" id="swmf-cancel-selection"><?php esc_html_e( 'Cancel', 'slim-media-folders' ); ?></button>
			</div>
			<div class="swmf-tree-container"><div id="swmf-loading" class="swmf-loading"><span class="spinner is-active"></span><?php esc_html_e( 'Loading…', 'slim-media-folders' ); ?></div><div id="swmf-tree" class="swmf-tree" style="display: none;"></div><div id="swmf-empty" class="swmf-empty" style="display: none;"><p><?php esc_html_e( 'No folders for this content type yet.', 'slim-media-folders' ); ?></p><button type="button" class="button button-primary" id="swmf-create-first-folder"><?php esc_html_e( 'Create your first folder', 'slim-media-folders' ); ?></button></div></div>
		</section>
		<?php $this->render_folder_modal(); ?>
		<?php
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'Media Folders', 'slim-media-folders' ),
			get_option( 'swmf_brand' ) ?: __( 'Slim Media Folders', 'slim-media-folders' ),
			'read',
			'slim-media-folders',
			[ $this, 'render_admin_page' ],
			'dashicons-open-folder',
			25
		);
	}

	public function save_content_type_settings() {
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['swmf_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['swmf_settings_nonce'] ) ), 'swmf_save_content_type_settings' ) ) {
			wp_die( esc_html__( 'You are not allowed to update these settings.', 'slim-media-folders' ) );
		}
		$requested = isset( $_POST['swmf_enabled_post_types'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['swmf_enabled_post_types'] ) ) : [];
		$available = array_diff( array_keys( $this->get_available_post_types() ), [ 'attachment' ] );
		update_option( 'swmf_enabled_post_types', array_values( array_intersect( $requested, $available ) ) );
		wp_safe_redirect( add_query_arg( [ 'swmf-settings-updated' => '1', 'swmf-tab' => 'folders' ], admin_url( 'admin.php?page=slim-media-folders' ) ) );
		exit;
	}

	public function enqueue_scripts( $hook ) {
		if ( 'toplevel_page_slim-media-folders' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'swmf-admin', SWMF_PLUGIN_URL . 'assets/css/admin.css', [], filemtime( SWMF_PLUGIN_DIR . 'assets/css/admin.css' ) );
		wp_enqueue_script( 'swmf-admin', SWMF_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery', 'wp-util' ], filemtime( SWMF_PLUGIN_DIR . 'assets/js/admin.js' ), true );

		wp_localize_script( 'swmf-admin', 'swmf_data', [
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'swmf_nonce' ),
			'strings'       => [
				'create_folder'      => __( 'Create Folder', 'slim-media-folders' ),
				'edit_folder'        => __( 'Edit Folder', 'slim-media-folders' ),
				'folder_id'          => __( 'Folder ID', 'slim-media-folders' ),
				'delete_folder'      => __( 'Delete Folder', 'slim-media-folders' ),
				'bulk_actions'       => __( 'Bulk Actions', 'slim-media-folders' ),
				'apply'              => __( 'Apply', 'slim-media-folders' ),
				'color'              => __( 'Color', 'slim-media-folders' ),
				'lock'               => __( 'Lock', 'slim-media-folders' ),
				'unlock'             => __( 'Unlock', 'slim-media-folders' ),
				'delete'             => __( 'Delete', 'slim-media-folders' ),
				'confirm_delete'     => __( 'Are you sure you want to delete this folder and all its subfolders?', 'slim-media-folders' ),
				'confirm_bulk_delete'=> __( 'Are you sure you want to delete the selected folders and all their subfolders?', 'slim-media-folders' ),
				'folder_name'        => __( 'Folder Name', 'slim-media-folders' ),
				'parent_folder'      => __( 'Parent Folder', 'slim-media-folders' ),
				'root'               => __( 'Root', 'slim-media-folders' ),
				'select_folders'     => __( 'Select Folders', 'slim-media-folders' ),
				'no_folders'         => __( 'No folders yet. Create your first folder!', 'slim-media-folders' ),
				'loading'            => __( 'Loading...', 'slim-media-folders' ),
				'error'              => __( 'An error occurred', 'slim-media-folders' ),
				'creating_zip'       => __( 'Creating ZIP…', 'slim-media-folders' ),
				'create_zip'         => __( 'Create ZIP', 'slim-media-folders' ),
				'delete_zip'         => __( 'Delete ZIP', 'slim-media-folders' ),
				'download_zip'       => __( 'Download ZIP', 'slim-media-folders' ),
				'confirm_delete_zip' => __( 'Delete the current ZIP file?', 'slim-media-folders' ),
				'select_zip_folder'  => __( 'Choose a folder first.', 'slim-media-folders' ),
				'no_zip'             => __( 'No ZIP has been created yet.', 'slim-media-folders' ),
				'files_ready'        => __( 'files ready to download.', 'slim-media-folders' ),
				'zip_in_progress'    => __( 'Preparing your ZIP. This may take a moment…', 'slim-media-folders' ),
				'zip_ready'          => __( 'Your ZIP is ready to download.', 'slim-media-folders' ),
				'deleting_zip'       => __( 'Removing the current ZIP…', 'slim-media-folders' ),
				'zip_deleted'        => __( 'The ZIP has been deleted.', 'slim-media-folders' ),
			],
		] );
	}

	public function render_admin_page() {
		$has_pro = swmf_has_pro();
		$show_pro = current_user_can( 'manage_options' ) || '1' !== get_option( 'swmf_hide_pro_non_admin' );
		$upgrade_url = swmf_product_config( 'upgrade_url', 'https://slim-media-folders-site.vercel.app/#pro' );
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'slim-media-folders' ) );
		}
		$tabs = [ 'overview' => __( 'Overview', 'slim-media-folders' ) ];
		if ( swmf_has_pro() && ( swmf_can( 'view' ) || current_user_can( 'manage_options' ) ) ) { $tabs['folders'] = __( 'Folders', 'slim-media-folders' ); }
		$tabs = apply_filters( 'swmf_admin_tabs', $tabs );
		if ( swmf_has_pro() && current_user_can( 'manage_options' ) ) { $tabs['settings'] = __( 'Settings', 'slim-media-folders' ); }
		$requested_tab = isset( $_GET['swmf-tab'] ) ? sanitize_key( wp_unslash( $_GET['swmf-tab'] ) ) : 'overview';
		$active_tab = isset( $tabs[ $requested_tab ] ) ? $requested_tab : 'overview';
		$counts      = SWMF_Folder::get_counts();
		$tree        = SWMF_Folder::get_tree_for_type( 0, $counts, 'attachment' );
		?>
		<div class="wrap swmf-wrap" data-swmf-theme="system">
			<div class="swmf-page-header">
				<div>
					<span class="swmf-eyebrow"><?php esc_html_e( 'Slim Media Folders', 'slim-media-folders' ); ?></span>
					<h1><?php esc_html_e( 'Media Library made easy with folders.', 'slim-media-folders' ); ?></h1>
				</div>
			</div>
			<nav class="nav-tab-wrapper swmf-tabs" aria-label="<?php esc_attr_e( 'Plugin sections', 'slim-media-folders' ); ?>">
				<?php foreach ( $tabs as $tab => $label ) : ?><a class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'page' => 'slim-media-folders', 'swmf-tab' => $tab ], admin_url( 'admin.php' ) ) ); ?>" <?php if ( $active_tab === $tab ) : ?>aria-current="page"<?php endif; ?>><?php echo esc_html( $label ); ?></a><?php endforeach; ?>
			</nav>
			<div class="swmf-page-grid">
				<main class="swmf-main-content">
				<?php do_action( 'swmf_admin_tab_content', $active_tab, $tree ); ?>
				<?php if ( 'folders' === $active_tab && current_user_can( 'manage_options' ) ) : ?>
				<div class="swmf-folder-settings-cards">
					<?php if ( isset( $_GET['swmf-settings-updated'] ) ) : ?><div class="notice notice-success inline"><p><?php esc_html_e( 'Folder settings saved.', 'slim-media-folders' ); ?></p></div><?php endif; ?>
					<section class="swmf-content-type-settings" aria-labelledby="swmf-media-folder-settings-title">
						<h2 id="swmf-media-folder-settings-title"><?php esc_html_e( 'Media Library folder', 'slim-media-folders' ); ?></h2>
						<p><?php esc_html_e( 'Organize files in the Media Library with folders.', 'slim-media-folders' ); ?></p>
						<div class="swmf-content-type-options">
							<div class="swmf-content-type-option is-fixed"><input type="checkbox" checked disabled aria-label="<?php esc_attr_e( 'Media Library', 'slim-media-folders' ); ?>" /> <span><?php esc_html_e( 'Media Library', 'slim-media-folders' ); ?></span><small><?php esc_html_e( 'Always enabled', 'slim-media-folders' ); ?></small></div>
						</div>
					</section>
					<?php if ( swmf_has_pro() ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="swmf_save_content_type_settings" />
						<?php wp_nonce_field( 'swmf_save_content_type_settings', 'swmf_settings_nonce' ); ?>
					<section class="swmf-content-type-settings" aria-labelledby="swmf-content-type-settings-title">
						<h2 id="swmf-content-type-settings-title"><?php esc_html_e( 'Posts and pages folders', 'slim-media-folders' ); ?></h2>
						<p><?php esc_html_e( 'Choose which content types should have folders.', 'slim-media-folders' ); ?></p>
						<div class="swmf-content-type-options">
							<?php foreach ( array_intersect_key( $this->get_available_post_types(), array_flip( [ 'post', 'page' ] ) ) as $post_type => $label ) : ?>
								<label class="swmf-content-type-option"><input type="checkbox" name="swmf_enabled_post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( swmf_is_post_type_folders_enabled( $post_type ) ); ?> /> <span><?php echo esc_html( $label ); ?></span></label>
							<?php endforeach; ?>
						</div>
					</section>
					<?php $custom_post_types = array_diff_key( $this->get_available_post_types(), array_flip( [ 'post', 'page', 'attachment' ] ) ); ?>
			<?php if ( $custom_post_types && swmf_has_pro() ) : ?>
					<section class="swmf-content-type-settings" aria-labelledby="swmf-custom-type-settings-title">
						<h2 id="swmf-custom-type-settings-title"><?php esc_html_e( 'Custom post type folders', 'slim-media-folders' ); ?></h2>
						<p><?php esc_html_e( 'Enable folders for custom post types added by your theme or plugins.', 'slim-media-folders' ); ?></p>
						<div class="swmf-content-type-options">
							<?php foreach ( $custom_post_types as $post_type => $label ) : ?>
								<label class="swmf-content-type-option"><input type="checkbox" name="swmf_enabled_post_types[]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( swmf_is_post_type_folders_enabled( $post_type ) ); ?> /> <span><?php echo esc_html( $label ); ?></span></label>
							<?php endforeach; ?>
						</div>
			</section>
			<?php elseif ( $custom_post_types ) : ?>
			<section class="swmf-content-type-settings" aria-labelledby="swmf-custom-type-settings-title">
				<h2 id="swmf-custom-type-settings-title"><?php esc_html_e( 'Custom post type folders', 'slim-media-folders' ); ?></h2>
				<p><?php esc_html_e( 'Folders for custom post types are a Pro feature. Upgrade to organize content types added by your theme or plugins.', 'slim-media-folders' ); ?></p>
			</section>
			<?php endif; ?>
					<p class="swmf-folder-settings-actions"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save folder settings', 'slim-media-folders' ); ?></button></p>
					</form>
					<?php endif; ?>
				</div>
				<?php endif; ?>
				</main>

				<?php if ( 'overview' === $active_tab && ( $has_pro || $show_pro ) ) : ?>
				<aside class="swmf-sidebar swmf-license-sidebar" aria-labelledby="swmf-license-title">
					<section class="swmf-license-card">
						<span class="swmf-eyebrow"><?php esc_html_e( 'Your plan', 'slim-media-folders' ); ?></span>
						<h2 id="swmf-license-title"><?php esc_html_e( 'License status', 'slim-media-folders' ); ?></h2>
						<p class="swmf-license-state <?php echo $has_pro ? 'is-pro' : 'is-free'; ?>"><?php echo esc_html( $has_pro ? __( 'Pro active', 'slim-media-folders' ) : __( 'Free edition', 'slim-media-folders' ) ); ?></p>
						<?php if ( $has_pro ) : ?>
						<div class="swmf-license-success"><span class="swmf-success-icon" aria-hidden="true">&#10003;</span><h3><?php esc_html_e( 'Thank you for going Pro!', 'slim-media-folders' ); ?></h3><p><?php esc_html_e( 'Your Pro features are active.', 'slim-media-folders' ); ?></p></div>
						<?php else : ?>
						<a class="button button-primary swmf-license-button" href="<?php echo esc_url( $upgrade_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Let’s go Pro', 'slim-media-folders' ); ?></a>
						<?php endif; ?>
					</section>
				</aside>
				<?php endif; ?>

				<?php if ( 'folders' === $active_tab && swmf_can( 'view' ) ) : ?><div class="swmf-sidebar">
				<aside class="swmf-folder-overview" aria-label="<?php esc_attr_e( 'Folder overview', 'slim-media-folders' ); ?>">
					<div class="swmf-sidebar-heading"><div><span class="swmf-eyebrow"><?php esc_html_e( 'Your workspace', 'slim-media-folders' ); ?></span><h2><?php esc_html_e( 'Folder overview', 'slim-media-folders' ); ?></h2></div><span class="swmf-overview-total"><?php echo esc_html( number_format_i18n( $counts['total'] ) ); ?></span></div>
					<p class="swmf-overview-copy"><?php esc_html_e( 'Review your folders here. Create and organize them from the Media Library.', 'slim-media-folders' ); ?></p>
					<div class="swmf-overview-list"><?php $this->render_folder_overview( $tree ); ?></div>
					<div class="swmf-overview-footer"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>"><span class="dashicons dashicons-admin-media" aria-hidden="true"></span><?php esc_html_e( 'Manage folders', 'slim-media-folders' ); ?></a></div>
				</aside>
				</div><?php endif; ?>
			</div>
		</div>

		<?php if ( swmf_can( 'view' ) ) { $this->render_folder_modal(); } ?>
		<?php
	}

	private function render_folder_modal() {
		?>
		<div class="swmf-modal" id="swmf-folder-modal" style="display: none;">
			<div class="swmf-modal-overlay"></div>
			<div class="swmf-modal-content">
				<h2 id="swmf-modal-title"><?php esc_html_e( 'Create Folder', 'slim-media-folders' ); ?></h2>
				<form id="swmf-folder-form">
					<input type="hidden" id="swmf-folder-id" name="id" value="">
					<input type="hidden" id="swmf-folder-parent" name="parent_id" value="0">
					
					<table class="form-table">
						<tr>
							<th scope="row"><label for="swmf-folder-name"><?php esc_html_e( 'Folder Name', 'slim-media-folders' ); ?></label></th>
							<td><input type="text" id="swmf-folder-name" name="name" required></td>
						</tr>
						<tr>
							<th scope="row"><label for="swmf-folder-color"><?php esc_html_e( 'Color', 'slim-media-folders' ); ?></label></th>
							<td><input type="color" id="swmf-folder-color" name="color" value="#3498db"></td>
						</tr>
						<tr>
							<th scope="row"><label for="swmf-folder-locked"><?php esc_html_e( 'Lock Folder', 'slim-media-folders' ); ?></label></th>
							<td>
								<label>
									<input type="checkbox" id="swmf-folder-locked" name="is_locked" value="1">
									<span><?php esc_html_e( 'Prevent modifications to this folder', 'slim-media-folders' ); ?></span>
								</label>
							</td>
						</tr>
					</table>
					
					<div class="swmf-modal-actions">
						<button type="button" class="button" id="swmf-modal-cancel"><?php esc_html_e( 'Cancel', 'slim-media-folders' ); ?></button>
						<button type="submit" class="button button-primary" id="swmf-modal-submit"><?php esc_html_e( 'Save', 'slim-media-folders' ); ?></button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	private function get_available_post_types() {
		$post_types = get_post_types( [ 'show_ui' => true ], 'objects' );
		$types      = [];
		foreach ( $post_types as $post_type => $object ) {
			if ( in_array( $post_type, [ 'revision', 'nav_menu_item', 'wp_navigation' ], true ) || ! current_user_can( $object->cap->edit_posts ) ) {
				continue;
			}
			$types[ $post_type ] = $object->labels->name;
		}
		return $types;
	}

	private function get_supported_post_types() {
		return array_filter( $this->get_available_post_types(), function( $post_type ) {
			return swmf_is_post_type_folders_enabled( $post_type );
		}, ARRAY_FILTER_USE_KEY );
	}

	private function render_folder_overview( $folders, $depth = 0 ) {
		if ( empty( $folders ) ) {
			if ( 0 === $depth ) {
				echo '<p class="swmf-overview-empty">' . esc_html__( 'No folders created yet.', 'slim-media-folders' ) . '</p>';
			}
			return;
		}

		echo '<ul class="swmf-overview-tree">';
		foreach ( $folders as $folder ) {
			echo '<li><span class="swmf-overview-folder"><span class="swmf-overview-dot" style="background-color:' . esc_attr( $folder->color ) . '"></span><span class="dashicons dashicons-category" aria-hidden="true"></span><span class="swmf-overview-folder-name">' . esc_html( $folder->name ) . '</span><span class="swmf-overview-count">' . esc_html( number_format_i18n( $folder->count ) ) . '</span></span>';
			$this->render_folder_overview( $folder->children, $depth + 1 );
			echo '</li>';
		}
		echo '</ul>';
	}

}
