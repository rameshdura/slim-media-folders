# Slim Media Folders user guide

## The folder model

Slim Media Folders creates virtual folders in the WordPress database. It never moves an upload on disk, rewrites an attachment URL, or duplicates a file. Each item can belong to one folder for its content type; removing a folder returns its items to **Uncategorized**.

## Everyday workflow

1. Open **Media > Library** and create a folder from the sidebar.
2. Drag files onto a folder, or select a folder to filter the library.
3. Use the folder menu to rename, recolor, lock, create a subfolder, or delete the virtual folder.
4. Use the sort control to arrange folders alphabetically.

Locked folders prevent folder changes for users who do not have administrator access. Posts, pages, and supported custom post types are available as separate content trees in Pro; Free organizes the Media Library.

Role-based permission controls, analytics, unused-media review, white-label branding, ZIP export, folder reordering, and custom post-type folders are Pro features.

## Galleries

Use the **Media Folder Gallery** block in Gutenberg or the matching Elementor widget to render a live gallery from a media folder. Choose the folder and configure columns, image size, captions, lightbox, layout, and pagination in the block or widget settings. The gallery reads the folder at render time, so moving an image updates the gallery without editing the page. Shortcode galleries and the shortcode builder are Pro features.

## Pro analytics, review, and export

Analytics reports original local file sizes, not generated thumbnails. The unused-file review is conservative: “possibly unused” means the plugin did not find a known WordPress reference; it is not proof that a file is safe to remove. Always verify the file before moving it to Trash.

ZIP exports are temporary, per-user archives. They contain up to 50 files from the selected folder, expire after one hour, and require the creating user to remain authorized.

## Troubleshooting

- If a folder is not visible, confirm that the current content type is enabled and that your role has **View folders** permission.
- If a gallery is empty, confirm that the selected folder contains images and that the block/widget is using the intended folder.
- If an export fails, refresh the page and create a new archive. Expired archives are removed automatically.
- If a folder tree looks incorrect after a failed request, reload the screen. The server remains authoritative and rejects invalid parent/child relationships.

## White-label setup

Administrators can set a workspace name, accent color, logo URL, help URL, and light/dark/system theme under **Settings > Theme and white label**. The help URL is opened in a new tab and should point to the support documentation maintained by the site owner.
