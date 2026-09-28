<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SWMF_Activator {
	public static function activate() {
		SWMF_Database::create_tables();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
