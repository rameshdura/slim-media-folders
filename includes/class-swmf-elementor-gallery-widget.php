<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
class SWMF_Elementor_Gallery_Widget extends \Elementor\Widget_Base {
	public function get_name() { return 'swmf-folder-gallery'; }
	public function get_title() { return __( 'Media Folder Gallery', 'slim-media-folders' ); }
	public function get_icon() { return 'eicon-gallery-grid'; }
	public function get_categories() { return [ 'general' ]; }
	public function get_style_depends() { return [ 'swmf-gallery-block' ]; }
	public function get_script_depends() { return [ 'swmf-gallery-block-view' ]; }

	protected function register_controls() {
		$options = [ '' => __( 'Choose a folder', 'slim-media-folders' ) ];
		global $wpdb;
		$counts = $wpdb->get_results( "SELECT pm.meta_value AS folder_id, COUNT(DISTINCT p.ID) AS image_count FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_swmf_folder_id' WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type LIKE 'image/%' GROUP BY pm.meta_value", OBJECT_K );
		$this->add_folders( SWMF_Folder::get_tree(), $options, $counts );
		$this->start_controls_section( 'content', [ 'label' => __( 'Gallery', 'slim-media-folders' ) ] );
		$this->add_control( 'folder_id', [ 'label' => __( 'Media folder', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => $options, 'default' => '' ] );
		$this->add_control( 'columns', [ 'label' => __( 'Columns', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 1, 'max' => 6, 'default' => 3 ] );
		$this->add_control( 'image_size', [ 'label' => __( 'Image size', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => [ 'thumbnail' => __( 'Thumbnail', 'slim-media-folders' ), 'medium' => __( 'Medium', 'slim-media-folders' ), 'large' => __( 'Large', 'slim-media-folders' ), 'full' => __( 'Full size', 'slim-media-folders' ) ], 'default' => 'large' ] );
		$this->add_control( 'lightbox', [ 'label' => __( 'Open images in a lightbox', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'label_on' => __( 'Yes', 'slim-media-folders' ), 'label_off' => __( 'No', 'slim-media-folders' ), 'return_value' => 'yes', 'default' => 'yes' ] );
		$this->add_control( 'captions', [ 'label' => __( 'Show captions', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => '' ] );
		$this->add_control( 'layout', [ 'label' => __( 'Layout', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::SELECT, 'options' => [ 'grid' => __( 'Grid', 'slim-media-folders' ), 'masonry' => __( 'Masonry', 'slim-media-folders' ) ], 'default' => 'grid' ] );
		$this->add_control( 'per_page', [ 'label' => __( 'Images per page (0 for all)', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::NUMBER, 'min' => 0, 'max' => 100, 'default' => 0 ] );
		$this->add_control( 'include_non_images', [ 'label' => __( 'Include non-image files', 'slim-media-folders' ), 'description' => __( 'Off by default. When enabled, PDFs and other files appear as links and are excluded from the image slideshow.', 'slim-media-folders' ), 'type' => \Elementor\Controls_Manager::SWITCHER, 'label_on' => __( 'Yes', 'slim-media-folders' ), 'label_off' => __( 'No', 'slim-media-folders' ), 'return_value' => 'yes', 'default' => '' ] );
		$this->end_controls_section();
	}

	private function add_folders( $folders, &$options, $counts, $depth = 0 ) {
		foreach ( $folders as $folder ) {
			if ( 'attachment' === $folder->object_type ) {
				$count = isset( $counts[ $folder->id ] ) ? (int) $counts[ $folder->id ]->image_count : 0;
				$options[ (string) $folder->id ] = str_repeat( '— ', $depth ) . $folder->name . ' (' . number_format_i18n( $count ) . ')';
				if ( ! empty( $folder->children ) ) { $this->add_folders( $folder->children, $options, $counts, $depth + 1 ); }
			}
		}
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The shared renderer escapes all user-controlled gallery output.
		echo SWMF_Gallery_Renderer::render( [
			'folder_id' => $settings['folder_id'] ?? 0,
			'columns' => $settings['columns'] ?? 3,
			'image_size' => $settings['image_size'] ?? 'large',
			'lightbox' => 'yes' === ( $settings['lightbox'] ?? '' ),
			'captions' => 'yes' === ( $settings['captions'] ?? '' ),
			'layout' => $settings['layout'] ?? 'grid',
			'per_page' => $settings['per_page'] ?? 0,
			'include_non_images' => 'yes' === ( $settings['include_non_images'] ?? '' ),
			'editor' => \Elementor\Plugin::$instance->editor->is_edit_mode(),
		] );
		// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
