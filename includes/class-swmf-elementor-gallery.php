<?php
/** Elementor widget for live Slim Media Folder galleries. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Elementor_Gallery {
	public static function init() {
		add_action( 'elementor/widgets/register', [ __CLASS__, 'register' ] );
	}

	public static function register( $widgets_manager ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) { return; }
		require_once __DIR__ . '/class-swmf-elementor-gallery-widget.php';
		$widgets_manager->register( new SWMF_Elementor_Gallery_Widget() );
	}
}

