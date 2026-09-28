<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Media_Library {
	public static function init() {
		add_filter( 'manage_media_columns', [ __CLASS__, 'add_folder_column' ] );
		add_action( 'manage_media_custom_column', [ __CLASS__, 'render_folder_column' ], 10, 2 );
		add_action( 'restrict_manage_posts', [ __CLASS__, 'add_folder_filter' ] );
		add_filter( 'parse_query', [ __CLASS__, 'filter_by_folder' ] );
		add_filter( 'ajax_query_attachments_args', [ __CLASS__, 'filter_ajax_attachments' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_media_scripts' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ __CLASS__, 'enqueue_elementor_media_scripts' ] );
		add_action( 'admin_footer-upload.php', [ __CLASS__, 'render_sidebar_container' ] );
		add_action( 'add_attachment', [ __CLASS__, 'assign_uploaded_attachment' ] );
		add_filter( 'wp_prepare_attachment_for_js', [ __CLASS__, 'add_folder_to_attachment_response' ], 10, 2 );
	}

	public static function add_folder_to_attachment_response( $response, $attachment ) {
		$response['swmf_folder_id'] = (int) get_post_meta( $attachment->ID, '_swmf_folder_id', true );
		return $response;
	}

	/** Assign only uploads carrying this plugin's signed folder selection. */
	public static function assign_uploaded_attachment( $attachment_id ) {
		if ( empty( $_REQUEST['swmf_upload_folder_id'] ) || empty( $_REQUEST['swmf_upload_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['swmf_upload_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'swmf_upload_folder' ) || ! swmf_can( 'move' ) || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return;
		}
		$folder_id = absint( $_REQUEST['swmf_upload_folder_id'] );
		$folder = $folder_id ? SWMF_Folder::get_by_id( $folder_id ) : false;
		if ( ! $folder || 'attachment' !== $folder->object_type || ! empty( $folder->is_locked ) || 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}
		update_post_meta( $attachment_id, '_swmf_folder_id', $folder_id );
		SWMF_Folder::assign_object( $folder_id, $attachment_id, 'attachment' );
		delete_transient( 'swmf_analytics_dashboard' );
	}

	public static function add_folder_column( $columns ) {
		$new_columns = [];
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['swmf_folder'] = __( 'Folder', 'slim-media-folders' );
			}
		}
		return $new_columns;
	}

	public static function render_folder_column( $column_name, $post_id ) {
		if ( 'swmf_folder' !== $column_name ) {
			return;
		}

		$folder_id = get_post_meta( $post_id, '_swmf_folder_id', true );

		if ( $folder_id ) {
			$folder = SWMF_Folder::get_by_id( $folder_id );
			if ( $folder ) {
				echo '<span class="swmf-folder-badge" style="background-color: ' . esc_attr( $folder->color ) . ';">' . esc_html( $folder->name ) . '</span>';
			}
		} else {
			echo '<span class="swmf-no-folder">' . esc_html__( '—', 'slim-media-folders' ) . '</span>';
		}
	}

	public static function add_folder_filter() {
		global $pagenow, $typenow;

		if ( 'upload.php' !== $pagenow || 'attachment' !== $typenow ) {
			return;
		}

		$folders = SWMF_Folder::get_tree();
		$current_folder = isset( $_GET['swmf_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['swmf_folder'] ) ) : '';

		echo '<select name="swmf_folder" id="swmf-folder-filter">';
		echo '<option value="">' . esc_html__( 'All Folders', 'slim-media-folders' ) . '</option>';
		echo '<option value="uncategorized"' . selected( 'uncategorized', $current_folder, false ) . '>' . esc_html__( 'Uncategorized', 'slim-media-folders' ) . '</option>';
		self::render_folder_options( $folders, 0, (int) $current_folder );
		echo '</select>';
	}

	private static function render_folder_options( $folders, $level, $current ) {
		foreach ( $folders as $folder ) {
			$indent = str_repeat( "\u{00A0}", 3 * $level );
			echo '<option value="' . esc_attr( (int) $folder->id ) . '"' . selected( (int) $folder->id, $current, false ) . '>' . esc_html( $indent . $folder->name ) . ' (' . (int) $folder->count . ')</option>';
			if ( ! empty( $folder->children ) ) {
				self::render_folder_options( $folder->children, $level + 1, $current );
			}
		}
	}

	public static function filter_by_folder( $query ) {
		global $pagenow, $typenow;

		if ( ! is_admin() || 'upload.php' !== $pagenow || 'attachment' !== $typenow ) {
			return;
		}

		if ( ! isset( $_GET['swmf_folder'] ) ) {
			return;
		}

		$folder = sanitize_text_field( wp_unslash( $_GET['swmf_folder'] ) );
		if ( '' === $folder || 'all' === $folder ) { return; }

		if ( 'uncategorized' === $folder ) {
			$meta_query = $query->get( 'meta_query' );
			if ( ! is_array( $meta_query ) ) {
				$meta_query = [];
			}
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => '_swmf_folder_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_swmf_folder_id',
					'value'   => 0,
					'compare' => '=',
				],
			];
			$query->set( 'meta_query', $meta_query );
		} else {
			$folder_id = (int) $folder;
			if ( $folder_id > 0 ) {
				$query->set( 'meta_key', '_swmf_folder_id' );
				$query->set( 'meta_value', $folder_id );
			}
		}
	}

	public static function filter_ajax_attachments( $query ) {
		if ( ! isset( $_REQUEST['query']['swmf_folder'] ) ) {
			return $query;
		}

		$folder = sanitize_text_field( wp_unslash( $_REQUEST['query']['swmf_folder'] ) );

		if ( empty( $folder ) || 'all' === $folder ) {
			return $query;
		}

		if ( 'uncategorized' === $folder ) {
			if ( ! isset( $query['meta_query'] ) || ! is_array( $query['meta_query'] ) ) {
				$query['meta_query'] = [];
			}
			$query['meta_query'][] = [
				'relation' => 'OR',
				[
					'key'     => '_swmf_folder_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_swmf_folder_id',
					'value'   => 0,
					'compare' => '=',
				],
			];
		} else {
			$folder_id = (int) $folder;
			if ( $folder_id > 0 ) {
				if ( ! isset( $query['meta_query'] ) || ! is_array( $query['meta_query'] ) ) {
					$query['meta_query'] = [];
				}
				$query['meta_query'][] = [
					'key'     => '_swmf_folder_id',
					'value'   => $folder_id,
					'compare' => '=',
				];
			}
		}

		return $query;
	}

	public static function render_sidebar_container() {
		// Handled dynamically below page header in assets/js/swmf-app.js
	}

	public static function enqueue_media_scripts( $hook ) {
		$media_screens = [ 'upload.php', 'edit.php', 'media-upload.php', 'post.php', 'post-new.php', 'site-editor.php', 'widgets.php', 'customize.php' ];

		if ( ! in_array( $hook, $media_screens, true ) ) {
			return;
		}

		if ( ! swmf_can( 'view' ) ) { return; }
		self::enqueue_media_modal_assets();

		if ( ! in_array( $hook, [ 'upload.php', 'edit.php' ], true ) ) {
			return;
		}
	if ( 'edit.php' === $hook && ! swmf_is_post_type_folders_enabled( isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : 'post' ) ) {
			return;
		}

		wp_enqueue_style( 'swmf-sidebar', SWMF_PLUGIN_URL . 'assets/css/swmf-sidebar.css', [], filemtime( SWMF_PLUGIN_DIR . 'assets/css/swmf-sidebar.css' ) );

		wp_enqueue_script(
			'swmf-sidebar-app',
			SWMF_PLUGIN_URL . 'assets/js/swmf-app.js',
			[ 'jquery', 'wp-element', 'media-views', 'media-editor' ],
			filemtime( SWMF_PLUGIN_DIR . 'assets/js/swmf-app.js' ),
			true
		);

		wp_localize_script( 'swmf-sidebar-app', 'swmf_data', [
			'permissions' => [ 'view' => swmf_can( 'view' ), 'create' => swmf_can( 'create' ), 'move' => swmf_can( 'move' ), 'edit' => swmf_can( 'edit' ), 'delete' => swmf_can( 'delete' ) ],
			'theme' => get_option( 'swmf_theme', 'system' ),
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'swmf_nonce' ),
			'object_type' => 'edit.php' === $hook && isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : ( 'edit.php' === $hook ? 'post' : 'attachment' ),
			'folder_query_arg' => 'edit.php' === $hook ? 'swmf_folder_id' : 'swmf_folder',
			'strings'  => [
				'folders'         => __( 'Folders', 'slim-media-folders' ),
				'all_files'       => __( 'All Files', 'slim-media-folders' ),
				'uncategorized'   => __( 'Uncategorized', 'slim-media-folders' ),
				'new_folder'      => __( 'New Folder', 'slim-media-folders' ),
				'edit_folders'    => __( 'Edit', 'slim-media-folders' ),
				'done'            => __( 'Done', 'slim-media-folders' ),
				'folder_settings' => __( 'Folder settings', 'slim-media-folders' ),
				'sort_folders'    => __( 'Sort folders', 'slim-media-folders' ),
				'sort_a_z'        => __( 'Sort: A–Z', 'slim-media-folders' ),
				'sort_z_a'        => __( 'Sort: Z–A', 'slim-media-folders' ),
				'drag_to_reorder' => __( 'Drag to reorder folders', 'slim-media-folders' ),
				'new_subfolder'   => __( 'New Subfolder', 'slim-media-folders' ),
				'rename'          => __( 'Rename', 'slim-media-folders' ),
				'change_color'    => __( 'Change Color', 'slim-media-folders' ),
				'delete'          => __( 'Delete', 'slim-media-folders' ),
				// translators: %s: the name of the folder to delete.
				'confirm_delete'  => __( 'Are you sure you want to delete "%s"? Items inside will be moved to Uncategorized.', 'slim-media-folders' ),
				'folder_name'     => __( 'Folder Name', 'slim-media-folders' ),
				'folder_id'       => __( 'Folder ID', 'slim-media-folders' ),
				'gallery_shortcode' => __( 'Gallery shortcode', 'slim-media-folders' ),
				'copy_gallery_shortcode' => __( 'Copy gallery shortcode', 'slim-media-folders' ),
				'shortcode_copied' => __( 'Gallery shortcode copied.', 'slim-media-folders' ),
				'shortcode_copy_failed' => __( 'Could not copy the shortcode.', 'slim-media-folders' ),
				'folder_color'    => __( 'Color', 'slim-media-folders' ),
				'save'            => __( 'Save', 'slim-media-folders' ),
				'cancel'          => __( 'Cancel', 'slim-media-folders' ),
				'search_folders'  => __( 'Search folders...', 'slim-media-folders' ),
				'show_more_folders' => __( 'Show more folders', 'slim-media-folders' ),
				// translators: %1$d: number of items moved; %2$d: total number of items to move.
				'moving_items' => __( 'Moving %1$d of %2$d items…', 'slim-media-folders' ),
				'no_folders'      => __( 'No folders found', 'slim-media-folders' ),
				'drop_here'       => __( 'Move items here', 'slim-media-folders' ),
				'expand_folders'  => __( 'Expand folders', 'slim-media-folders' ),
				'collapse_folders' => __( 'Collapse folders', 'slim-media-folders' ),
				'loading_folders' => __( 'Loading folders', 'slim-media-folders' ),
				// translators: %d: number of selected folders.
				'selected_count'  => __( '%d selected', 'slim-media-folders' ),
				'change_tags'     => __( 'Change tags', 'slim-media-folders' ),
				'set_color_tag'   => __( 'Set selected folders to this color tag', 'slim-media-folders' ),
				'folder_color_updated' => __( 'Folder color tag updated.', 'slim-media-folders' ),
				'lock'            => __( 'Lock', 'slim-media-folders' ),
				'unlock'          => __( 'Unlock', 'slim-media-folders' ),
				'delete_selected' => __( 'Delete', 'slim-media-folders' ),
				// translators: %d: number of selected virtual folders.
				'confirm_delete_selected' => __( 'Delete %d selected virtual folder(s)?', 'slim-media-folders' ),
				'selected_deleted' => __( 'Selected folders deleted.', 'slim-media-folders' ),
				'update_failed'   => __( 'Unable to update selected folders.', 'slim-media-folders' ),
				'update_retry'    => __( 'Unable to update selected folders. Please try again.', 'slim-media-folders' ),
				'folder_updated'  => __( 'Folder updated.', 'slim-media-folders' ),
				'update_folder_failed' => __( 'Failed to update folder.', 'slim-media-folders' ),
				'folder_deleted'  => __( 'Folder deleted.', 'slim-media-folders' ),
				'delete_folder_failed' => __( 'Unable to delete folder.', 'slim-media-folders' ),
				'delete_folder_retry' => __( 'Unable to delete folder. Please try again.', 'slim-media-folders' ),
				'select_folder_first' => __( 'Select at least one folder first.', 'slim-media-folders' ),
			],
		] );
	}

	/**
	 * Load the native WordPress media-frame filter in Elementor's editor too.
	 * Elementor opens the same wp.media modal for its image controls.
	 */
	public static function enqueue_elementor_media_scripts() {
		self::enqueue_media_modal_assets();
	}

	private static function enqueue_media_modal_assets() {
		wp_enqueue_style( 'swmf-media', SWMF_PLUGIN_URL . 'assets/css/media.css', [], filemtime( SWMF_PLUGIN_DIR . 'assets/css/media.css' ) );

		wp_enqueue_script(
			'swmf-media-modal',
			SWMF_PLUGIN_URL . 'assets/js/media.js',
			[ 'jquery', 'media-views', 'media-editor' ],
			filemtime( SWMF_PLUGIN_DIR . 'assets/js/media.js' ),
			true
		);

		wp_localize_script( 'swmf-media-modal', 'swmf_media_modal_data', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'swmf_nonce' ),
			'upload_nonce' => is_admin() && 'upload.php' === ( $GLOBALS['pagenow'] ?? '' ) ? wp_create_nonce( 'swmf_upload_folder' ) : '',
			'strings'  => [
				'all_files'     => __( 'All files', 'slim-media-folders' ),
				'uncategorized' => __( 'Uncategorized', 'slim-media-folders' ),
				'folder_label'  => __( 'Slim Folders', 'slim-media-folders' ),
				'upload_waiting' => __( 'Waiting to upload', 'slim-media-folders' ),
				'uploading' => __( 'Uploading', 'slim-media-folders' ),
				'upload_complete' => __( 'Uploaded to folder', 'slim-media-folders' ),
				'upload_failed' => __( 'Upload failed', 'slim-media-folders' ),
				'upload_unassigned' => __( 'Uploaded, but not added to the selected folder', 'slim-media-folders' ),
			],
		] );
	}
}

SWMF_Media_Library::init();
