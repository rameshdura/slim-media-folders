<?php
/** Shared folder gallery renderer for blocks, shortcodes and Elementor. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Gallery_Renderer {
	public static function render( $options = [] ) {
		$options = wp_parse_args( $options, [ 'folder_id' => 0, 'columns' => 3, 'image_size' => 'large', 'lightbox' => true, 'captions' => false, 'layout' => 'grid', 'per_page' => 0, 'include_non_images' => false, 'editor' => false, 'wrapper_attributes' => '' ] );
		$folder_id = absint( $options['folder_id'] );
		$folder = $folder_id ? SWMF_Folder::get_by_id( $folder_id ) : null;
		if ( ! $folder || 'attachment' !== $folder->object_type ) {
			return '<div class="swmf-gallery-notice"><p>' . esc_html__( 'This media folder is no longer available. Choose another folder in the block settings.', 'slim-media-folders' ) . '</p></div>';
		}
		$columns = min( 6, max( 1, absint( $options['columns'] ) ) );
		$layout = in_array( $options['layout'], [ 'grid', 'masonry', 'list', 'slider', 'folders' ], true ) ? $options['layout'] : 'grid';
		if ( 'folders' === $layout ) { return self::render_folder_tree( $folder, $options ); }
		$size = in_array( $options['image_size'], [ 'thumbnail', 'medium', 'large', 'full' ], true ) ? $options['image_size'] : 'large';
		$per_page = min( 100, absint( $options['per_page'] ) );
		$page_key = 'swmf_gallery_' . $folder_id;
		$page = isset( $_GET[ $page_key ] ) ? max( 1, absint( wp_unslash( $_GET[ $page_key ] ) ) ) : 1;
		if ( $options['editor'] ) { $page = 1; }
		$query = new WP_Query( [
			'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => $per_page ?: -1,
			'paged' => $per_page ? $page : 1, 'fields' => 'ids', 'orderby' => 'menu_order ID', 'order' => 'ASC',
			'meta_key' => '_swmf_folder_id', 'meta_value' => $folder_id,
			'post_mime_type' => $options['include_non_images'] ? '' : 'image',
			'no_found_rows' => ! $per_page,
		] );
		if ( ! $query->posts ) {
			return '<div class="swmf-gallery-notice"><p>' . esc_html__( 'This folder does not have any images yet. Add images to it in the Media Library and they will appear here automatically.', 'slim-media-folders' ) . '</p></div>';
		}
		$classes = 'swmf-gallery swmf-gallery--columns-' . $columns . ' swmf-gallery--' . $layout;
		$output = '<div class="' . esc_attr( $classes ) . '"'  . ( $options['wrapper_attributes'] ? ' ' . $options['wrapper_attributes'] : '' ) . '>';
		foreach ( $query->posts as $id ) {
			$url = wp_get_attachment_url( $id );
			if ( ! $url ) { continue; }
			if ( wp_attachment_is_image( $id ) ) {
				$image = wp_get_attachment_image( $id, $size, false, [ 'loading' => 'lazy' ] );
				if ( ! $image ) { continue; }
				$output .= '<figure class="swmf-gallery__item">';
				$output .= $options['lightbox'] && ! $options['editor'] ? '<a class="swmf-gallery__link" href="' . esc_url( $url ) . '">' . $image . '</a>' : $image;
				$caption = wp_get_attachment_caption( $id );
				if ( $options['captions'] && $caption ) { $output .= '<figcaption class="swmf-gallery__caption">' . esc_html( $caption ) . '</figcaption>'; }
				$output .= '</figure>';
			} elseif ( $options['include_non_images'] ) {
				$title = get_the_title( $id ) ?: wp_basename( get_attached_file( $id ) );
				$output .= '<figure class="swmf-gallery__item swmf-gallery__item--file"><a class="swmf-gallery__file" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"><span class="swmf-gallery__file-icon" aria-hidden="true">📄</span><span>' . esc_html( $title ) . '</span></a></figure>';
			}
		}
		$output .= '</div>';
		if ( 'slider' === $layout && count( $query->posts ) > 1 ) {
			$output = '<div class="swmf-gallery-slider"><button type="button" class="swmf-gallery-slider__previous" aria-label="' . esc_attr__( 'Previous slide', 'slim-media-folders' ) . '">‹</button>' . $output . '<button type="button" class="swmf-gallery-slider__next" aria-label="' . esc_attr__( 'Next slide', 'slim-media-folders' ) . '">›</button></div>';
		}
		if ( $per_page && $query->max_num_pages > 1 && ! $options['editor'] ) {
			$output .= '<nav class="swmf-gallery-pagination" aria-label="' . esc_attr__( 'Gallery pages', 'slim-media-folders' ) . '">';
			for ( $number = 1; $number <= $query->max_num_pages; $number++ ) {
				if ( $number === $page ) { $output .= '<span class="swmf-gallery-pagination__current" aria-current="page">' . esc_html( $number ) . '</span>'; }
				else { $output .= '<a href="' . esc_url( add_query_arg( $page_key, $number ) ) . '">' . esc_html( $number ) . '</a>'; }
			}
			$output .= '</nav>';
		}
		return $output;
	}
	private static function render_folder_tree( $folder, $options, $depth = 0 ) {
		if ( $depth > 20 ) { return ''; }
		$children = SWMF_Folder::get_all( [ 'parent_id' => $folder->id, 'object_type' => 'attachment' ] );
		$child_options = $options;
		$child_options['folder_id'] = $folder->id;
		$child_options['layout'] = 'list';
		$child_options['per_page'] = 0;
		$own_items = self::render( $child_options );
		if ( false !== strpos( $own_items, 'swmf-gallery-notice' ) ) { $own_items = ''; }
		$output = '<section class="swmf-gallery-folder"><h' . ( $depth ? '4' : '3' ) . '>' . esc_html( $folder->name ) . '</h' . ( $depth ? '4' : '3' ) . '>' . $own_items;
		foreach ( $children as $child ) { $output .= self::render_folder_tree( $child, $options, $depth + 1 ); }
		return $output . '</section>';
	}

}
