(function( $ ) {
	'use strict';

	function initAdminDatepickers( config, page ) {
		if ( config.bookingsPageSlug && page !== config.bookingsPageSlug ) {
			return;
		}

		if ( !$.fn.datepicker ) {
			return;
		}

		$( '.comarine-admin-datepicker' ).each( function() {
			var $input = $( this );
			if ( !$input.attr( 'placeholder' ) && config.dateInputFormat ) {
				$input.attr( 'placeholder', config.dateInputFormat );
			}

			$input.datepicker( config.datepicker || { dateFormat: 'dd/mm/yy' } );
		} );
	}

	function parseGalleryIds( value ) {
		if ( typeof value !== 'string' ) {
			return [];
		}

		var unique = {};
		value.split( ',' ).forEach( function( part ) {
			var id = parseInt( String( part ).trim(), 10 );
			if ( Number.isFinite( id ) && id > 0 ) {
				unique[ id ] = id;
			}
		} );

		return Object.keys( unique ).map( function( key ) {
			return unique[ key ];
		} );
	}

	function getAttachmentPreviewUrl( attachment ) {
		if ( !attachment || typeof attachment !== 'object' ) {
			return '';
		}

		if ( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) {
			return attachment.sizes.thumbnail.url;
		}

		if ( attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url ) {
			return attachment.sizes.medium.url;
		}

		return attachment.url || '';
	}

	function updateGalleryFieldState( $field ) {
		var $input = $field.find( '.comarine-storage-unit-gallery-field__input' );
		var $preview = $field.find( '.comarine-storage-unit-gallery-field__preview' );
		var $select = $field.find( '.comarine-storage-unit-gallery-field__select' );
		var $clear = $field.find( '.comarine-storage-unit-gallery-field__clear' );
		var hasImages = parseGalleryIds( $input.val() ).length > 0 && $preview.children().length > 0;

		$clear.toggle( hasImages );
		$select.text( hasImages ? 'Edit Gallery Images' : 'Select Gallery Images' );
	}

	function syncGalleryInputFromPreview( $field ) {
		var ids = [];
		$field.find( '.comarine-storage-unit-gallery-field__preview-item' ).each( function() {
			var id = parseInt( $( this ).attr( 'data-image-id' ) || '', 10 );
			if ( Number.isFinite( id ) && id > 0 ) {
				ids.push( id );
			}
		} );

		$field.find( '.comarine-storage-unit-gallery-field__input' ).val( ids.join( ',' ) );
		updateGalleryFieldState( $field );
	}

	function renderGalleryPreviewItems( $field, attachments ) {
		var $preview = $field.find( '.comarine-storage-unit-gallery-field__preview' );
		var $input = $field.find( '.comarine-storage-unit-gallery-field__input' );
		var ids = [];

		$preview.empty();

		( attachments || [] ).forEach( function( attachment ) {
			var id = attachment && attachment.id ? parseInt( attachment.id, 10 ) : 0;
			var url = getAttachmentPreviewUrl( attachment );
			var label = ( attachment && attachment.title ) ? String( attachment.title ) : '';
			var $item;
			var $thumb;
			var $img;

			if ( !id || !url ) {
				return;
			}

			ids.push( id );

			if ( !label ) {
				label = 'Image #' + id;
			}

			$item = $( '<li/>', {
				class: 'comarine-storage-unit-gallery-field__preview-item',
				'data-image-id': id
			} );

			$thumb = $( '<span/>', {
				class: 'comarine-storage-unit-gallery-field__preview-thumb'
			} );

			$img = $( '<img/>', {
				class: 'comarine-storage-unit-gallery-field__preview-image',
				src: url,
				alt: label
			} );

			$thumb.append( $img );
			$item.append( $thumb );
			$item.append( $( '<span/>', {
				class: 'comarine-storage-unit-gallery-field__preview-label',
				text: label
			} ) );
			$item.append( $( '<button/>', {
				type: 'button',
				class: 'button-link-delete comarine-storage-unit-gallery-field__remove',
				text: 'Remove',
				'aria-label': 'Remove image from gallery'
			} ) );

			$preview.append( $item );
		} );

		$input.val( ids.join( ',' ) );
		updateGalleryFieldState( $field );
	}

	function initStorageUnitGalleryFields( config ) {
		if ( !window.wp || !wp.media ) {
			return;
		}

		$( '.comarine-storage-unit-gallery-field' ).each( function() {
			var $field = $( this );
			var $input = $field.find( '.comarine-storage-unit-gallery-field__input' );
			var frame = null;

			if ( !$input.length ) {
				return;
			}

			updateGalleryFieldState( $field );

			$field.on( 'click', '.comarine-storage-unit-gallery-field__remove', function( event ) {
				event.preventDefault();
				$( this ).closest( '.comarine-storage-unit-gallery-field__preview-item' ).remove();
				syncGalleryInputFromPreview( $field );
			} );

			$field.on( 'click', '.comarine-storage-unit-gallery-field__clear', function( event ) {
				event.preventDefault();
				$field.find( '.comarine-storage-unit-gallery-field__preview' ).empty();
				$input.val( '' );
				updateGalleryFieldState( $field );
			} );

			$field.on( 'click', '.comarine-storage-unit-gallery-field__select', function( event ) {
				event.preventDefault();

				if ( frame ) {
					frame.open();
					return;
				}

				frame = wp.media( {
					title: config.mediaFrameTitle || 'Select unit gallery images',
					button: {
						text: config.mediaFrameButton || 'Use selected images'
					},
					library: {
						type: 'image'
					},
					multiple: true
				} );

				frame.on( 'open', function() {
					var selection = frame.state().get( 'selection' );
					var ids = parseGalleryIds( $input.val() );

					selection.reset();

					ids.forEach( function( id ) {
						var attachment = wp.media.attachment( id );
						if ( attachment ) {
							attachment.fetch();
							selection.add( attachment );
						}
					} );
				} );

				frame.on( 'select', function() {
					var attachments = frame.state().get( 'selection' ).toJSON();
					renderGalleryPreviewItems( $field, attachments );
				} );

				frame.open();
			} );
		} );
	}

	$( function() {
		var config = window.comarineStorageBookingAdmin || {};
		var page = new URLSearchParams( window.location.search ).get( 'page' );

		initAdminDatepickers( config, page );
		initStorageUnitGalleryFields( config );
	} );

})( jQuery );
