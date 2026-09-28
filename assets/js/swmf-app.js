/**
 * Slim WordPress Media Folders - Modern React/wp.element FileBird-style sidebar
 */
(function($, wp) {
	'use strict';

	if (!wp || !wp.element) {
		console.warn('wp.element is required for SWMF Sidebar');
		return;
	}

	var createElement = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useMemo = wp.element.useMemo;
	var useRef = wp.element.useRef;

	// Icons
	var FolderIcon = function() {
		return createElement('svg', {
			viewBox: '0 0 24 24',
			className: 'swmf-folder-icon'
		}, createElement('path', {
			d: 'M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z'
		}));
	};

	var ChevronIcon = function(props) {
		return createElement('svg', {
			viewBox: '0 0 24 24',
			width: 14,
			height: 14,
			fill: 'currentColor'
		}, createElement('path', {
			d: 'M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z'
		}));
	};

	var ChevronLeft = function() {
		return createElement('svg', {
			viewBox: '0 0 24 24'
		}, createElement('path', {
			d: 'M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z'
		}));
	};

	var SuccessCheckIcon = function() {
		return createElement('svg', {
			viewBox: '0 0 24 24',
			width: 16,
			height: 16,
			className: 'swmf-toast-icon swmf-toast-icon-success',
			'aria-hidden': 'true'
		}, [
			createElement('circle', { key: 'circle', cx: 12, cy: 12, r: 10 }),
			createElement('path', { key: 'check', d: 'M7.5 12.5l3 3 6-6', className: 'swmf-toast-icon-mark' })
		]);
	};

	var ErrorCircleIcon = function() {
		return createElement('svg', {
			viewBox: '0 0 24 24',
			width: 16,
			height: 16,
			className: 'swmf-toast-icon swmf-toast-icon-error',
			'aria-hidden': 'true'
		}, [
			createElement('circle', { key: 'circle', cx: 12, cy: 12, r: 10 }),
			createElement('path', { key: 'mark', d: 'M12 7v6', className: 'swmf-toast-icon-mark' }),
			createElement('circle', { key: 'dot', cx: 12, cy: 16.5, r: 1, className: 'swmf-toast-icon-mark' })
		]);
	};

	var MoreIcon = function() {
		return createElement('svg', {
			viewBox: '0 0 24 24',
			width: 14,
			height: 14,
			fill: 'currentColor'
		}, createElement('path', {
			d: 'M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z'
		}));
	};

	var PencilIcon = function() {
		return createElement('svg', {
			viewBox: '0 0 24 24', width: 13, height: 13, fill: 'none', stroke: 'currentColor',
			strokeWidth: 2, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true
		}, [
			createElement('path', { key: 'line', d: 'M12 20h9' }),
			createElement('path', { key: 'pen', d: 'M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z' })
		]);
	};

	var SWMFSidebarApp = function() {
		var objectType = (window.swmf_data && window.swmf_data.object_type) || 'attachment';
		var permissions = (window.swmf_data && window.swmf_data.permissions) || {};
		var folderSortStorageKey = 'swmf_folder_sort_' + objectType;
		var [collapsed, setCollapsed] = useState(function() {
			return localStorage.getItem('swmf_collapsed') === 'true';
		});
		var [tree, setTree] = useState([]);
		var [counts, setCounts] = useState({ total: 0, uncategorized: 0, folders: {} });
		var [currentFolder, setCurrentFolder] = useState(function() {
			var selected = new URL(window.location.href).searchParams.get((window.swmf_data && window.swmf_data.folder_query_arg) || 'swmf_folder');
			return selected || 'all';
		});
		var [searchQuery, setSearchQuery] = useState('');
		var [expandedFolders, setExpandedFolders] = useState({});
		var [visibleFolderCounts, setVisibleFolderCounts] = useState({});
		var [activeMenuId, setActiveMenuId] = useState(null);
		var [menuOpensUp, setMenuOpensUp] = useState(false);
		var [dragOverFolderId, setDragOverFolderId] = useState(null);
		var [isLoading, setIsLoading] = useState(false);
		var [moveProgress, setMoveProgress] = useState(null);
		var [isSelectingFolders, setIsSelectingFolders] = useState(false);
		var [selectedFolderIds, setSelectedFolderIds] = useState({});
		var [folderSort, setFolderSort] = useState(function() {
			var savedSort = localStorage.getItem(folderSortStorageKey);
			return ['name-asc', 'name-desc'].indexOf(savedSort) !== -1 || (permissions.reorder && savedSort === 'custom') ? savedSort : 'name-asc';
		});
		var [draggedFolderId, setDraggedFolderId] = useState(null);
		var [sortDragOverFolderId, setSortDragOverFolderId] = useState(null);

		// Modal state
		var [modalOpen, setModalOpen] = useState(false);
		var [modalMode, setModalMode] = useState('create'); // 'create', 'create_sub', 'edit'
		var [modalFolderData, setModalFolderData] = useState({ id: 0, name: '', color: '#3498db', parent_id: 0 });

		var strings = (window.swmf_data && window.swmf_data.strings) || {};
		var ajaxUrl = (window.swmf_data && window.swmf_data.ajax_url) || ajaxurl;
		var nonce = (window.swmf_data && window.swmf_data.nonce) || '';
		var folderQueryArg = (window.swmf_data && window.swmf_data.folder_query_arg) || 'swmf_folder';
		var listRequest = useRef(null);
		var refreshListTable = function(url) {
			if (listRequest.current) listRequest.current.abort();
			var $form = $('#posts-filter');
			if (!$form.length) return;
			$form.addClass('swmf-list-loading').attr('aria-busy', 'true');
			var request = $.get(url.toString());
			listRequest.current = request;
			request.done(function(html) {
				var doc = new DOMParser().parseFromString(html, 'text/html');
				var nextForm = doc.querySelector('#posts-filter');
				var currentForm = document.querySelector('#posts-filter');
				if (!nextForm || !currentForm || !nextForm.querySelector('.wp-list-table')) {
					window.location.href = url.toString();
					return;
				}
				// Keep the original form and its WordPress handlers; swap only rendered results.
				['.wp-list-table', '.tablenav.top', '.tablenav.bottom'].forEach(function(selector) {
					var oldNode = currentForm.querySelector(selector);
					var newNode = nextForm.querySelector(selector);
					if (oldNode && newNode) oldNode.replaceWith(newNode);
				});
				var oldViews = document.querySelector('.wrap .subsubsub');
				var newViews = doc.querySelector('.wrap .subsubsub');
				if (oldViews && newViews) oldViews.replaceWith(newViews);
			}).fail(function(xhr, status) {
				if (status !== 'abort') window.location.href = url.toString();
			}).always(function() {
				if (listRequest.current !== request) return;
				$form.removeClass('swmf-list-loading').removeAttr('aria-busy');
				listRequest.current = null;
			});
		};
		useEffect(function() {
			var onPopState = function() {
				var selected = new URL(window.location.href).searchParams.get(folderQueryArg) || 'all';
				setCurrentFolder(selected);
				if ($('#posts-filter .wp-list-table').length) refreshListTable(new URL(window.location.href));
			};
			window.addEventListener('popstate', onPopState);
			return function() { window.removeEventListener('popstate', onPopState); };
		}, []);

		// Load tree data
		var fetchTree = function() {
			setIsLoading(true);
			return $.post(ajaxUrl, {
				action: 'swmf_get_folder_tree',
				nonce: nonce,
				object_type: objectType
			}, function(res) {
				if (res && res.success) {
					setTree(res.data.tree || []);
					if (res.data.counts) {
						setCounts(res.data.counts);
					}
				}
			}).always(function() {
				setIsLoading(false);
			});
		};

		var sortTree = function(nodes) {
			if (folderSort === 'custom') {
				return nodes.map(function(node) {
					return Object.assign({}, node, { children: node.children ? sortTree(node.children) : [] });
				});
			}
			var direction = folderSort === 'name-desc' ? -1 : 1;
			return nodes.slice().sort(function(a, b) {
				return a.name.localeCompare(b.name) * direction;
			}).map(function(node) {
				return Object.assign({}, node, { children: node.children ? sortTree(node.children) : [] });
			});
		};
		var sortedTree = useMemo(function() { return sortTree(tree); }, [tree, folderSort]);

		var removeFolderFromTree = function(nodes, folderId) {
			return nodes.filter(function(node) {
				return node.id != folderId;
			}).map(function(node) {
				return Object.assign({}, node, { children: removeFolderFromTree(node.children || [], folderId) });
			});
		};

		var folderContains = function(node, folderId) {
			if (Number(node.id) === Number(folderId)) return true;
			return (node.children || []).some(function(child) { return folderContains(child, folderId); });
		};

		var findFolder = function(nodes, folderId) {
			for (var i = 0; i < nodes.length; i++) {
				if (Number(nodes[i].id) === Number(folderId)) return nodes[i];
				var child = findFolder(nodes[i].children || [], folderId);
				if (child) return child;
			}
			return null;
		};

		var parentOptions = function(nodes, depth, movingFolder) {
			return nodes.reduce(function(options, node) {
				if (movingFolder && folderContains(movingFolder, node.id)) return options;
				options.push(createElement('option', { key: node.id, value: node.id }, Array(depth + 1).join('— ') + node.name));
				return options.concat(parentOptions(node.children || [], depth + 1, movingFolder));
			}, []);
		};

		var moveFolder = function(folderId, parentId) {
			var folder = findFolder(tree, folderId);
			if (!folder || Number(folder.parent_id) === Number(parentId)) return;
			if (folderContains(folder, parentId)) {
				showToast('A folder cannot be moved into itself or one of its subfolders.', 'error');
				return;
			}
			$.post(ajaxUrl, { action: 'swmf_update_folder', nonce: nonce, id: folderId, parent_id: parentId }, function(res) {
				if (res && res.success) {
					setExpandedFolders(function(previous) { return Object.assign({}, previous, { [parentId]: true }); });
					fetchTree();
					showToast('Folder moved.', 'success');
				} else {
					showToast((res && res.data) || 'Unable to move folder.', 'error');
				}
			}).fail(function() { showToast('Unable to move folder. Please try again.', 'error'); });
		};

		var toggleFolderSelection = function(folderId) {
			setSelectedFolderIds(function(previous) {
				var next = Object.assign({}, previous);
				if (next[folderId]) delete next[folderId];
				else next[folderId] = true;
				return next;
			});
		};

		useEffect(function() {
			fetchTree();
			var handleUploadComplete = function() { fetchTree(); };
			$(document).on('swmf:upload-complete', handleUploadComplete);

			// Close context menu on outside click
			var handleOutside = function(e) {
				if (!$(e.target).closest('.swmf-folder-actions-btn, .swmf-context-menu').length) {
					setActiveMenuId(null);
				}
			};
			document.addEventListener('click', handleOutside);
			return function() {
				$(document).off('swmf:upload-complete', handleUploadComplete);
				document.removeEventListener('click', handleOutside);
			};
		}, []);

		// Toasts must survive a folder-tree refresh; only clear their timers on unmount.
		useEffect(function() {
			return function() {
				if (toastTimeoutRef.current) clearTimeout(toastTimeoutRef.current);
				if (toastFadeTimeoutRef.current) clearTimeout(toastFadeTimeoutRef.current);
			};
		}, []);

		// Toggle sidebar collapse
		var toggleCollapse = function() {
			var next = !collapsed;
			setCollapsed(next);
			localStorage.setItem('swmf_collapsed', next ? 'true' : 'false');
		};

		// Filter media grid
		var selectFolder = function(folderId) {
			setCurrentFolder(folderId);
			if (objectType === 'attachment' && window.swmfSetUploadFolder) {
				window.swmfSetUploadFolder(folderId);
			}

			// Grid view (wp.media)
			if (wp.media && wp.media.frame) {
				var collection = wp.media.frame.content.get().collection;
				if (collection && collection.props) {
					collection.props.set({ swmf_folder: folderId });
					collection.props.trigger('change');
				}
			}

			// Backbone Query Collection directly
			if (wp.media && wp.media.model && wp.media.model.Query && wp.media.model.Query.defaultProps) {
				wp.media.model.Query.defaultProps.swmf_folder = folderId;
			}

			// If on list view (table) or page URL query
			var url = new URL(window.location.href);
			if (url.searchParams.has(folderQueryArg) || folderId !== 'all') {
				if (folderId === 'all') {
					url.searchParams.delete(folderQueryArg);
				} else {
					url.searchParams.set(folderQueryArg, folderId);
				}
				if ($('#posts-filter .wp-list-table').length) {
					url.searchParams.delete('paged');
					window.history.pushState({ swmfFolder: folderId }, '', url.toString());
					refreshListTable(url);
				}
			}
		};

		// Expand/collapse folder subtree
		var toggleExpand = function(id, e) {
			e.stopPropagation();
			setExpandedFolders(function(prev) {
				var next = Object.assign({}, prev);
				next[id] = !next[id];
				return next;
			});
		};

		// Toast Notification state
		var [toast, setToast] = useState(null);
		var toastTimeoutRef = useRef(null);
		var toastFadeTimeoutRef = useRef(null);

		var showToast = function(message, type) {
			type = type || 'success';
			if (toastTimeoutRef.current) {
				clearTimeout(toastTimeoutRef.current);
			}
			if (toastFadeTimeoutRef.current) {
				clearTimeout(toastFadeTimeoutRef.current);
			}
			setToast({ message: message, type: type, isLeaving: false });
			toastTimeoutRef.current = setTimeout(function() {
				setToast(function(current) {
					return current ? Object.assign({}, current, { isLeaving: true }) : null;
				});
				toastFadeTimeoutRef.current = setTimeout(function() {
					setToast(null);
				}, 250);
			}, 1600);
		};

		var getSelectedFolderIds = function() {
			return Object.keys(selectedFolderIds).filter(function(id) { return selectedFolderIds[id]; }).map(Number);
		};

		var updateSelectedFolders = function(data, successMessage) {
			var ids = getSelectedFolderIds();
			if (!ids.length) {
				showToast('Select at least one folder first.', 'error');
				return;
			}
			setIsLoading(true);
			$.post(ajaxUrl, Object.assign({ action: 'swmf_bulk_update_folders', nonce: nonce, ids: ids, object_type: objectType }, data), function(res) {
				if (res && res.success) {
					fetchTree();
					showToast(successMessage, 'success');
				} else {
				showToast((res && res.data) || strings.update_failed || 'Unable to update selected folders.', 'error');
				}
			}).fail(function() {
				showToast(strings.update_retry || 'Unable to update selected folders. Please try again.', 'error');
			}).always(function() { setIsLoading(false); });
		};

		var deleteSelectedFolders = function() {
			var ids = getSelectedFolderIds();
			if (!ids.length) {
				showToast(strings.select_folder_first || 'Select at least one folder first.', 'error');
				return;
			}
			if (!confirm((strings.confirm_delete_selected || 'Delete %d selected virtual folder(s)?').replace('%d', ids.length))) return;
			var previousTree = tree;
			setTree(function(nodes) {
				return ids.reduce(function(result, id) { return removeFolderFromTree(result, id); }, nodes);
			});
			setSelectedFolderIds({});
			setIsLoading(true);
			$.post(ajaxUrl, { action: 'swmf_bulk_delete_folders', nonce: nonce, ids: ids, object_type: objectType }, function(res) {
				if (res && res.success) {
					fetchTree();
					showToast(strings.selected_deleted || 'Selected folders deleted.', 'success');
				} else {
					setTree(previousTree);
					showToast((res && res.data) || strings.delete_folder_failed || 'Unable to delete selected folders.', 'error');
				}
			}).fail(function() {
				setTree(previousTree);
				showToast(strings.delete_folder_retry || 'Unable to delete selected folders. Please try again.', 'error');
			}).always(function() { setIsLoading(false); });
		};

		// Helper to find folder name by ID from tree
		var findFolderName = function(nodes, id) {
			for (var i = 0; i < nodes.length; i++) {
				if (nodes[i].id == id) return nodes[i].name;
				if (nodes[i].children && nodes[i].children.length) {
					var found = findFolderName(nodes[i].children, id);
					if (found) return found;
				}
			}
			return null;
		};

		// Drop handler for dragging items into folder
		var handleDropOnFolder = function(targetFolderId, attachmentIds, targetFolderName) {
			if (!attachmentIds || !attachmentIds.length) return;
			setDragOverFolderId(null);

			var numericFolderId = 0;
			var folderDisplayName = targetFolderName;

			if (targetFolderId === 'uncategorized' || targetFolderId === 0) {
				numericFolderId = 0;
				folderDisplayName = folderDisplayName || strings.uncategorized || 'Uncategorized';
			} else if (targetFolderId === 'all') {
				// Don't change folder if dropped on 'all files'
				return;
			} else {
				numericFolderId = parseInt(targetFolderId, 10);
				folderDisplayName = folderDisplayName || findFolderName(tree, numericFolderId) || 'folder';
			}

			var count = attachmentIds.length;

			setIsLoading(true);
			setMoveProgress({ done: 0, total: count });
			var moved = 0;
			var sendBatch = function() {
				var batch = attachmentIds.slice(moved, moved + 200);
				$.post(ajaxUrl, {
					action: 'swmf_bulk_assign_folder',
					nonce: nonce,
					object_ids: batch,
					object_type: objectType,
					folder_id: numericFolderId,
					include_counts: moved + batch.length >= count ? 1 : 0
				}).done(function(res) {
					if (!(res && res.success)) {
						showToast('Failed to move item(s). Please try again.', 'error');
						setIsLoading(false);
						setMoveProgress(null);
						if (moved) fetchTree();
						return;
					}
					moved += batch.length;
					setMoveProgress({ done: moved, total: count });
					if (moved < count) {
						sendBatch();
						return;
					}
					fetchTree();

					// If we are currently in "All Files", files stay visible because all files includes everything.
					// If we are in a specific virtual folder or uncategorized, remove only the moved item(s) from the view.
					if (currentFolder !== 'all') {
						// Grid view (Backbone collection)
						if (wp.media && wp.media.frame) {
							var content = wp.media.frame.content.get();
							if (content && content.collection) {
								attachmentIds.forEach(function(aid) {
									var model = content.collection.get(aid);
									if (model) {
										content.collection.remove(model);
									}
								});
							}
						}

						// DOM cleanup for grid view elements
						attachmentIds.forEach(function(aid) {
							$('.attachment[data-id="' + aid + '"]').fadeOut(250, function() {
								$(this).remove();
							});
							$('.attachment.attachment-id-' + aid).fadeOut(250, function() {
								$(this).remove();
							});
							$('#post-' + aid).fadeOut(250, function() {
								$(this).remove();
							});
						});

						// DOM cleanup for list view table rows
						if ($('table.media, .wp-list-table').length) {
							attachmentIds.forEach(function(aid) {
								$('table.media tbody tr#post-' + aid + ', .wp-list-table tbody tr#post-' + aid).fadeOut(250, function() {
									$(this).remove();
								});
							});
						}
					}

					// Show toast message
					var msg = count === 1
						? 'Item successfully moved to "' + folderDisplayName + '".'
						: count + ' items successfully moved to "' + folderDisplayName + '".';
					showToast(msg, 'success');
					setIsLoading(false);
					setMoveProgress(null);
				}).fail(function() {
					showToast('Failed to move item(s). Please check connection.', 'error');
					setIsLoading(false);
					setMoveProgress(null);
					if (moved) fetchTree();
				});
			};
			sendBatch();
		};

		// Setup HTML5 / jQuery Drag and Drop listeners on media cards
		useEffect(function() {
			// Native HTML5 drag support on wp.media items and list table rows
			var initMediaDraggable = function() {
				// Grid view attachments
				$('.attachment').each(function() {
					var $att = $(this);
					if (!$att.attr('draggable')) {
						$att.attr('draggable', 'true');
						$att.on('dragstart', function(e) {
							var id = $att.data('id') || $att.find('input[type="checkbox"]').val();
							if (!id) {
								var classMatches = ($att.attr('class') || '').match(/attachment-id-(\d+)/);
								if (classMatches) id = classMatches[1];
							}
							if (!id) {
								var idAttr = $att.attr('id') || '';
								var match = idAttr.match(/post-(\d+)/);
								if (match) id = match[1];
							}

							// Check if multiple attachments are selected
							var selectedIds = [];
							$('.attachment.selected').each(function() {
								var sid = $(this).data('id') || $(this).find('input[type="checkbox"]').val();
								if (!sid) {
									var smatch = ($(this).attr('class') || '').match(/attachment-id-(\d+)/);
									if (smatch) sid = smatch[1];
								}
								if (sid && selectedIds.indexOf(sid) === -1) selectedIds.push(sid);
							});

							if (id && selectedIds.indexOf(id) === -1) {
								if (!selectedIds.length) {
									selectedIds.push(id);
								}
							}

							$('body').addClass('swmf-dragging-media');
							$att.addClass('swmf-is-dragging');
							if (e.originalEvent && e.originalEvent.dataTransfer) {
								e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify(selectedIds));
								e.originalEvent.dataTransfer.setData('application/x-swmf-media', JSON.stringify(selectedIds));
								e.originalEvent.dataTransfer.effectAllowed = 'move';
							}
						});

						$att.on('dragend', function() {
							$('body').removeClass('swmf-dragging-media');
							$att.removeClass('swmf-is-dragging');
							setDragOverFolderId(null);
						});
					}
				});

				// List view table rows (upload.php?mode=list)
				$('table.media tbody tr').each(function() {
					var $row = $(this);
					if (!$row.attr('draggable')) {
						$row.attr('draggable', 'true');
						$row.on('dragstart', function(e) {
							var id = $row.find('input[name="media[]"]').val();
							if (!id) {
								var rowId = $row.attr('id') || '';
								var match = rowId.match(/post-(\d+)/);
								if (match) id = match[1];
							}

							var selectedIds = [];
							$('table.media tbody input[name="media[]"]:checked').each(function() {
								selectedIds.push($(this).val());
							});

							if (id && selectedIds.indexOf(id) === -1) {
								if (!selectedIds.length) selectedIds.push(id);
							}

							$('body').addClass('swmf-dragging-media');
							$row.addClass('swmf-is-dragging');
							if (e.originalEvent && e.originalEvent.dataTransfer) {
								e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify(selectedIds));
								e.originalEvent.dataTransfer.setData('application/x-swmf-media', JSON.stringify(selectedIds));
								e.originalEvent.dataTransfer.effectAllowed = 'move';
							}
						});

						$row.on('dragend', function() {
							$('body').removeClass('swmf-dragging-media');
							$row.removeClass('swmf-is-dragging');
							setDragOverFolderId(null);
						});
					}
				});

				if (objectType !== 'attachment') {
					$('.wp-list-table tbody tr').each(function() {
						var $row = $(this);
						if ($row.attr('draggable')) return;
						$row.attr('draggable', 'true').on('dragstart', function(e) {
							var match = ($row.attr('id') || '').match(/post-(\d+)/);
							var id = match ? match[1] : null;
							var selectedIds = $('.wp-list-table tbody input[name="post[]"]:checked').map(function() { return this.value; }).get();
							if (id && selectedIds.indexOf(id) === -1) selectedIds = [id];
							$('body').addClass('swmf-dragging-media');
							$row.addClass('swmf-is-dragging');
							if (e.originalEvent && e.originalEvent.dataTransfer) {
								e.originalEvent.dataTransfer.setData('text/plain', JSON.stringify(selectedIds));
								e.originalEvent.dataTransfer.setData('application/x-swmf-media', JSON.stringify(selectedIds));
								e.originalEvent.dataTransfer.effectAllowed = 'move';
							}
						}).on('dragend', function() {
							$('body').removeClass('swmf-dragging-media');
							$row.removeClass('swmf-is-dragging');
							setDragOverFolderId(null);
						});
					});
				}
			};

			// Global listener to prevent WordPress's default window uploader when dragging existing media items
			var onGlobalDragOver = function(e) {
				if ($('body').hasClass('swmf-dragging-media') || $('body').hasClass('swmf-dragging-folder')) {
					// Hide any active uploader window if already triggered
					$('.uploader-window').hide().css('opacity', 0);
				}
			};

			var onGlobalDragEnd = function() {
				$('body').removeClass('swmf-dragging-media swmf-dragging-folder');
			};

			window.addEventListener('dragover', onGlobalDragOver, true);
			window.addEventListener('dragend', onGlobalDragEnd, true);

			var interval = setInterval(initMediaDraggable, 800);
			initMediaDraggable();

			return function() {
				clearInterval(interval);
				window.removeEventListener('dragover', onGlobalDragOver, true);
				window.removeEventListener('dragend', onGlobalDragEnd, true);
			};
		}, [tree]);

		// Save Modal
		var handleSaveModal = function() {
			if (!modalFolderData.name.trim()) return;

			if (modalMode === 'create' || modalMode === 'create_sub') {
				setIsLoading(true);
				$.post(ajaxUrl, {
					action: 'swmf_create_folder',
					nonce: nonce,
					name: modalFolderData.name,
					object_type: objectType,
					parent_id: modalFolderData.parent_id,
					color: modalFolderData.color
				}, function(res) {
					if (res && res.success) {
						fetchTree();
						setModalOpen(false);
						showToast('Folder "' + modalFolderData.name + '" created.', 'success');
					} else {
						showToast((res && res.data) || 'Failed to create folder.', 'error');
					}
				}).always(function() {
					setIsLoading(false);
				});
			} else if (modalMode === 'edit') {
				setIsLoading(true);
				$.post(ajaxUrl, {
					action: 'swmf_update_folder',
					nonce: nonce,
					id: modalFolderData.id,
					name: modalFolderData.name,
					color: modalFolderData.color,
					parent_id: modalFolderData.parent_id
				}, function(res) {
					if (res && res.success) {
						fetchTree();
						setModalOpen(false);
						showToast('Folder updated.', 'success');
					} else {
						showToast((res && res.data) || 'Failed to update folder.', 'error');
					}
				}).always(function() {
					setIsLoading(false);
				});
			}
		};

		// Delete Folder
		var handleDelete = function(folder) {
			if (confirm((strings.confirm_delete || 'Delete folder "%s"?').replace('%s', folder.name))) {
				// Virtual folders are safe to remove immediately: this only changes the folder map,
				// never the physical media files or their URLs.
				var previousTree = tree;
				setTree(function(nodes) { return removeFolderFromTree(nodes, folder.id); });
				setSelectedFolderIds(function(previous) {
					var next = Object.assign({}, previous);
					delete next[folder.id];
					return next;
				});
				setIsLoading(true);
				$.post(ajaxUrl, {
					action: 'swmf_delete_folder',
					nonce: nonce,
					id: folder.id
				}, function(res) {
					if (res && res.success) {
						if (currentFolder == folder.id) {
							selectFolder('all');
						}
						fetchTree();
						showToast('Folder deleted.', 'success');
					} else {
						setTree(previousTree);
						showToast((res && res.data) || 'Failed to delete folder.', 'error');
					}
				}).fail(function() {
					setTree(previousTree);
					showToast('Failed to delete folder. Please try again.', 'error');
				}).always(function() {
					setIsLoading(false);
				});
			}
		};

		// Recursive Tree Node Renderer
		var renderTreeNode = function(node, depth) {
			var hasChildren = node.children && node.children.length > 0;
			var isExpanded = expandedFolders[node.id] || searchQuery.length > 0;
			var isActive = currentFolder == node.id;
			var isDragOver = dragOverFolderId == node.id;
			var isMenuOpen = activeMenuId == node.id;
			var isSelected = !!selectedFolderIds[node.id];
			var isLocked = Number(node.is_locked) === 1;

			// Filter check
			if (searchQuery) {
				var matches = node.name.toLowerCase().indexOf(searchQuery.toLowerCase()) !== -1;
				if (!matches && !hasChildren) return null;
			}

			return createElement('div', {
				key: node.id,
				className: 'swmf-tree-node'
			}, [
				createElement('div', {
					key: 'row',
					className: 'swmf-folder-row' + (isActive ? ' is-active' : '') + (isDragOver ? ' is-drag-over' : '') + (isMenuOpen ? ' has-menu-open' : '') + (isSelected ? ' is-selected' : ''),
					style: { paddingLeft: (depth * 14 + 6) + 'px' },
					draggable: permissions.edit && permissions.move && !isSelectingFolders,
					onDragStart: function(e) {
						if (!(permissions.edit && permissions.move && !isSelectingFolders)) return;
						setDraggedFolderId(node.id);
						$('body').addClass('swmf-dragging-folder');
						if (e.dataTransfer) {
							e.dataTransfer.setData('application/x-swmf-folder', String(node.id));
							e.dataTransfer.effectAllowed = 'move';
						}
					},
					onDragEnd: function() { setDraggedFolderId(null); setSortDragOverFolderId(null); $('body').removeClass('swmf-dragging-folder'); },
					onClick: function() {
						if (isSelectingFolders) toggleFolderSelection(node.id);
						else selectFolder(node.id);
					},
					onDragOver: function(e) {
						e.preventDefault();
						e.stopPropagation();
						if (e.dataTransfer) {
							e.dataTransfer.dropEffect = 'move';
						}
						if (dragOverFolderId !== node.id) setDragOverFolderId(node.id);
					},
					onDragLeave: function(e) {
						e.preventDefault();
						e.stopPropagation();
						if (dragOverFolderId === node.id) setDragOverFolderId(null);
					},
					onDrop: function(e) {
						e.preventDefault();
						e.stopPropagation();
						setDragOverFolderId(null);
						var folderId = e.dataTransfer && e.dataTransfer.getData('application/x-swmf-folder');
						if (folderId && !isCustomSorting) {
							setDraggedFolderId(null);
							if (isLocked) showToast('Folder is locked.', 'error');
							else moveFolder(Number(folderId), node.id);
							return;
						}
						$('body').removeClass('swmf-dragging-media');
						if (!permissions.move || isLocked) {
							showToast('Folder is locked.', 'error');
							return;
						}
						try {
							var dt = e.dataTransfer;
							var rawData = dt ? (dt.getData('application/x-swmf-media') || dt.getData('text/plain')) : null;
							if (rawData) {
								var ids = JSON.parse(rawData);
								handleDropOnFolder(node.id, ids, node.name);
							}
						} catch (err) {}
					}
				}, [
					createElement('div', { key: 'left', className: 'swmf-folder-row-left' }, [
						isSelectingFolders ? createElement('input', {
							key: 'select', type: 'checkbox', className: 'swmf-folder-select-checkbox', checked: isSelected,
							onClick: function(e) { e.stopPropagation(); },
							onChange: function() { toggleFolderSelection(node.id); }
						}) : null,
						createElement('span', {
							key: 'chevron',
							className: 'swmf-chevron' + (hasChildren ? (isExpanded ? ' is-open' : '') : ' is-empty'),
							onClick: function(e) { toggleExpand(node.id, e); }
						}, hasChildren ? createElement(ChevronIcon) : null),
						createElement('span', {
							key: 'dot',
							className: 'swmf-folder-color-dot',
							style: { backgroundColor: node.color || '#3498db' }
						}),
						createElement(FolderIcon, { key: 'icon' }),
						createElement('span', { key: 'name', className: 'swmf-folder-label', title: node.name }, node.name),
						isLocked ? createElement('span', { key: 'locked', className: 'swmf-folder-lock', title: 'Locked virtual folder' }, '🔒') : null
					]),
					createElement('div', { key: 'right', className: 'swmf-folder-row-right' }, [
						createElement('span', { key: 'cnt', className: 'swmf-badge' }, node.count || 0),
						(permissions.create || permissions.edit || permissions.delete) ? createElement('button', {
							key: 'actions',
							type: 'button',
							className: 'swmf-folder-actions-btn',
							onClick: function(e) {
								e.stopPropagation();
								if (!isMenuOpen) {
									var row = e.currentTarget.closest('.swmf-folder-row');
									var sidebar = e.currentTarget.closest('.swmf-sidebar-container');
									var rowBottom = row.getBoundingClientRect().bottom;
									var availableBottom = Math.min(sidebar.getBoundingClientRect().bottom, window.innerHeight);
									setMenuOpensUp(availableBottom - rowBottom < (isLocked ? 120 : 148));
								}
								setActiveMenuId(isMenuOpen ? null : node.id);
							}
						}, createElement(MoreIcon)) : null
					]),
					// Context Menu Popup
					isMenuOpen ? createElement('div', { key: 'menu', className: 'swmf-context-menu' + (menuOpensUp ? ' opens-up' : ''), onClick: function(e) { e.stopPropagation(); } }, [
						isLocked ? createElement('div', { key: 'locked-note', className: 'swmf-context-menu-note' }, 'Locked virtual folder') : null,
						(!isLocked && permissions.create) ? createElement('div', {
							key: 'add_sub',
							className: 'swmf-context-menu-item',
							onClick: function() {
								setActiveMenuId(null);
								setModalMode('create_sub');
								setModalFolderData({ id: 0, name: '', color: '#3498db', parent_id: node.id });
								setModalOpen(true);
							}
						}, strings.new_subfolder || 'New Subfolder') : null,
						permissions.edit ? createElement('div', {
							key: 'rename',
							className: 'swmf-context-menu-item',
							onClick: function() {
								setActiveMenuId(null);
								setModalMode('edit');
								setModalFolderData({ id: node.id, name: node.name, color: node.color, parent_id: node.parent_id });
								setModalOpen(true);
							}
						}, strings.rename || 'Rename') : null,
						(!isLocked && permissions.delete) ? createElement('div', {
							key: 'delete',
							className: 'swmf-context-menu-item is-danger',
							onClick: function() {
								setActiveMenuId(null);
								handleDelete(node);
							}
						}, strings.delete || 'Delete') : null
					]) : null
				]),
				// Children subfolder branch
				(hasChildren && isExpanded) ? createElement('div', {
					key: 'children',
					className: 'swmf-tree-children'
				}, renderTreeBranch(node.children, node.id, depth + 1)) : null
			]);
		};

		var renderTreeBranch = function(nodes, parentId, depth) {
			var limit = searchQuery ? nodes.length : (visibleFolderCounts[parentId] || 100);
			var items = nodes.slice(0, limit).map(function(node) { return renderTreeNode(node, depth); });
			if (nodes.length > limit) {
				items.push(createElement('button', {
					key: 'more-' + parentId,
					type: 'button',
					className: 'button-link swmf-folder-show-more',
					onClick: function() {
						setVisibleFolderCounts(function(previous) {
							return Object.assign({}, previous, { [parentId]: limit + 100 });
						});
					}
				}, strings.show_more_folders));
			}
			return items;
		};

		var swatches = ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6', '#34495e', '#1abc9c', '#e67e22'];

		return createElement('div', {
			className: 'swmf-sidebar-wrapper'
		}, [
			// Circular Expand/Collapse Button (always accessible outside the collapsing container)
			createElement('button', {
				key: 'toggle-btn',
				type: 'button',
				className: 'swmf-sidebar-toggle-btn' + (collapsed ? ' is-collapsed' : ''),
				title: collapsed ? (strings.expand_folders || 'Expand folders') : (strings.collapse_folders || 'Collapse folders'),
				onClick: toggleCollapse
			}, createElement(ChevronLeft)),

			// Collapsible Sidebar Container
			createElement('div', {
				key: 'container',
				className: 'swmf-sidebar-container' + (collapsed ? ' is-collapsed' : '')
			}, [
				// Header
				createElement('div', { key: 'header', className: 'swmf-sidebar-header' }, [
					createElement('h3', { key: 'title', className: 'swmf-sidebar-title' }, [
						createElement(FolderIcon, { key: 'icon' }),
						strings.folders || 'Folders'
					]),
					permissions.create ? createElement('button', {
						key: 'btn-add',
						type: 'button',
						className: 'swmf-btn-new-folder',
						onClick: function() {
							setModalMode('create');
							setModalFolderData({ id: 0, name: '', color: '#3498db', parent_id: 0 });
							setModalOpen(true);
						}
					}, [
						createElement('span', { key: 'plus' }, '+ '),
						strings.new_folder || 'New Folder'
					]) : null
				]),

				// Search
				createElement('div', { key: 'search', className: 'swmf-sidebar-search' }, [
					createElement('input', {
						key: 'input',
						type: 'text',
						className: 'swmf-search-input',
						placeholder: strings.search_folders || 'Search folders...',
						value: searchQuery,
						onChange: function(e) { setSearchQuery(e.target.value); }
					})
				]),

				// Folder settings
				createElement('div', { key: 'settings', className: 'swmf-folder-settings' }, [
					createElement('span', { key: 'label', className: 'swmf-folder-settings-label' }, strings.folder_settings || 'Folder settings'),
					createElement('div', { key: 'controls', className: 'swmf-folder-settings-controls' }, [
						(permissions.edit || permissions.delete) ? createElement('button', {
							key: 'select', type: 'button', className: 'swmf-folder-settings-button' + (isSelectingFolders ? ' is-active' : ''),
							onClick: function() {
								setIsSelectingFolders(!isSelectingFolders);
								if (isSelectingFolders) setSelectedFolderIds({});
							}
						}, [
							!isSelectingFolders ? createElement(PencilIcon, { key: 'icon' }) : null,
							isSelectingFolders ? (strings.done || 'Done') : (strings.edit_folders || 'Edit')
						]) : null,
						createElement('label', { key: 'sort-label', className: 'screen-reader-text', htmlFor: 'swmf-folder-sort' }, strings.sort_folders || 'Sort folders'),
						createElement('select', {
							key: 'sort', id: 'swmf-folder-sort', className: 'swmf-folder-sort', value: folderSort,
							onChange: function(e) {
								localStorage.setItem(folderSortStorageKey, e.target.value);
								setFolderSort(e.target.value);
							}
						}, [
							createElement('option', { key: 'asc', value: 'name-asc' }, strings.sort_a_z || 'Sort: A–Z'),
							createElement('option', { key: 'desc', value: 'name-desc' }, strings.sort_z_a || 'Sort: Z–A'),
						])
					]),
					(isSelectingFolders && (permissions.edit || permissions.delete)) ? createElement('div', { key: 'selection-actions', className: 'swmf-folder-selection-actions' }, [
						createElement('span', { key: 'count', className: 'swmf-folder-selection-count' }, (strings.selected_count || '%d selected').replace('%d', getSelectedFolderIds().length)),
						permissions.edit ? createElement('div', { key: 'colors', className: 'swmf-folder-selection-colors' }, [
							createElement('span', { key: 'label', className: 'swmf-folder-selection-tags-label' }, strings.change_tags || 'Change tags'),
							createElement('span', { key: 'swatches', className: 'swmf-folder-selection-swatches', 'aria-label': 'Set folder color tag' }, swatches.slice(0, 5).map(function(color) {
								return createElement('button', {
									key: color, type: 'button', className: 'swmf-folder-action-color', style: { backgroundColor: color },
									title: strings.set_color_tag || 'Set selected folders to this color tag', onClick: function() { updateSelectedFolders({ color: color }, strings.folder_color_updated || 'Folder color tag updated.'); }
								});
							}))
						]) : null,
						createElement('div', { key: 'buttons', className: 'swmf-folder-selection-buttons' }, [
								permissions.edit ? createElement('button', { key: 'lock', type: 'button', className: 'swmf-folder-action-button', onClick: function() { updateSelectedFolders({ is_locked: 1 }, strings.locked || 'Selected virtual folders locked.'); } }, strings.lock || 'Lock') : null,
								permissions.edit ? createElement('button', { key: 'unlock', type: 'button', className: 'swmf-folder-action-button', onClick: function() { updateSelectedFolders({ is_locked: 0 }, strings.unlocked || 'Selected virtual folders unlocked.'); } }, strings.unlock || 'Unlock') : null,
								permissions.delete ? createElement('button', { key: 'delete', type: 'button', className: 'swmf-folder-action-button is-danger', onClick: deleteSelectedFolders }, strings.delete_selected || 'Delete') : null
						])
					]) : null
				]),

				isLoading ? createElement('div', { key: 'progress', className: 'swmf-folder-progress', role: 'progressbar', 'aria-label': strings.loading_folders || 'Loading folders' },
					createElement('span', { className: 'swmf-folder-progress-bar' })
				) : null,
				moveProgress ? createElement('div', { key: 'move-progress', className: 'swmf-move-progress', role: 'status', 'aria-live': 'polite' },
					strings.moving_items.replace('%1$d', moveProgress.done).replace('%2$d', moveProgress.total)
				) : null,

				// Navigation List (All Files & Uncategorized)
				createElement('div', { key: 'body', className: 'swmf-sidebar-body' }, [
					createElement('ul', { key: 'nav', className: 'swmf-nav-list' }, [
						createElement('li', {
							key: 'all',
						className: 'swmf-nav-item' + (currentFolder === 'all' ? ' is-active' : '') + (dragOverFolderId === 'root' ? ' is-drag-over' : ''),
						onClick: function() { selectFolder('all'); },
						onDragOver: function(e) {
							if (!draggedFolderId) return;
							e.preventDefault();
							e.stopPropagation();
							if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
							setDragOverFolderId('root');
						},
						onDragLeave: function() { setDragOverFolderId(null); },
						onDrop: function(e) {
							if (!draggedFolderId) return;
							e.preventDefault();
							e.stopPropagation();
							setDragOverFolderId(null);
							moveFolder(draggedFolderId, 0);
						}
					}, [
						createElement('div', { key: 'left', className: 'swmf-nav-item-left' }, [
							createElement(FolderIcon, { key: 'icon' }),
							createElement('span', { key: 'text' }, strings.all_files || 'All Files')
						]),
						createElement('span', { key: 'badge', className: 'swmf-badge' }, counts.total || 0)
					]),
					createElement('li', {
						key: 'uncategorized',
						className: 'swmf-nav-item' + (currentFolder === 'uncategorized' ? ' is-active' : '') + (dragOverFolderId === 'uncategorized' ? ' is-drag-over' : ''),
						onClick: function() { selectFolder('uncategorized'); },
						onDragOver: function(e) {
							e.preventDefault();
							e.stopPropagation();
							if (e.dataTransfer) {
								e.dataTransfer.dropEffect = 'move';
							}
							if (dragOverFolderId !== 'uncategorized') setDragOverFolderId('uncategorized');
						},
						onDragLeave: function(e) {
							e.preventDefault();
							e.stopPropagation();
							if (dragOverFolderId === 'uncategorized') setDragOverFolderId(null);
						},
						onDrop: function(e) {
							e.preventDefault();
							e.stopPropagation();
							setDragOverFolderId(null);
							$('body').removeClass('swmf-dragging-media');
							try {
								var dt = e.dataTransfer;
								var rawData = dt ? (dt.getData('application/x-swmf-media') || dt.getData('text/plain')) : null;
								if (rawData) {
									var ids = JSON.parse(rawData);
									handleDropOnFolder('uncategorized', ids, strings.uncategorized || 'Uncategorized');
								}
							} catch (err) {}
						}
					}, [
						createElement('div', { key: 'left', className: 'swmf-nav-item-left' }, [
							createElement(FolderIcon, { key: 'icon' }),
							createElement('span', { key: 'text' }, strings.uncategorized || 'Uncategorized')
						]),
						createElement('span', { key: 'badge', className: 'swmf-badge' }, counts.uncategorized || 0)
					])
				]),

				// Folder Tree
				createElement('div', { key: 'tree', className: 'swmf-tree-section' }, [
					permissions.edit && permissions.move && !isSelectingFolders ? createElement('div', {
						key: 'root-drop',
						className: 'swmf-root-drop-target' + (dragOverFolderId === 'root' ? ' is-drag-over' : ''),
						onDragOver: function(e) {
							if (!draggedFolderId) return;
							e.preventDefault();
							e.stopPropagation();
							e.dataTransfer.dropEffect = 'move';
							setDragOverFolderId('root');
						},
						onDragLeave: function(e) { e.stopPropagation(); setDragOverFolderId(null); },
						onDrop: function(e) {
							e.preventDefault();
							e.stopPropagation();
							setDragOverFolderId(null);
							var folderId = draggedFolderId || (e.dataTransfer && e.dataTransfer.getData('application/x-swmf-folder'));
							if (folderId) moveFolder(Number(folderId), 0);
						}
					}, 'Top level folders') : null,
					tree.length > 0 ? renderTreeBranch(sortedTree, 0, 0) : createElement('div', {
						key: 'empty', style: { padding: '16px 8px', textAlign: 'center', color: '#8c8f94', fontSize: '12px' }
					}, strings.no_folders || 'No folders found')
				])
			]),
			]),

			// Floating Top-Center Toast Notification (overlay, does not affect page layout)
			toast ? createElement('div', {
				key: 'toast-container',
				className: 'swmf-toast-container'
			}, [
				createElement('div', {
					key: 'toast-msg',
					className: 'swmf-toast swmf-toast-' + (toast.type || 'success') + (toast.isLeaving ? ' swmf-toast-leave' : '')
				}, [
					toast.type === 'error' ? createElement(ErrorCircleIcon, { key: 'icon' }) : createElement(SuccessCheckIcon, { key: 'icon' }),
					createElement('span', { key: 'text' }, toast.message)
				])
			]) : null,

			// Modal
			modalOpen ? createElement('div', {
				key: 'modal',
				className: 'swmf-modal-backdrop',
				onClick: function() { setModalOpen(false); }
			}, [
				createElement('div', {
					key: 'card',
					className: 'swmf-modal-card',
					onClick: function(e) { e.stopPropagation(); }
				}, [
					createElement('div', { key: 'mheader', className: 'swmf-modal-header' },
						modalMode === 'edit' ? (strings.rename || 'Edit Folder') : (modalMode === 'create_sub' ? (strings.new_subfolder || 'New Subfolder') : (strings.new_folder || 'New Folder'))
					),
					createElement('div', { key: 'mbody', className: 'swmf-modal-body' }, [
						createElement('div', { key: 'fg1', className: 'swmf-form-group' }, [
							createElement('label', { key: 'lbl' }, strings.folder_name || 'Folder Name'),
							createElement('input', {
								key: 'inp',
								type: 'text',
								autoFocus: true,
								value: modalFolderData.name,
								onChange: function(e) {
									var val = e.target.value;
									setModalFolderData(function(prev) {
										return Object.assign({}, prev, { name: val });
									});
								},
								onKeyDown: function(e) {
									if (e.key === 'Enter') handleSaveModal();
								}
							})
						]),
						modalMode === 'edit' && permissions.move ? createElement('div', { key: 'parent', className: 'swmf-form-group' }, [
							createElement('label', { key: 'label', htmlFor: 'swmf-folder-parent' }, 'Parent folder'),
							createElement('select', {
								key: 'select', id: 'swmf-folder-parent', value: modalFolderData.parent_id,
								onChange: function(e) {
									setModalFolderData(function(previous) { return Object.assign({}, previous, { parent_id: Number(e.target.value) }); });
								}
							}, [createElement('option', { key: 'root', value: 0 }, 'Top level')].concat(parentOptions(tree, 0, findFolder(tree, modalFolderData.id))))
						]) : null,
						createElement('div', { key: 'fg2', className: 'swmf-form-group' }, [
							createElement('label', { key: 'lbl2' }, strings.folder_color || 'Color'),
							createElement('div', { key: 'swatches', className: 'swmf-color-palette' }, swatches.map(function(col) {
								return createElement('span', {
									key: col,
									className: 'swmf-color-swatch' + (modalFolderData.color === col ? ' is-selected' : ''),
									style: { backgroundColor: col },
									onClick: function() {
										setModalFolderData(function(prev) {
											return Object.assign({}, prev, { color: col });
										});
									}
								});
							}))
						])
					]),
					createElement('div', { key: 'mfooter', className: 'swmf-modal-footer' }, [
						createElement('button', {
							key: 'btn-cancel',
							type: 'button',
							className: 'button',
							onClick: function() { setModalOpen(false); }
						}, strings.cancel || 'Cancel'),
						createElement('button', {
							key: 'btn-save',
							type: 'button',
							className: 'button button-primary',
							onClick: handleSaveModal
						}, strings.save || 'Save')
					])
				])
			]) : null
		]);
	};

	// Mount into the DOM
	$(document).ready(function() {
		var $wrap = $('body.upload-php .wrap, body.edit-php .wrap').first();
		if (!$wrap.length) return;

		// Ensure layout container below the title and header
		var $layout = $('.swmf-layout-wrapper');
		if (!$layout.length) {
			$layout = $('<div class="swmf-layout-wrapper"></div>');
			var $sidebarRoot = $('<div id="swmf-sidebar-root"></div>');
			var $main = $('<div class="swmf-media-main"></div>');

			// Find header elements: h1, page-title-action (Add New), and wp-header-end
			var $headerElements = $wrap.find('h1, .wp-heading-inline, .page-title-action, hr.wp-header-end, #wp-media-grid-filter-anchor');
			var $contentElements = $wrap.children().not($headerElements).not('#swmf-sidebar-root');

			$contentElements.appendTo($main);
			$layout.append($sidebarRoot).append($main);

			var $headerEnd = $wrap.find('hr.wp-header-end');
			if ($headerEnd.length) {
				$headerEnd.after($layout);
			} else {
				$wrap.append($layout);
			}
		}

		var $root = $('#swmf-sidebar-root');
		if ($root.length) {
			if (wp.element.createRoot) {
				wp.element.createRoot($root[0]).render(createElement(SWMFSidebarApp));
			} else if (wp.element.render) {
				wp.element.render(createElement(SWMFSidebarApp), $root[0]);
			}
		}
	});

})(jQuery, window.wp);
