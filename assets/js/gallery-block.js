( function( blocks, blockEditor, components, data, element, i18n, serverSideRender ) {
	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var Placeholder = components.Placeholder;
	var ServerSideRender = serverSideRender;
	var config = window.swmfGalleryBlock || { folders: [], imageSizes: [], strings: {} };
	var strings = config.strings;

	blocks.registerBlockType( 'slim-media-folders/gallery', {
		apiVersion: 3,
		title: strings.title,
		description: strings.description,
		icon: 'format-gallery',
		category: 'media',
		attributes: {
			folderId: { type: 'integer', default: 0 },
			columns: { type: 'integer', default: 3 },
			imageSize: { type: 'string', default: 'large' },
			lightbox: { type: 'boolean', default: true },
			captions: { type: 'boolean', default: false },
			layout: { type: 'string', default: 'grid' },
			perPage: { type: 'integer', default: 0 }
		},
		edit: function( props ) {
			var attributes = props.attributes;
			var editorProps = useBlockProps( {
				className: 'swmf-gallery-editor-preview',
				onClick: function( event ) {
					event.preventDefault();
					data.dispatch( 'core/block-editor' ).selectBlock( props.clientId );
				}
			} );
			var folderOptions = [ { label: strings.chooseFolder, value: '0' } ].concat( config.folders );
			var controls = el( InspectorControls, {}, el( PanelBody, { title: strings.title, initialOpen: true },
				el( SelectControl, { label: strings.folder, value: String( attributes.folderId ), options: folderOptions, onChange: function( value ) { props.setAttributes( { folderId: parseInt( value, 10 ) || 0 } ); } } ),
				el( RangeControl, { label: strings.columns, value: attributes.columns, onChange: function( value ) { props.setAttributes( { columns: value } ); }, min: 1, max: 6 } ),
				el( SelectControl, { label: strings.imageSize, value: attributes.imageSize, options: config.imageSizes, onChange: function( value ) { props.setAttributes( { imageSize: value } ); } } ),
				el( ToggleControl, { label: strings.lightbox, checked: attributes.lightbox, onChange: function( value ) { props.setAttributes( { lightbox: value } ); } } ),
				el( ToggleControl, { label: strings.captions, checked: attributes.captions, onChange: function( value ) { props.setAttributes( { captions: value } ); } } ),
				el( SelectControl, { label: strings.layout, value: attributes.layout, options: [ { label: strings.grid, value: 'grid' }, { label: strings.masonry, value: 'masonry' } ], onChange: function( value ) { props.setAttributes( { layout: value } ); } } ),
				el( RangeControl, { label: strings.perPage, value: attributes.perPage, onChange: function( value ) { props.setAttributes( { perPage: value } ); }, min: 0, max: 100 } )
			) );
			if ( ! attributes.folderId ) {
				return el( 'div', editorProps, controls, el( Placeholder, { icon: 'format-gallery', label: strings.noFolderTitle, instructions: strings.noFolderDescription } ) );
			}
			return el( 'div', editorProps, controls, el( ServerSideRender, { block: 'slim-media-folders/gallery', attributes: attributes } ) );
		},
		save: function() { return null; }
	} );
	}( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.data, window.wp.element, window.wp.i18n, window.wp.serverSideRender ) );
