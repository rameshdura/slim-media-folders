<?php
/**
 * Plugin Name: Slim Media Folders
 * Plugin URI: https://slim-media-folders-site.vercel.app/
 * Description: Organize your WordPress Media Library with virtual folders and build folder galleries.
 * Version: 1.1.1
 * Author: Ramesh Dura
 * Author URI: https://rameshdura.com.np
 * Text Domain: slim-media-folders
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SWMF_VERSION', '1.1.1' );
define( 'SWMF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SWMF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SWMF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SWMF_PRODUCT_CONFIG', require SWMF_PLUGIN_DIR . 'config/product.php' );

function swmf_product_config( $key = null, $default = null ) {
	$config = is_array( SWMF_PRODUCT_CONFIG ) ? SWMF_PRODUCT_CONFIG : [];
	if ( null === $key ) {
		return $config;
	}
	return array_key_exists( $key, $config ) ? $config[ $key ] : $default;
}

require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-activator.php';
require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-database.php';
require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-folder.php';
require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-ajax.php';
require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-media-library.php';
require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-gallery-renderer.php';
require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-gallery-block.php';
require_once SWMF_PLUGIN_DIR . 'includes/class-swmf-elementor-gallery.php';
require_once SWMF_PLUGIN_DIR . 'admin/class-swmf-admin.php';

register_activation_hook( __FILE__, [ 'SWMF_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SWMF_Activator', 'deactivate' ] );

add_action( 'plugins_loaded', 'swmf_init' );

function swmf_init() {
	load_plugin_textdomain( 'slim-media-folders', false, dirname( SWMF_PLUGIN_BASENAME ) . '/languages' );
	SWMF_Database::maybe_upgrade();

	SWMF_Admin::get_instance();
	SWMF_Ajax::get_instance();
	add_action( 'before_delete_post', [ 'SWMF_Folder', 'cleanup_deleted_post' ] );
	SWMF_Gallery_Block::init();
	SWMF_Elementor_Gallery::init();
}

/** Administrators always retain access; other roles follow the saved policy. */
function swmf_can( $feature ) {
	if ( in_array( $feature, [ 'analytics', 'review', 'zip', 'permissions', 'white_label', 'reorder' ], true ) && ! swmf_has_pro() ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	$defaults = [
		'view' => [ 'editor', 'author', 'contributor' ],
		'create' => [ 'editor', 'author' ],
		'move' => [ 'editor', 'author' ],
		'edit' => [ 'editor' ],
		'delete' => [ 'editor' ],
		'analytics' => [ 'editor' ],
		'review' => [ 'editor' ],
	];
	if ( ! isset( $defaults[ $feature ] ) ) {
		return false;
	}
	$permissions = get_option( 'swmf_role_permissions', [] );
	$legacy = isset( $permissions['organize'] ) && in_array( $feature, [ 'view', 'create', 'move', 'edit', 'delete' ], true ) ? (array) $permissions['organize'] : $defaults[ $feature ];
	$roles = isset( $permissions[ $feature ] ) ? (array) $permissions[ $feature ] : $legacy;
	return (bool) array_intersect( $roles, (array) wp_get_current_user()->roles );
}

/** The source repository is the complete edition; builds may filter features. */
function swmf_has_pro() {
	/**
	 * The base plugin is always the Free edition. The separate Pro add-on
	 * explicitly enables premium modules through this filter.
	 */
	return (bool) apply_filters( 'swmf_pro_active', false );
}

/** Return whether this edition exposes folder support for a content type. */
function swmf_is_post_type_folders_enabled( $post_type ) {
	$post_type = sanitize_key( $post_type );
	$available_types = apply_filters( 'swmf_folder_content_types', [ 'attachment' ] );
	if ( ! in_array( $post_type, (array) $available_types, true ) ) {
		return false;
	}
	if ( 'attachment' === $post_type ) {
		return true;
	}
	if ( ! post_type_exists( $post_type ) ) {
		return false;
	}
	$enabled = get_option( 'swmf_enabled_post_types', null );
	return in_array( $post_type, (array) $enabled, true );
}
