=== Slim Media Folders ===
Contributors: rameshdura
Tags: media library, media folders, file manager, gallery, organization
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize your WordPress Media Library with virtual folders. Build galleries from folder contents without moving files or changing their URLs.

== Description ==

Slim Media Folders gives your WordPress Media Library a clear folder structure while leaving the files where WordPress stores them. Folders are virtual, so organizing your library does not rename or move files and does not change their public URLs.

Use nested folders, search, colors, locks, and bulk actions to keep a growing library manageable. Filter media by folder in the Media Library and picker, then display folder images with block-based galleries.

The plugin includes a Gutenberg gallery block and an Elementor gallery widget. Select a media folder in the block or widget settings to display its images with options for layout, captions, image size, pagination, and a lightbox.

= Free features =

* Unlimited virtual folders and nested folders for media.
* Search, color labels, folder locks, drag-and-drop, and bulk actions.
* Folder filtering in the Media Library and WordPress media picker.
* Folder galleries through a Gutenberg block or an Elementor widget.
* Local folder and gallery management using WordPress APIs; media files are not sent to an external service.

= Pro features =

Slim Media Folders Pro includes all Free features, plus:

* Folder organization for posts, pages, and other supported post types.
* Gallery shortcodes and a shortcode builder.
* Manual folder ordering and role-based folder permissions.
* Media storage analytics and unused-media review.
* Temporary folder ZIP exports and white-label settings.

Pro is a separate add-on. Install Free first; your folders and assignments remain in the base plugin. See https://slim-media-folders-site.vercel.app/ for current product details.

== Installation ==

1. In your WordPress dashboard, go to Plugins > Add New Plugin > Upload Plugin.
2. Select the Slim Media Folders ZIP file and choose Install Now.
3. Activate Slim Media Folders.
4. Go to Media > Library to create folders and organize media.
5. Add the Media Folder Gallery block to a post or page and choose a media folder in the block settings.

You can also install the plugin by extracting the ZIP into `/wp-content/plugins/` and activating Slim Media Folders from the Plugins screen.

== Frequently Asked Questions ==

= Does Slim Media Folders move or rename my files? =

No. Folders are virtual. Your files stay in their existing locations, and their public URLs do not change.

= What happens to files when I delete a folder? =

Deleting a folder removes the virtual folder and its child folders. Items assigned to those folders return to Uncategorized. The plugin does not delete the physical media files.

= Can I create a gallery from a folder? =

Yes. Add the Media Folder Gallery block in the block editor or use the Elementor widget. Choose a folder and configure the gallery in the block or widget settings.

= Does the plugin send media or personal data to an external service? =

No. Folder management and gallery rendering run on your WordPress site. The plugin does not transmit media files or personal data to an external service.

= Can an item belong to more than one folder? =

An item belongs to one folder per content type. It can be moved to another folder at any time.

= Where can I find documentation or support? =

The plugin includes a quick-start guide, user guide, privacy information, and support policy in its `docs` directory. Product information and support details are available at https://slim-media-folders-site.vercel.app/.

== Changelog ==

= 1.1.1 =

* Prepared the Free edition for WordPress.org distribution.
* Clarified Free and Pro feature availability in the package documentation.

= 1.1.0 =

* Renamed the product to Slim Media Folders.
* Updated the Free edition and product information.
* Added hierarchy validation and cycle protection.
* Fixed stale and duplicate folder assignments.
* Fixed folder deletion so affected items return to Uncategorized.
* Added upgrade cleanup for orphaned assignments.

= 1.0.0 =

* Initial development release.

== Upgrade Notice ==

= 1.1.1 =

This release prepares the Free edition for WordPress.org and clarifies the Free and Pro feature boundaries.
