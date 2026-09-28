<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Runtime product configuration for Slim Media Folders.
 *
 * Keep user-facing URLs and product labels here rather than scattering them
 * through admin screens. Plugin headers and readme metadata remain static by
 * design because WordPress reads those before plugin PHP is executed.
 */

return [
	'name'        => 'Slim Media Folders',
	'pro_name'    => 'Slim Media Folders Pro',
	'website_url' => 'https://slim-media-folders-site.vercel.app/',
	'upgrade_url' => 'https://slim-media-folders-site.vercel.app/#pro',
	'support_url' => 'https://slim-media-folders-site.vercel.app/#faq',
	'docs_url'    => 'https://slim-media-folders-site.vercel.app/#how-it-works',
	'author'      => 'Ramesh Dura',
];
