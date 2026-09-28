<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Folder {
	private static $table_name = 'swmf_folders';

	public static function get_all( $args = [] ) {
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'parent_id' => 0,
			'object_type' => '',
			'orderby'   => 'sort_order',
			'order'     => 'ASC',
		];
		$args = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'parent_id = %d', $args['parent_id'] );
		if ( ! empty( $args['object_type'] ) ) {
			$where .= $wpdb->prepare( ' AND object_type = %s', sanitize_key( $args['object_type'] ) );
		}
		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( 'sort_order' === $args['orderby'] ) {
			$orderby .= ', name ASC';
		}

		return $wpdb->get_results( "SELECT * FROM $table WHERE $where ORDER BY $orderby" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic clauses are prepared/allowlisted above.
	}

	public static function get_counts() {
		global $wpdb;
		$total_attachments = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status != 'trash'" );

		$folder_counts_raw = $wpdb->get_results(
			"SELECT pm.meta_value as folder_id, COUNT(p.ID) as count
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_swmf_folder_id'
			 WHERE p.post_type = 'attachment' AND p.post_status != 'trash'
			 GROUP BY pm.meta_value"
		);

		$folder_counts = [];
		$categorized_count = 0;
		foreach ( $folder_counts_raw as $row ) {
			$f_id = (int) $row->folder_id;
			$cnt = (int) $row->count;
			$folder_counts[ $f_id ] = $cnt;
			$categorized_count += $cnt;
		}

		$uncategorized = max( 0, $total_attachments - $categorized_count );

		return [
			'total'         => $total_attachments,
			'uncategorized' => $uncategorized,
			'folders'       => $folder_counts,
		];
	}

	public static function get_object_counts( $object_type ) {
		global $wpdb;
		$object_type = sanitize_key( $object_type );
		$total       = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft')",
			$object_type
		) );
		$categorized = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_swmf_folder_id' WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft')",
			$object_type
		) );
		$folder_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT pm.meta_value AS folder_id, COUNT(p.ID) AS count FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_swmf_folder_id' WHERE p.post_type = %s AND p.post_status NOT IN ('trash', 'auto-draft') GROUP BY pm.meta_value",
			$object_type
		) );
		$folders = [];
		foreach ( $folder_rows as $row ) {
			$folders[ (int) $row->folder_id ] = (int) $row->count;
		}

		return [
			'total'         => $total,
			'uncategorized' => max( 0, $total - $categorized ),
			'folders'       => $folders,
		];
	}

	public static function get_tree( $parent_id = 0, $counts = null ) {
		return self::get_tree_for_type( $parent_id, $counts );
	}

	public static function get_tree_for_type( $parent_id = 0, $counts = null, $object_type = '' ) {
		if ( null === $counts ) {
			$counts = self::get_counts();
		}
		global $wpdb;
		$table = self::get_table_name();
		$rows = $object_type
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE object_type = %s ORDER BY parent_id ASC, sort_order ASC, name ASC", sanitize_key( $object_type ) ) )
			: $wpdb->get_results( "SELECT * FROM $table ORDER BY parent_id ASC, sort_order ASC, name ASC" );
		foreach ( $rows as $folder ) {
			$folder->count = isset( $counts['folders'][ (int) $folder->id ] ) ? (int) $counts['folders'][ (int) $folder->id ] : 0;
			$folder->children = [];
		}
		$children_by_parent = [];
		foreach ( $rows as $folder ) {
			$children_by_parent[ (int) $folder->parent_id ][] = $folder;
		}
		$tree = [];
		$visited = [];
		$stack = [ [ (int) $parent_id, null ] ];
		while ( $stack ) {
			$entry = array_pop( $stack );
			$parent = $entry[0];
			$parent_folder = $entry[1];
			foreach ( $children_by_parent[ $parent ] ?? [] as $folder ) {
				$id = (int) $folder->id;
				if ( isset( $visited[ $id ] ) ) {
					continue;
				}
				$visited[ $id ] = true;
				if ( $parent_folder ) {
					$parent_folder->children[] = $folder;
				} else {
					$tree[] = $folder;
				}
				$stack[] = [ $id, $folder ];
			}
		}
		return $tree;
	}

	public static function get_by_id( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
	}

	/**
	 * Whether a folder has immediate children of the same content type.
	 */
	public static function has_children( $parent_id, $object_type = '' ) {
		global $wpdb;
		$table = self::get_table_name();
		if ( '' !== $object_type ) {
			return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE parent_id = %d AND object_type = %s", (int) $parent_id, sanitize_key( $object_type ) ) );
		}

		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE parent_id = %d", (int) $parent_id ) );
	}

	public static function get_object_type( $id ) {
		$folder = self::get_by_id( $id );
		return $folder && ! empty( $folder->object_type ) ? $folder->object_type : 'attachment';
	}

	public static function assign_object( $folder_id, $object_id, $object_type = 'attachment' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'swmf_folder_relationships';
		$object_type = sanitize_key( $object_type );
		if ( ! $folder_id || ! $object_id || ! post_type_exists( $object_type ) ) {
			return false;
		}
		// An object belongs to one virtual folder per content type.
		$wpdb->delete( $table, [
			'object_id'   => (int) $object_id,
			'object_type' => $object_type,
		] );
		return (bool) $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO $table (folder_id, object_id, object_type) VALUES (%d, %d, %s)",
			(int) $folder_id,
			(int) $object_id,
			$object_type
		) );
	}

	public static function unassign_object( $folder_id, $object_id, $object_type = 'attachment' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'swmf_folder_relationships';
		return false !== $wpdb->delete( $table, [
			'folder_id' => (int) $folder_id,
			'object_id' => (int) $object_id,
			'object_type' => sanitize_key( $object_type ),
		] );
	}

	/** Remove denormalized relationships before WordPress permanently deletes a post. */
	public static function cleanup_deleted_post( $post_id ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'swmf_folder_relationships', [ 'object_id' => (int) $post_id ], [ '%d' ] );
		delete_transient( 'swmf_analytics_dashboard' );
	}

	public static function get_objects( $folder_id, $object_type = 'attachment' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'swmf_folder_relationships';
		return $wpdb->get_col( $wpdb->prepare(
			"SELECT object_id FROM $table WHERE folder_id = %d AND object_type = %s ORDER BY id ASC",
			(int) $folder_id,
			sanitize_key( $object_type )
		) );
	}

	public static function is_locked( $id ) {
		$folder = self::get_by_id( $id );
		return $folder && ! empty( $folder->is_locked );
	}

	public static function has_locked_descendant( $parent_id ) {
		global $wpdb;
		$table = self::get_table_name();
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE parent_id = %d", $parent_id ) );
		foreach ( $children as $child_id ) {
			if ( self::is_locked( $child_id ) || self::has_locked_descendant( $child_id ) ) {
				return true;
			}
		}
		return false;
	}

	/** Whether assigning $parent_id would place a folder below itself. */
	public static function would_create_cycle( $folder_id, $parent_id ) {
		$folder_id = (int) $folder_id;
		$parent_id = (int) $parent_id;
		$seen      = [];

		while ( $parent_id > 0 ) {
			if ( $parent_id === $folder_id || isset( $seen[ $parent_id ] ) ) {
				return true;
			}
			$seen[ $parent_id ] = true;
			$parent = self::get_by_id( $parent_id );
			if ( ! $parent ) {
				return false;
			}
			$parent_id = (int) $parent->parent_id;
		}

		return false;
	}

	public static function create( $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$data = wp_parse_args( $data, [
			'name'       => '',
			'object_type' => 'attachment',
			'parent_id'  => 0,
			'color'      => '#3498db',
			'is_locked'  => 0,
			'sort_order' => null,
		] );

		if ( null === $data['sort_order'] ) {
			$max_sort_order = $wpdb->get_var(
				$wpdb->prepare( "SELECT MAX(sort_order) FROM $table WHERE parent_id = %d", (int) $data['parent_id'] )
			);
			$data['sort_order'] = null === $max_sort_order ? 0 : ( (int) $max_sort_order + 1 );
		}

		$result = $wpdb->insert( $table, [
			'name'      => sanitize_text_field( $data['name'] ),
			'object_type' => sanitize_key( $data['object_type'] ),
			'parent_id' => (int) $data['parent_id'],
			'color'     => sanitize_hex_color( $data['color'] ),
			'is_locked' => (int) $data['is_locked'],
			'sort_order' => (int) $data['sort_order'],
		] );

		if ( $result ) {
			return $wpdb->insert_id;
		}
		return false;
	}

	public static function update( $id, $data ) {
		global $wpdb;
		$table = self::get_table_name();

		$allowed = [ 'name', 'object_type', 'parent_id', 'color', 'is_locked', 'sort_order' ];
		$update_data = [];

		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
					switch ( $field ) {
					case 'name':
						$update_data[ $field ] = sanitize_text_field( $data[ $field ] );
						break;
					case 'parent_id':
						$update_data[ $field ] = (int) $data[ $field ];
						break;
					case 'object_type':
						$update_data[ $field ] = sanitize_key( $data[ $field ] );
						break;
					case 'color':
						$update_data[ $field ] = sanitize_hex_color( $data[ $field ] );
						break;
					case 'is_locked':
						$update_data[ $field ] = (int) $data[ $field ];
						break;
					case 'sort_order':
						$update_data[ $field ] = (int) $data[ $field ];
						break;
				}
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		return $wpdb->update( $table, $update_data, [ 'id' => (int) $id ] );
	}

	public static function delete( $id ) {
		global $wpdb;
		$table = self::get_table_name();
		$relationships_table = $wpdb->prefix . 'swmf_folder_relationships';
		$folder_ids = self::get_descendant_ids( $id );
		array_unshift( $folder_ids, (int) $id );
		$folder_ids = array_values( array_unique( array_filter( array_map( 'absint', $folder_ids ) ) ) );
		if ( empty( $folder_ids ) ) {
			return false;
		}

		$placeholders = implode( ',', array_fill( 0, count( $folder_ids ), '%d' ) );
		$wpdb->query( 'START TRANSACTION' );
		try {
			$meta_deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_swmf_folder_id' AND CAST(meta_value AS UNSIGNED) IN ($placeholders)",
				$folder_ids
			) );
			$relationships_deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM $relationships_table WHERE folder_id IN ($placeholders)",
				$folder_ids
			) );
			$folders_deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM $table WHERE id IN ($placeholders)",
				$folder_ids
			) );

			if ( false === $meta_deleted || false === $relationships_deleted || false === $folders_deleted || 0 === $folders_deleted ) {
				throw new RuntimeException( 'Unable to delete virtual folder data.' );
			}
			$wpdb->query( 'COMMIT' );
			delete_transient( 'swmf_analytics_dashboard' );
			return $folders_deleted;
		} catch ( Throwable $error ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
	}

	private static function get_descendant_ids( $parent_id, &$seen = [] ) {
		global $wpdb;
		$table = self::get_table_name();
		$ids = [];
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE parent_id = %d", $parent_id ) );
		foreach ( $children as $child_id ) {
			$child_id = (int) $child_id;
			if ( isset( $seen[ $child_id ] ) ) {
				continue;
			}
			$seen[ $child_id ] = true;
			$ids[] = $child_id;
			$ids = array_merge( $ids, self::get_descendant_ids( $child_id, $seen ) );
		}
		return $ids;
	}

	public static function bulk_update( $ids, $data ) {
		$results = [];
		foreach ( $ids as $id ) {
			$results[ $id ] = self::update( $id, $data );
		}
		return $results;
	}

	public static function bulk_delete( $ids ) {
		$results = [];
		foreach ( $ids as $id ) {
			$results[ $id ] = self::delete( $id );
		}
		return $results;
	}

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}
}
