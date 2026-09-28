<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Database {
	private static $table_name = 'swmf_folders';
	private const SCHEMA_VERSION = '1.3.0';

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::$table_name;
	}

	public static function create_tables() {
		global $wpdb;

		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			object_type varchar(32) NOT NULL DEFAULT 'attachment',
			parent_id bigint(20) unsigned DEFAULT 0,
			sort_order int(11) DEFAULT 0,
			color varchar(7) DEFAULT '#3498db',
			is_locked tinyint(1) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY parent_id (parent_id),
			KEY type_parent_order (object_type, parent_id, sort_order)
		) $charset_collate;";

		$relationships_table = $wpdb->prefix . 'swmf_folder_relationships';
		$relationships_sql = "CREATE TABLE $relationships_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			folder_id bigint(20) unsigned NOT NULL,
			object_id bigint(20) unsigned NOT NULL,
			object_type varchar(32) NOT NULL DEFAULT 'attachment',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY folder_object (folder_id, object_id, object_type),
			KEY object_lookup (object_id, object_type),
			KEY folder_lookup (folder_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $relationships_sql );

		// Existing media assignments remain authoritative and are copied once into
		// the generalized relationship table for backwards compatibility.
		$wpdb->query( "INSERT IGNORE INTO $relationships_table (folder_id, object_id, object_type)
			SELECT CAST(pm.meta_value AS UNSIGNED), pm.post_id, 'attachment'
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'attachment'
			WHERE pm.meta_key = '_swmf_folder_id' AND pm.meta_value REGEXP '^[0-9]+$'" );

		// Post metadata is authoritative. Remove stale assignments and rebuild the
		// relationship table so upgrades cannot retain duplicate or orphaned rows.
		$wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm
			LEFT JOIN $table_name f ON f.id = CAST(pm.meta_value AS UNSIGNED)
			WHERE pm.meta_key = '_swmf_folder_id' AND f.id IS NULL" );
		$wpdb->query( "DELETE rel FROM $relationships_table rel
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = rel.object_id
				AND pm.meta_key = '_swmf_folder_id'
				AND CAST(pm.meta_value AS UNSIGNED) = rel.folder_id
			WHERE pm.post_id IS NULL" );
		update_option( 'swmf_schema_version', self::SCHEMA_VERSION );
	}

	/**
	 * Keep existing installs in step with table changes introduced after activation.
	 */
	public static function maybe_upgrade() {
		if ( self::SCHEMA_VERSION === get_option( 'swmf_schema_version' ) ) {
			return;
		}

		self::create_tables();
	}

	public static function drop_tables() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
	}
}
