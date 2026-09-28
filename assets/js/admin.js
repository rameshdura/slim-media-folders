(function($) {
	'use strict';

	var swmf = {
		selectedFolders: new Set(),
		isSelectionMode: false,

		init: function() {
			this.bindEvents();
			if ($('#swmf-tree').length) {
				this.loadFolders(0);
			}
		},

		bindEvents: function() {
			var self = this;

			// Create root folder
			$('#swmf-create-root-folder, #swmf-create-first-folder').on('click', function() {
				self.openModal(0);
			});

			$('#swmf-object-type').on('change', function() {
				if ($('#swmf-tree').length) {
					self.loadFolders(0);
				}
			});

			$('#swmf-folder-search').on('input', function() {
				var term = $(this).val().toLowerCase();
				$('#swmf-tree .swmf-folder-item').each(function() {
					var $item = $(this);
					$item.toggle($item.find('.swmf-folder-name').first().text().toLowerCase().indexOf(term) !== -1);
				});
			});

			$('.swmf-list-folder-shortcut').on('click', function() {
				self.filterListByFolder($(this).data('folder-id'));
			});

			// Modal events
			$('#swmf-modal-cancel, .swmf-modal-overlay').on('click', function() {
				self.closeModal();
			});

			$('#swmf-folder-form').on('submit', function(e) {
				e.preventDefault();
				self.saveFolder();
			});

			// Folder row events (delegated)
			$('#swmf-tree').on('click', '.swmf-folder-expand', function(e) {
				e.stopPropagation();
				self.toggleFolder($(this).closest('.swmf-folder-item'));
			});

			$('#swmf-tree').on('click', '.swmf-folder-checkbox', function(e) {
				e.stopPropagation();
				var $item = $(this).closest('.swmf-folder-item');
				var id = $item.data('id');
				self.toggleSelection(id, $item);
			});

			$('#swmf-tree').on('click', '.swmf-folder-row', function(e) {
				if (!self.isSelectionMode && !$(e.target).closest('.swmf-folder-actions, .swmf-folder-expand, .swmf-folder-checkbox').length) {
					var $item = $(this).closest('.swmf-folder-item');
					var id = $item.data('id');
					if ($(this).closest('.swmf-list-folder-manager').length) {
						self.filterListByFolder(id);
					} else {
						self.openModal(id);
					}
				}
			});

			// Folder actions
			$('#swmf-tree').on('click', '.swmf-folder-edit', function(e) {
				e.stopPropagation();
				var $item = $(this).closest('.swmf-folder-item');
				var id = $item.data('id');
				self.openModal(id);
			});

			$('#swmf-tree').on('click', '.swmf-folder-delete', function(e) {
				e.stopPropagation();
				var $item = $(this).closest('.swmf-folder-item');
				var id = $item.data('id');
				var name = $item.find('.swmf-folder-name').text();
				if (confirm(swmf_data.strings.confirm_delete.replace('%s', name))) {
					self.deleteFolder(id, $item);
				}
			});

			$('#swmf-tree').on('click', '.swmf-folder-add-child', function(e) {
				e.stopPropagation();
				var $item = $(this).closest('.swmf-folder-item');
				var id = $item.data('id');
				self.openModal(id);
			});

			// Bulk actions
			$('#swmf-bulk-apply').on('click', function() {
				self.bulkUpdate();
			});

			$('#swmf-bulk-delete').on('click', function() {
				if (confirm(swmf_data.strings.confirm_bulk_delete)) {
					self.bulkDelete();
				}
			});

			$('#swmf-cancel-selection').on('click', function() {
				self.clearSelection();
			});
				if (confirm(swmf_data.strings.confirm_delete_zip)) {
					self.deleteZip();
				}
			});

			// Keyboard shortcuts
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					self.closeModal();
					if (self.isSelectionMode) {
						self.clearSelection();
					}
				}
			});
		},

		filterListByFolder: function(folderId) {
			var url = new URL(window.location.href);
			if (folderId === '' || typeof folderId === 'undefined') url.searchParams.delete('swmf_folder_id');
			else url.searchParams.set('swmf_folder_id', folderId);
			window.location.href = url.toString();
		},

		loadFolders: function(parentId, $container) {
			var self = this;
			var $target = $container || $('#swmf-tree');
			var isRoot = !$container;

			if (isRoot) {
				$('#swmf-loading').show();
				$('#swmf-tree, #swmf-empty').hide();
			}

			$.ajax({
				url: swmf_data.ajax_url,
				type: 'POST',
				data: {
					action: 'swmf_get_folders',
					parent_id: parentId,
					object_type: $('#swmf-object-type').val() || 'attachment',
					nonce: swmf_data.nonce
				},
				success: function(response) {
					if (response.success) {
						if (isRoot) {
							$('#swmf-loading').hide();
							if (response.data.length === 0) {
								$('#swmf-empty').show();
								$('#swmf-tree').hide();
							} else {
								$('#swmf-tree').show().empty();
								self.renderFolders(response.data, $('#swmf-tree'));
								$('#swmf-empty').hide();
							}
						} else {
							self.renderFolders(response.data, $target);
							$target.prev('.swmf-folder-expand').removeClass('loading');
						}
					} else {
						self.showError(response.data);
					}
				},
				error: function() {
					self.showError(swmf_data.strings.error);
				}
			});
		},

			renderFolders: function(folders, $container) {
			var self = this;
			var html = '';

			folders.forEach(function(folder) {
				var hasChildren = !!folder.has_children;
				html += self.renderFolderRow(folder, hasChildren);
			});

			$container.append(html);
		},

		renderFolderRow: function(folder, hasChildren) {
			var lockedClass = folder.is_locked ? ' locked' : '';
			var lockIcon = folder.is_locked ? 'dashicons-lock' : 'dashicons-unlock';
			var expandIcon = hasChildren ? 'dashicons-arrow-right-alt2' : '';
			var expandClass = hasChildren ? '' : ' no-children';

			return '<div class="swmf-folder-item" data-id="' + folder.id + '" data-parent="' + folder.parent_id + '">' +
				'<div class="swmf-folder-row' + lockedClass + '">' +
					'<div class="swmf-folder-expand' + expandClass + '" data-expanded="false">' +
						'<span class="dashicons ' + expandIcon + '"></span>' +
					'</div>' +
					'<input type="checkbox" class="swmf-folder-checkbox" style="display: none;">' +
					'<div class="swmf-folder-color-indicator" style="background-color: ' + folder.color + ';"></div>' +
					'<span class="swmf-folder-name" title="' + folder.name + '">' + folder.name + '</span>' +
					'<span class="swmf-folder-lock"><span class="dashicons ' + lockIcon + '"></span></span>' +
					'<div class="swmf-folder-actions">' +
						'<button type="button" class="swmf-folder-add-child" title="' + swmf_data.strings.create_folder + '"><span class="dashicons dashicons-plus"></span></button>' +
						'<button type="button" class="swmf-folder-edit" title="' + swmf_data.strings.edit_folder + '"><span class="dashicons dashicons-edit"></span></button>' +
						'<button type="button" class="swmf-folder-delete" title="' + swmf_data.strings.delete_folder + '"><span class="dashicons dashicons-trash"></span></button>' +
					'</div>' +
				'</div>' +
				'<div class="swmf-folder-children collapsed"></div>' +
			'</div>';
		},

		toggleFolder: function($item) {
			var self = this;
			var id = $item.data('id');
			var $expand = $item.find('.swmf-folder-expand');
			var $children = $item.find('.swmf-folder-children');
			var isExpanded = $expand.attr('data-expanded') === 'true';

			if (isExpanded) {
				$expand.attr('data-expanded', 'false');
				$expand.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
				$children.addClass('collapsed').slideUp(200);
			} else {
				if ($children.children().length === 0) {
					$expand.addClass('loading');
					$expand.find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-update spin');
					self.loadFolders(id, $children);
				} else {
					$expand.attr('data-expanded', 'true');
					$expand.find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
					$children.removeClass('collapsed').slideDown(200);
				}
			}
		},

		openModal: function(folderId, parentId) {
			var self = this;
			var isEdit = folderId > 0;

			$('#swmf-modal-title').text(isEdit ? swmf_data.strings.edit_folder : swmf_data.strings.create_folder);
			$('#swmf-folder-id').val(folderId);
			$('#swmf-folder-parent').val(parentId || 0);
			$('#swmf-folder-name').val('');
			$('#swmf-folder-color').val('#3498db');
			$('#swmf-folder-locked').prop('checked', false);

			if (isEdit) {
				var $item = $('.swmf-folder-item[data-id="' + folderId + '"]');
				var name = $item.find('.swmf-folder-name').text();
				var color = $item.find('.swmf-folder-color-indicator').css('background-color');
				var locked = $item.find('.swmf-folder-lock').hasClass('locked');

				$('#swmf-folder-name').val(name);
				$('#swmf-folder-color').val(self.rgbToHex(color));
				$('#swmf-folder-locked').prop('checked', locked);
			}

			$('#swmf-folder-modal').show();
			$('#swmf-folder-name').focus();
		},

		closeModal: function() {
			$('#swmf-folder-modal').hide();
			$('#swmf-folder-form')[0].reset();
		},

		saveFolder: function() {
			var self = this;
			var id = parseInt($('#swmf-folder-id').val(), 10);
			var isEdit = id > 0;

			var data = {
				action: isEdit ? 'swmf_update_folder' : 'swmf_create_folder',
				nonce: swmf_data.nonce,
				name: $('#swmf-folder-name').val(),
				parent_id: parseInt($('#swmf-folder-parent').val(), 10),
				object_type: $('#swmf-object-type').val() || 'attachment',
				color: $('#swmf-folder-color').val(),
				is_locked: $('#swmf-folder-locked').is(':checked') ? 1 : 0
			};

			if (isEdit) {
				data.id = id;
			}

			$.ajax({
				url: swmf_data.ajax_url,
				type: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						self.closeModal();
						if (isEdit) {
							self.updateFolderInTree(response.data);
						} else {
							var parentId = data.parent_id;
							if (parentId === 0) {
								$('#swmf-empty').hide();
								$('#swmf-tree').show();
								var $newRow = $(self.renderFolderRow(response.data, false));
								$('#swmf-tree').prepend($newRow);
							} else {
								var $parent = $('.swmf-folder-item[data-id="' + parentId + '"]');
								var $children = $parent.find('.swmf-folder-children');
								$parent.find('.swmf-folder-expand').removeClass('no-children').find('.dashicons').removeClass('dashicons-arrow-right-alt2').addClass('dashicons-arrow-down-alt2');
								$children.removeClass('collapsed');
								var $newRow = $(self.renderFolderRow(response.data, false));
								$children.prepend($newRow);
							}
						}
					} else {
						self.showError(response.data);
					}
				},
				error: function() {
					self.showError(swmf_data.strings.error);
				}
			});
		},

		updateFolderInTree: function(folder) {
			var $item = $('.swmf-folder-item[data-id="' + folder.id + '"]');
			$item.find('.swmf-folder-name').text(folder.name).attr('title', folder.name);
			$item.find('.swmf-folder-color-indicator').css('background-color', folder.color);
			
			var $lock = $item.find('.swmf-folder-lock');
			if (folder.is_locked) {
				$item.find('.swmf-folder-row').addClass('locked');
				$lock.addClass('locked').find('.dashicons').removeClass('dashicons-unlock').addClass('dashicons-lock');
			} else {
				$item.find('.swmf-folder-row').removeClass('locked');
				$lock.removeClass('locked').find('.dashicons').removeClass('dashicons-lock').addClass('dashicons-unlock');
			}
		},

		deleteFolder: function(id, $item) {
			var self = this;

			$.ajax({
				url: swmf_data.ajax_url,
				type: 'POST',
				data: {
					action: 'swmf_delete_folder',
					id: id,
					nonce: swmf_data.nonce
				},
				success: function(response) {
					if (response.success) {
						var $parent = $item.parent('.swmf-folder-children');
						$item.slideUp(200, function() {
							$item.remove();
							if ($parent.children().length === 0) {
								$parent.addClass('collapsed').hide();
								$parent.siblings('.swmf-folder-expand').addClass('no-children').attr('data-expanded', 'false')
									.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
							}
						});

						if ($('#swmf-tree .swmf-folder-item').length === 0) {
							$('#swmf-tree').hide();
							$('#swmf-empty').show();
						}
					} else {
						self.showError(response.data);
					}
				},
				error: function() {
					self.showError(swmf_data.strings.error);
				}
			});
		},

		toggleSelection: function(id, $item) {
			var self = this;

			if (self.selectedFolders.has(id)) {
				self.selectedFolders.delete(id);
				$item.find('.swmf-folder-row').removeClass('selected');
			} else {
				self.selectedFolders.add(id);
				$item.find('.swmf-folder-row').addClass('selected');
			}

			self.updateSelectionUI();
		},

		updateSelectionUI: function() {
			var count = this.selectedFolders.size;

			if (count > 0) {
				this.isSelectionMode = true;
				$('#swmf-bulk-actions').show();
				$('#swmf-create-root-folder').hide();
				$('#swmf-tree .swmf-folder-checkbox').show();
			} else {
				this.clearSelection();
			}
		},

		clearSelection: function() {
			this.selectedFolders.clear();
			this.isSelectionMode = false;
			$('#swmf-bulk-actions').hide();
			$('#swmf-create-root-folder').show();
			$('#swmf-tree .swmf-folder-row').removeClass('selected');
			$('#swmf-tree .swmf-folder-checkbox').hide().prop('checked', false);
		},

		bulkUpdate: function() {
			var self = this;
			var ids = Array.from(this.selectedFolders);
			var color = $('#swmf-bulk-color').val();
			var isLocked = $('#swmf-bulk-lock').is(':checked') ? 1 : 0;

			if (ids.length === 0) return;

			$.ajax({
				url: swmf_data.ajax_url,
				type: 'POST',
				data: {
					action: 'swmf_bulk_update_folders',
					ids: ids,
					color: color,
					is_locked: isLocked,
					nonce: swmf_data.nonce
				},
				success: function(response) {
					if (response.success) {
						ids.forEach(function(id) {
							var $item = $('.swmf-folder-item[data-id="' + id + '"]');
							$item.find('.swmf-folder-color-indicator').css('background-color', color);
							var $lock = $item.find('.swmf-folder-lock');
							if (isLocked) {
								$item.find('.swmf-folder-row').addClass('locked');
								$lock.addClass('locked').find('.dashicons').removeClass('dashicons-unlock').addClass('dashicons-lock');
							} else {
								$item.find('.swmf-folder-row').removeClass('locked');
								$lock.removeClass('locked').find('.dashicons').removeClass('dashicons-lock').addClass('dashicons-unlock');
							}
						});
						self.clearSelection();
					} else {
						self.showError(response.data);
					}
				},
				error: function() {
					self.showError(swmf_data.strings.error);
				}
			});
		},

		bulkDelete: function() {
			var self = this;
			var ids = Array.from(this.selectedFolders);

			if (ids.length === 0) return;

			$.ajax({
				url: swmf_data.ajax_url,
				type: 'POST',
				data: {
					action: 'swmf_bulk_delete_folders',
					ids: ids,
					nonce: swmf_data.nonce
				},
				success: function(response) {
					if (response.success) {
						ids.forEach(function(id) {
							var $item = $('.swmf-folder-item[data-id="' + id + '"]');
							var $parent = $item.parent('.swmf-folder-children');
							$item.slideUp(200, function() {
								$item.remove();
								if ($parent.children().length === 0) {
									$parent.addClass('collapsed').hide();
									$parent.siblings('.swmf-folder-expand').addClass('no-children').attr('data-expanded', 'false')
										.find('.dashicons').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-right-alt2');
								}
							});
						});

						if ($('#swmf-tree .swmf-folder-item').length === 0) {
							$('#swmf-tree').hide();
							$('#swmf-empty').show();
						}

						self.clearSelection();
					} else {
						self.showError(response.data);
					}
				},
				error: function() {
					self.showError(swmf_data.strings.error);
				}
			});
		},

		rgbToHex: function(rgb) {
			if (rgb.indexOf('#') === 0) return rgb;
			var parts = rgb.match(/\d+/g);
			if (!parts) return '#3498db';
			return '#' + parts.slice(0, 3).map(function(x) {
				return parseInt(x, 10).toString(16).padStart(2, '0');
			}).join('');
		},

		showError: function(message) {
			alert(message);
		}
	};

	$(document).ready(function() {
		var $listManager = $('.swmf-list-folder-manager');
		if ($listManager.length) {
			var $listForm = $('#posts-filter').first();
			if ($listForm.length) {
				var $layout = $('<div class="swmf-list-layout"></div>');
				$listForm.wrap($layout);
				$listForm.before($listManager);
				$listManager.show();
			}
		}
		swmf.init();
	});

})(jQuery);
