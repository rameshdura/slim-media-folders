( function() {
	// The block preview is clickable in the editor, so the front-end overlay must
	// never take over the editing canvas.
	if ( document.body.classList.contains( 'wp-admin' ) ) {
		return;
	}

	var modal;
	var links = [];
	var currentIndex = 0;

	function close() {
		if ( modal ) {
			modal.remove();
			modal = null;
		}
	}

	function showImage() {
		var link = links[ currentIndex ];
		var image = modal.querySelector( '.swmf-gallery-lightbox__image' );
		image.src = link.href;
		image.alt = link.querySelector( 'img' ).alt || '';
	}

	function changeImage( direction ) {
		currentIndex = ( currentIndex + direction + links.length ) % links.length;
		showImage();
	}

	function open( link ) {
		close();
		links = Array.prototype.slice.call( link.closest( '.swmf-gallery' ).querySelectorAll( '.swmf-gallery__link' ) );
		currentIndex = links.indexOf( link );
		modal = document.createElement( 'div' );
		modal.className = 'swmf-gallery-lightbox';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		var closeButton = document.createElement( 'button' );
		closeButton.type = 'button';
		closeButton.className = 'swmf-gallery-lightbox__close';
		closeButton.setAttribute( 'aria-label', window.swmfGalleryLightbox.closeLabel );
		closeButton.textContent = '×';
		var previousButton = document.createElement( 'button' );
		previousButton.type = 'button';
		previousButton.className = 'swmf-gallery-lightbox__previous';
		previousButton.setAttribute( 'aria-label', window.swmfGalleryLightbox.previousLabel );
		previousButton.textContent = '‹';
		var nextButton = document.createElement( 'button' );
		nextButton.type = 'button';
		nextButton.className = 'swmf-gallery-lightbox__next';
		nextButton.setAttribute( 'aria-label', window.swmfGalleryLightbox.nextLabel );
		nextButton.textContent = '›';
		var image = document.createElement( 'img' );
		image.className = 'swmf-gallery-lightbox__image';
		image.src = link.href;
		image.alt = link.querySelector( 'img' ).alt || '';
		modal.appendChild( closeButton );
		if ( links.length > 1 ) {
			modal.appendChild( previousButton );
			modal.appendChild( nextButton );
		}
		modal.appendChild( image );
		document.body.appendChild( modal );
		closeButton.focus();
	}

	document.addEventListener( 'click', function( event ) {
		var slideButton = event.target.closest( '.swmf-gallery-slider__previous, .swmf-gallery-slider__next' );
		if ( slideButton ) {
			var gallery = slideButton.closest( '.swmf-gallery-slider' ).querySelector( '.swmf-gallery' );
			var item = gallery.querySelector( '.swmf-gallery__item' );
			if ( item ) gallery.scrollBy( { left: ( slideButton.classList.contains( 'swmf-gallery-slider__next' ) ? 1 : -1 ) * ( item.getBoundingClientRect().width + 16 ), behavior: 'smooth' } );
			return;
		}
		var link = event.target.closest( '.swmf-gallery__link' );
		if ( link ) {
			event.preventDefault();
			open( link );
		} else if ( modal && event.target.closest( '.swmf-gallery-lightbox__previous' ) ) {
			changeImage( -1 );
		} else if ( modal && event.target.closest( '.swmf-gallery-lightbox__next' ) ) {
			changeImage( 1 );
		} else if ( modal && ( event.target === modal || event.target.closest( '.swmf-gallery-lightbox__close' ) ) ) {
			close();
		}
	} );
	document.addEventListener( 'keydown', function( event ) {
		if ( ! modal ) {
			return;
		}
		if ( 'Escape' === event.key ) {
			close();
		} else if ( 'ArrowLeft' === event.key && links.length > 1 ) {
			changeImage( -1 );
		} else if ( 'ArrowRight' === event.key && links.length > 1 ) {
			changeImage( 1 );
		}
	} );
}() );
