(function($, wp) {
	'use strict';

	if (!wp || !wp.media || !window.swmf_media_modal_data) {
		return;
	}

	var data = window.swmf_media_modal_data;
	// The Media Library sidebar owns this value; capture it on each queued file so
	// retries and multiple uploads keep the folder chosen when they were added.
	if ($('body').hasClass('upload-php') && wp.Uploader) {
		var selectedUploadFolder = new URL(window.location.href).searchParams.get('swmf_folder') || 'all';
		window.swmfSetUploadFolder = function(folderId) { selectedUploadFolder = String(folderId || 'all'); };
		var uploadStatus = function(file) {
			var $main = $('.swmf-media-main');
			if (!$main.length) { return $(); }
			var $list = $main.children('.swmf-upload-status');
			if (!$list.length) {
				$list = $('<div class="swmf-upload-status" role="status" aria-live="polite"></div>').prependTo($main);
			}
			var $row = $list.children().filter(function() { return this.dataset.fileId === file.id; });
			if (!$row.length) {
				$row = $('<div class="swmf-upload-status-row"><span class="spinner is-active"></span><span class="swmf-upload-file"></span><span class="swmf-upload-message"></span></div>');
				$row.attr('data-file-id', file.id).appendTo($list);
				$row.find('.swmf-upload-file').text(file.name);
			}
			return $row;
		};
		var showUploadedAttachment = function(folderId, attachmentData) {
			var frame = wp.media && wp.media.frame;
			var content = frame && frame.content && frame.content.get ? frame.content.get() : null;
			var collection = content && content.collection;
			if (selectedUploadFolder === folderId && collection && collection.add && attachmentData && attachmentData.id) {
				// Keep the current thumbnails; resetting a filtered Query clears them.
				var attachment = wp.media.attachment(attachmentData.id);
				attachment.set(attachmentData);
				collection.add(attachment, { at: 0 });
			}
			$(document).trigger('swmf:upload-complete');
		};
		var bindUploadFolder = function(uploader) {
			if (!uploader || uploader.swmfFolderBound) { return; }
			uploader.swmfFolderBound = true;
			uploader.bind('FilesAdded', function(up, files) {
				files.forEach(function(file) {
					file.swmfFolderId = selectedUploadFolder;
					if (/^\d+$/.test(file.swmfFolderId)) {
						uploadStatus(file).find('.swmf-upload-message').text(data.strings.upload_waiting);
					}
				});
			});
			uploader.bind('UploadProgress', function(up, file) {
				if (/^\d+$/.test(file.swmfFolderId || '')) {
					uploadStatus(file).find('.swmf-upload-message').text(data.strings.uploading + ' ' + file.percent + '%');
				}
			});
			uploader.bind('BeforeUpload', function(up, file) {
				var folderId = file.swmfFolderId || selectedUploadFolder;
				up.settings.multipart_params.swmf_upload_folder_id = /^\d+$/.test(folderId) ? folderId : '';
				up.settings.multipart_params.swmf_upload_nonce = data.upload_nonce;
			});
			uploader.bind('FileUploaded', function(up, file, response) {
				if (!/^\d+$/.test(file.swmfFolderId || '')) { return; }
				var result;
				try { result = JSON.parse(response.response); } catch (e) { result = null; }
				var $row = uploadStatus(file);
				$row.find('.spinner').remove();
				if (!result || !result.success) {
					$row.find('.swmf-upload-message').text(data.strings.upload_failed);
					return;
				}
				if (Number(result.data.swmf_folder_id) !== Number(file.swmfFolderId)) {
					$row.find('.swmf-upload-message').text(data.strings.upload_unassigned);
					return;
				}
				var image = result.data && result.data.sizes && result.data.sizes.thumbnail && result.data.sizes.thumbnail.url;
				if (image) { $('<img class="swmf-upload-preview" alt="">').attr('src', image).prependTo($row); }
				$row.find('.swmf-upload-message').text(data.strings.upload_complete);
				showUploadedAttachment(file.swmfFolderId, result.data);
			});
		};
		var bindCurrentUploader = function() {
			var frame = wp.media && wp.media.frame;
			if (frame && frame.uploader && frame.uploader.uploader) {
				bindUploadFolder(frame.uploader.uploader.uploader);
			}
		};
		var originalUploaderInit = wp.Uploader.prototype.init;
		wp.Uploader.prototype.init = function() {
			bindUploadFolder(this.uploader);
			return originalUploaderInit.apply(this, arguments);
		};
		$(bindCurrentUploader);
		$(document).on('uploader:ready', bindCurrentUploader);
		new MutationObserver(bindCurrentUploader).observe(document.body, { childList: true, subtree: true });
	}
	var swmfMedia = {
		folders: null,

		init: function() {
			this.observeMediaModal();
		},

		observeMediaModal: function() {
			var self = this;
			var schedule = function() { window.setTimeout(function() { self.addFolderFilter(); }, 0); };

			new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
			$(document).on('click', '.media-frame-menu, .media-frame-title, .media-modal', schedule);
			schedule();
		},

		addFolderFilter: function() {
			var self = this;
			var $browser = $('.media-modal:visible .attachments-browser').last();
			if (!$browser.length || $browser.find('.swmf-modal-folder-filter').length) {
				return;
			}

			this.getFolders().done(function(folders) {
				var $toolbar = $browser.find('.media-toolbar').first();
				if (!$toolbar.length || $browser.find('.swmf-modal-folder-filter').length) {
					return;
				}

				var $filter = $('<label class="swmf-modal-folder-filter"><span class="swmf-modal-folder-label"></span><select></select></label>');
				$filter.find('.swmf-modal-folder-label').text(data.strings.folder_label);
				var $select = $filter.find('select').attr({
					id: 'swmf-media-folder-filter',
					name: 'swmf_folder'
				});
				$select.append($('<option>', { value: 'all', text: data.strings.all_files }));
				$select.append($('<option>', { value: 'uncategorized', text: data.strings.uncategorized }));
				self.addFolderOptions($select, folders, 0);
				$select.on('change', function() { self.filterAttachments($(this).val()); });
				$toolbar.prepend($filter);
			});
		},

		getFolders: function() {
			var self = this;
			if (this.folders) {
				return $.Deferred().resolve(this.folders).promise();
			}

			return $.ajax({
				url: data.ajax_url,
				type: 'POST',
				data: {
					action: 'swmf_get_folder_tree',
					nonce: data.nonce
				},
			}).then(function(response) {
				self.folders = response && response.success ? response.data.tree : [];
				return self.folders;
			});
		},

		addFolderOptions: function($select, folders, level) {
			folders.forEach(function(folder) {
				$select.append($('<option>', { value: folder.id, text: new Array(level + 1).join('— ') + folder.name }));
				if (folder.children && folder.children.length) {
					swmfMedia.addFolderOptions($select, folder.children, level + 1);
				}
			});
		},

		filterAttachments: function(folderId) {
			var frame = wp.media.frame;
			var content = frame && frame.content && frame.content.get ? frame.content.get() : null;
			var collection = content && content.collection;

			if (!collection || !collection.props) {
				return;
			}

			collection.props.set({ swmf_folder: folderId });
			collection.reset();
			collection.more();
		}
	};

	$(document).ready(function() {
		swmfMedia.init();
	});

})(jQuery, window.wp);
