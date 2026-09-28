<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Ajax {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_swmf_get_folders', [ $this, 'get_folders' ] );
		add_action( 'wp_ajax_swmf_create_folder', [ $this, 'create_folder' ] );
		add_action( 'wp_ajax_swmf_update_folder', [ $this, 'update_folder' ] );
		add_action( 'wp_ajax_swmf_delete_folder', [ $this, 'delete_folder' ] );
		add_action( 'wp_ajax_swmf_bulk_update_folders', [ $this, 'bulk_update_folders' ] );
		add_action( 'wp_ajax_swmf_bulk_delete_folders', [ $this, 'bulk_delete_folders' ] );
		add_action( 'wp_ajax_swmf_assign_folder', [ $this, 'assign_folder' ] );
		add_action( 'wp_ajax_swmf_bulk_assign_folder', [ $this, 'bulk_assign_folder' ] );
		add_action( 'wp_ajax_swmf_remove_folder', [ $this, 'remove_folder' ] );
		add_action( 'wp_ajax_swmf_get_folder_tree', [ $this, 'get_folder_tree' ] );
	}

	protected function can_manage_folders( $object_type = 'attachment', $permission = 'view' ) {
		if ( ! swmf_is_post_type_folders_enabled( $object_type ) || ! swmf_can( $permission ) ) {
			return false;
		}
		if ( 'attachment' === $object_type ) {
			return current_user_can( 'upload_files' );
		}

		$post_type = get_post_type_object( $object_type );
		return $post_type && current_user_can( $post_type->cap->edit_posts );
	}

	public function get_folders() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );

		$parent_id   = isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;
		$object_type = isset( $_POST['object_type'] ) ? sanitize_key( $_POST['object_type'] ) : 'attachment';
		if ( ! post_type_exists( $object_type ) ) {
			wp_send_json_error( __( 'Invalid content type', 'slim-media-folders' ) );
		}
		if ( ! $this->can_manage_folders( $object_type ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}
		$folders = SWMF_Folder::get_all( [ 'parent_id' => $parent_id, 'object_type' => $object_type ] );
		global $wpdb;
		$table = SWMF_Folder::get_table_name();
		$child_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT parent_id FROM $table WHERE object_type = %s AND parent_id > 0", $object_type ) );
		$has_children = array_fill_keys( array_map( 'intval', $child_ids ), true );
		foreach ( $folders as $folder ) {
			$folder->has_children = isset( $has_children[ (int) $folder->id ] );
		}

		wp_send_json_success( $folders );
	}

	public function create_folder() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );

		$data = [
			'name'       => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'object_type'=> isset( $_POST['object_type'] ) ? sanitize_key( wp_unslash( $_POST['object_type'] ) ) : 'attachment',
			'parent_id'  => isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0,
			'color'      => isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '#3498db',
			'is_locked'  => isset( $_POST['is_locked'] ) ? (int) $_POST['is_locked'] : 0,
		];

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( __( 'Folder name is required', 'slim-media-folders' ) );
		}
		if ( ! post_type_exists( $data['object_type'] ) ) {
			wp_send_json_error( __( 'Invalid content type', 'slim-media-folders' ) );
		}
		if ( ! $this->can_manage_folders( $data['object_type'], 'create' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}
		if ( $data['parent_id'] ) {
			$parent = SWMF_Folder::get_by_id( $data['parent_id'] );
			if ( ! $parent || $parent->object_type !== $data['object_type'] ) {
				wp_send_json_error( __( 'Invalid folder', 'slim-media-folders' ) );
			}
			if ( ! empty( $parent->is_locked ) ) {
				wp_send_json_error( __( 'Folder is locked.', 'slim-media-folders' ) );
			}
		}

		$folder_id = SWMF_Folder::create( $data );

		if ( $folder_id ) {
			delete_transient( 'swmf_analytics_dashboard' );
			$folder = SWMF_Folder::get_by_id( $folder_id );
			wp_send_json_success( $folder );
		}

		wp_send_json_error( __( 'Failed to create folder', 'slim-media-folders' ) );
	}

	public function update_folder() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );

		$id   = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$folder = SWMF_Folder::get_by_id( $id );
		if ( ! $folder || ! $this->can_manage_folders( $folder->object_type, 'edit' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}
		$data = [];

		if ( isset( $_POST['name'] ) ) {
			$data['name'] = sanitize_text_field( wp_unslash( $_POST['name'] ) );
		}
		if ( isset( $_POST['parent_id'] ) ) {
			$parent_id = (int) $_POST['parent_id'];
			if ( $parent_id !== (int) $folder->parent_id && ! $this->can_manage_folders( $folder->object_type, 'move' ) ) {
				wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
			}
			if ( $parent_id > 0 ) {
				$parent = SWMF_Folder::get_by_id( $parent_id );
				if ( ! $parent || $parent->object_type !== $folder->object_type || SWMF_Folder::would_create_cycle( $id, $parent_id ) ) {
					wp_send_json_error( __( 'Invalid folder', 'slim-media-folders' ) );
				}
				if ( ! empty( $parent->is_locked ) ) {
					wp_send_json_error( __( 'Folder is locked.', 'slim-media-folders' ) );
				}
			} elseif ( $parent_id === $id ) {
				wp_send_json_error( __( 'Invalid folder', 'slim-media-folders' ) );
			}
			$data['parent_id'] = $parent_id;
		}
		if ( isset( $_POST['color'] ) ) {
			$data['color'] = sanitize_hex_color( wp_unslash( $_POST['color'] ) );
		}
		if ( isset( $_POST['is_locked'] ) ) {
			$data['is_locked'] = (int) $_POST['is_locked'];
		}

		$result = SWMF_Folder::update( $id, $data );

		if ( $result !== false ) {
			delete_transient( 'swmf_analytics_dashboard' );
			$folder = SWMF_Folder::get_by_id( $id );
			wp_send_json_success( $folder );
		}

		wp_send_json_error( __( 'Failed to update folder', 'slim-media-folders' ) );
	}

	public function delete_folder() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );

		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$folder = SWMF_Folder::get_by_id( $id );
		if ( ! $folder || ! $this->can_manage_folders( $folder->object_type, 'delete' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}
		if ( SWMF_Folder::is_locked( $id ) || SWMF_Folder::has_locked_descendant( $id ) ) {
			wp_send_json_error( __( 'Folder is locked.', 'slim-media-folders' ) );
		}
		$result = SWMF_Folder::delete( $id );

		if ( $result !== false ) {
			wp_send_json_success( [ 'id' => $id ] );
		}

		wp_send_json_error( __( 'Failed to delete folder', 'slim-media-folders' ) );
	}

	public function bulk_update_folders() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );

		$ids  = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];
		$data = [];

		if ( isset( $_POST['color'] ) ) {
			$data['color'] = sanitize_hex_color( wp_unslash( $_POST['color'] ) );
		}
		if ( isset( $_POST['is_locked'] ) ) {
			$data['is_locked'] = (int) $_POST['is_locked'];
		}

		if ( empty( $ids ) || empty( $data ) ) {
			wp_send_json_error( __( 'Invalid request', 'slim-media-folders' ) );
		}
		foreach ( $ids as $id ) {
			$folder = SWMF_Folder::get_by_id( $id );
			if ( ! $folder || ! $this->can_manage_folders( $folder->object_type, 'edit' ) ) {
				wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
			}
		}

		$results = SWMF_Folder::bulk_update( $ids, $data );
		wp_send_json_success( $results );
	}

	public function bulk_delete_folders() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );

		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : [];

		if ( empty( $ids ) ) {
			wp_send_json_error( __( 'No folders selected', 'slim-media-folders' ) );
		}
		foreach ( $ids as $id ) {
			$folder = SWMF_Folder::get_by_id( $id );
			if ( ! $folder || ! $this->can_manage_folders( $folder->object_type, 'delete' ) ) {
				wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
			}
			if ( SWMF_Folder::is_locked( $id ) || SWMF_Folder::has_locked_descendant( $id ) ) {
				wp_send_json_error( __( 'Locked virtual folders cannot be deleted', 'slim-media-folders' ) );
			}
		}

		$results = SWMF_Folder::bulk_delete( $ids );
		wp_send_json_success( $results );
	}

	public function assign_folder() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );
		if ( ! swmf_can( 'move' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		$object_id     = isset( $_POST['object_id'] ) ? (int) $_POST['object_id'] : $attachment_id;
		$object_type   = isset( $_POST['object_type'] ) ? sanitize_key( $_POST['object_type'] ) : 'attachment';
		$folder_id     = isset( $_POST['folder_id'] ) ? (int) $_POST['folder_id'] : 0;

		if ( ! $object_id || ! swmf_is_post_type_folders_enabled( $object_type ) || get_post_type( $object_id ) !== $object_type || ! current_user_can( 'edit_post', $object_id ) ) {
			wp_send_json_error( __( 'Invalid content item or insufficient permissions', 'slim-media-folders' ) );
		}
		if ( $object_type !== 'attachment' && ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}
		$current_folder_id = (int) get_post_meta( $object_id, '_swmf_folder_id', true );
		if ( $current_folder_id && $current_folder_id !== $folder_id && SWMF_Folder::is_locked( $current_folder_id ) ) {
			wp_send_json_error( __( 'An item cannot be removed from a locked virtual folder', 'slim-media-folders' ) );
		}

		if ( $folder_id ) {
			$folder = SWMF_Folder::get_by_id( $folder_id );
			if ( ! $folder ) {
				wp_send_json_error( __( 'Invalid folder', 'slim-media-folders' ) );
			}
			if ( ! empty( $folder->is_locked ) ) {
				wp_send_json_error( __( 'Folder is locked.', 'slim-media-folders' ) );
			}
			if ( SWMF_Folder::get_object_type( $folder_id ) !== $object_type ) {
				wp_send_json_error( __( 'This folder is for a different content type.', 'slim-media-folders' ) );
			}
			update_post_meta( $object_id, '_swmf_folder_id', $folder_id );
			SWMF_Folder::assign_object( $folder_id, $object_id, $object_type );
		} else {
			if ( $current_folder_id ) {
				SWMF_Folder::unassign_object( $current_folder_id, $object_id, $object_type );
			}
			delete_post_meta( $object_id, '_swmf_folder_id' );
		}
		delete_transient( 'swmf_analytics_dashboard' );

		wp_send_json_success( [ 'folder_id' => $folder_id, 'object_id' => $object_id, 'object_type' => $object_type ] );
	}

	public function bulk_assign_folder() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );

		$object_type = isset( $_POST['object_type'] ) ? sanitize_key( $_POST['object_type'] ) : 'attachment';
		$attachment_ids = isset( $_POST['object_ids'] ) ? array_map( 'intval', (array) $_POST['object_ids'] ) : ( isset( $_POST['attachment_ids'] ) ? array_map( 'intval', (array) $_POST['attachment_ids'] ) : [] );
		$attachment_ids = array_values( array_unique( $attachment_ids ) );
		$folder_id      = isset( $_POST['folder_id'] ) ? (int) $_POST['folder_id'] : 0;
		if ( count( $attachment_ids ) > 200 ) {
			wp_send_json_error( __( 'Invalid request', 'slim-media-folders' ) );
		}

		if ( ! post_type_exists( $object_type ) || ! $this->can_manage_folders( $object_type, 'move' ) || empty( $attachment_ids ) ) {
			wp_send_json_error( __( 'No content items specified or insufficient permissions', 'slim-media-folders' ) );
		}
		if ( $folder_id > 0 && SWMF_Folder::is_locked( $folder_id ) ) {
			wp_send_json_error( __( 'Folder is locked.', 'slim-media-folders' ) );
		}
		if ( $folder_id > 0 && SWMF_Folder::get_object_type( $folder_id ) !== $object_type ) {
			wp_send_json_error( __( 'This folder is for a different content type.', 'slim-media-folders' ) );
		}
		foreach ( $attachment_ids as $att_id ) {
			if ( get_post_type( $att_id ) !== $object_type || ! current_user_can( 'edit_post', $att_id ) ) {
				wp_send_json_error( __( 'Invalid content item or insufficient permissions', 'slim-media-folders' ) );
			}
			$current_folder_id = (int) get_post_meta( $att_id, '_swmf_folder_id', true );
			if ( $current_folder_id && $current_folder_id !== $folder_id && SWMF_Folder::is_locked( $current_folder_id ) ) {
				wp_send_json_error( __( 'An item cannot be removed from a locked virtual folder', 'slim-media-folders' ) );
			}
		}

		foreach ( $attachment_ids as $att_id ) {
			$current_folder_id = (int) get_post_meta( $att_id, '_swmf_folder_id', true );
			if ( $folder_id > 0 ) {
				update_post_meta( $att_id, '_swmf_folder_id', $folder_id );
				SWMF_Folder::assign_object( $folder_id, $att_id, $object_type );
			} else {
				delete_post_meta( $att_id, '_swmf_folder_id' );
				if ( $current_folder_id ) {
					SWMF_Folder::unassign_object( $current_folder_id, $att_id, $object_type );
				}
			}
		}
		delete_transient( 'swmf_analytics_dashboard' );

		$counts = ! isset( $_POST['include_counts'] ) || (int) $_POST['include_counts']
			? ( 'attachment' === $object_type ? SWMF_Folder::get_counts() : SWMF_Folder::get_object_counts( $object_type ) )
			: null;
		wp_send_json_success( [
			'folder_id'      => $folder_id,
			'attachment_ids' => $attachment_ids,
			'counts'         => $counts,
		] );
	}

	public function remove_folder() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );
		if ( ! swmf_can( 'move' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}

		$attachment_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'Invalid attachment', 'slim-media-folders' ) );
		}
		$current_folder_id = (int) get_post_meta( $attachment_id, '_swmf_folder_id', true );
		if ( $current_folder_id && SWMF_Folder::is_locked( $current_folder_id ) ) {
			wp_send_json_error( __( 'An item cannot be removed from a locked virtual folder', 'slim-media-folders' ) );
		}

		delete_post_meta( $attachment_id, '_swmf_folder_id' );
		if ( $current_folder_id ) {
			SWMF_Folder::unassign_object( $current_folder_id, $attachment_id, 'attachment' );
		}
		delete_transient( 'swmf_analytics_dashboard' );
		wp_send_json_success( [ 'counts' => SWMF_Folder::get_counts() ] );
	}

	public function get_folder_tree() {
		check_ajax_referer( 'swmf_nonce', 'nonce' );
		$object_type = isset( $_POST['object_type'] ) ? sanitize_key( $_POST['object_type'] ) : 'attachment';
		if ( ! post_type_exists( $object_type ) || ! $this->can_manage_folders( $object_type, 'view' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'slim-media-folders' ) );
		}

		$counts = 'attachment' === $object_type ? SWMF_Folder::get_counts() : SWMF_Folder::get_object_counts( $object_type );
		if ( ! isset( $counts['folders'] ) ) {
			$counts['folders'] = [];
		}
		$tree = SWMF_Folder::get_tree_for_type( 0, $counts, $object_type );

		wp_send_json_success( [
			'tree'   => $tree,
			'counts' => $counts,
		] );
	}





}
