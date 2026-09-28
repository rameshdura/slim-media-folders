<?php
/**
 * Front-end gallery block for images stored in a Slim Media Folders folder.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Gallery_Block {
	const BLOCK_NAME = 'slim-media-folders/gallery';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_front_assets' ] );
	}

	public static function register() {
		wp_register_script(
			'swmf-gallery-block-editor',
			SWMF_PLUGIN_URL . 'assets/js/gallery-block.js',
			[ 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-element', 'wp-i18n', 'wp-server-side-render' ],
			filemtime( SWMF_PLUGIN_DIR . 'assets/js/gallery-block.js' ),
			true
		);
		wp_localize_script( 'swmf-gallery-block-editor', 'swmfGalleryBlock', [
			'folders' => self::get_folder_options(),
			'imageSizes' => [
				[ 'label' => __( 'Thumbnail', 'slim-media-folders' ), 'value' => 'thumbnail' ],
				[ 'label' => __( 'Medium', 'slim-media-folders' ), 'value' => 'medium' ],
				[ 'label' => __( 'Large', 'slim-media-folders' ), 'value' => 'large' ],
				[ 'label' => __( 'Full size', 'slim-media-folders' ), 'value' => 'full' ],
			],
			'strings' => [
				'title'              => __( 'Media Folder Gallery', 'slim-media-folders' ),
				'description'        => __( 'Display images from a media folder.', 'slim-media-folders' ),
				'folder'             => __( 'Media folder', 'slim-media-folders' ),
				'chooseFolder'       => __( 'Choose a folder', 'slim-media-folders' ),
				'columns'            => __( 'Columns', 'slim-media-folders' ),
				'imageSize'          => __( 'Image size', 'slim-media-folders' ),
				'lightbox'           => __( 'Open images in a lightbox', 'slim-media-folders' ),
				'captions'           => __( 'Show captions', 'slim-media-folders' ),
				'layout'             => __( 'Layout', 'slim-media-folders' ),
				'grid'               => __( 'Grid', 'slim-media-folders' ),
				'masonry'            => __( 'Masonry', 'slim-media-folders' ),
				'perPage'            => __( 'Images per page (0 for all)', 'slim-media-folders' ),
				'noFolderTitle'      => __( 'Choose a media folder', 'slim-media-folders' ),
				'noFolderDescription'=> __( 'Select a folder in the block settings to show its images.', 'slim-media-folders' ),
			],
		] );

		wp_register_style(
			'swmf-gallery-block',
			SWMF_PLUGIN_URL . 'assets/css/gallery-block.css',
			[],
			filemtime( SWMF_PLUGIN_DIR . 'assets/css/gallery-block.css' )
		);
		wp_register_script(
			'swmf-gallery-block-view',
			SWMF_PLUGIN_URL . 'assets/js/gallery-lightbox.js',
			[],
			filemtime( SWMF_PLUGIN_DIR . 'assets/js/gallery-lightbox.js' ),
			true
		);
		wp_localize_script( 'swmf-gallery-block-view', 'swmfGalleryLightbox', [
			'closeLabel' => __( 'Close image preview', 'slim-media-folders' ),
			'previousLabel' => __( 'Previous image', 'slim-media-folders' ),
			'nextLabel'     => __( 'Next image', 'slim-media-folders' ),
		] );

		register_block_type( self::BLOCK_NAME, [
			'api_version'     => 3,
			'editor_script'   => 'swmf-gallery-block-editor',
			'view_script'     => 'swmf-gallery-block-view',
			'style'           => 'swmf-gallery-block',
			'render_callback' => [ __CLASS__, 'render' ],
			'attributes'      => [
				'folderId' => [ 'type' => 'integer', 'default' => 0 ],
				'columns'  => [ 'type' => 'integer', 'default' => 3 ],
				'imageSize'=> [ 'type' => 'string', 'default' => 'large' ],
				'lightbox' => [ 'type' => 'boolean', 'default' => true ],
				'captions' => [ 'type' => 'boolean', 'default' => false ],
				'layout' => [ 'type' => 'string', 'default' => 'grid' ],
				'perPage' => [ 'type' => 'integer', 'default' => 0 ],
			],
		] );
	}

	private static function get_folder_options() {
		$options = [];
		self::append_folder_options( SWMF_Folder::get_tree(), $options );
		return $options;
	}

	private static function append_folder_options( $folders, &$options, $depth = 0 ) {
		foreach ( $folders as $folder ) {
			$options[] = [
				'label' => str_repeat( '— ', $depth ) . $folder->name,
				'value' => (string) $folder->id,
			];
			if ( ! empty( $folder->children ) ) {
				self::append_folder_options( $folder->children, $options, $depth + 1 );
			}
		}
	}

	public static function render( $attributes ) {
		return SWMF_Gallery_Renderer::render( [
			'folder_id' => $attributes['folderId'] ?? 0,
			'columns' => $attributes['columns'] ?? 3,
			'image_size' => $attributes['imageSize'] ?? 'large',
			'lightbox' => $attributes['lightbox'] ?? true,
			'captions' => $attributes['captions'] ?? false,
			'layout' => $attributes['layout'] ?? 'grid',
			'per_page' => $attributes['perPage'] ?? 0,
			'editor' => is_admin(),
		] );
	}

	public static function enqueue_front_assets() {
		wp_enqueue_style( 'swmf-gallery-block' );
		wp_enqueue_script( 'swmf-gallery-block-view' );
	}

}
