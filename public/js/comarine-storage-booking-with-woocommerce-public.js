(function( $ ) {
	'use strict';

	function parseDateOnly( value ) {
		if ( typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test( value ) ) {
			return null;
		}

		var dt = new Date( value + 'T00:00:00' );
		return Number.isNaN( dt.getTime() ) ? null : dt;
	}

	function formatMoney( amount, currencyCode ) {
		var numeric = Number( amount );
		if ( !Number.isFinite( numeric ) ) {
			numeric = 0;
		}

		var code = ( currencyCode || 'EUR' ).toString().toUpperCase();
		try {
			return new Intl.NumberFormat( undefined, {
				style: 'currency',
				currency: code,
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			} ).format( numeric );
		} catch ( e ) {
			return numeric.toFixed( 2 ) + ' ' + code;
		}
	}

	function formatDateOnly( dateObj ) {
		if ( !( dateObj instanceof Date ) || Number.isNaN( dateObj.getTime() ) ) {
			return '';
		}

		var year = dateObj.getFullYear();
		var month = String( dateObj.getMonth() + 1 ).padStart( 2, '0' );
		var day = String( dateObj.getDate() ).padStart( 2, '0' );
		return year + '-' + month + '-' + day;
	}

	function getSelectedDurationKey( $form ) {
		var $checked = $form.find( 'input[name="comarine_duration_key"]:checked' );
		if ( $checked.length ) {
			return $checked.val();
		}

		var $hidden = $form.find( 'input[type="hidden"][name="comarine_duration_key"]' );
		return $hidden.length ? $hidden.val() : '';
	}

	function updatePricePreviewForForm( $form ) {
		var payloadRaw = $form.attr( 'data-comarine-price-preview' );
		if ( !payloadRaw ) {
			return;
		}

		var payload = {};
		try {
			payload = JSON.parse( payloadRaw );
		} catch ( e ) {
			return;
		}

		var prices = payload && payload.prices ? payload.prices : {};
		var currency = payload && payload.currency ? payload.currency : 'EUR';
		var unitCapacity = payload && payload.unit_capacity_m2 ? Number( payload.unit_capacity_m2 ) : 0;
		var usesDaily = !!( payload && payload.uses_daily );

		var $areaInput = $form.find( 'input[name="comarine_requested_area_m2"]' );
		var $startInput = $form.find( 'input[name="comarine_start_date"]' );
		var $endInput = $form.find( 'input[name="comarine_end_date"]' );
		var $estimateValue = $form.find( '[data-comarine-price-estimate-value]' );
		var $estimateNote = $form.find( '[data-comarine-price-estimate-note]' );
		var selectedKey = ( getSelectedDurationKey( $form ) || '' ).toString();

		if ( usesDaily && $startInput.length && $endInput.length ) {
			var startValForMin = $startInput.val();
			var startDtForMin = parseDateOnly( startValForMin );
			if ( startDtForMin ) {
				var minEnd = new Date( startDtForMin.getTime() + 86400000 );
				var minEndStr = formatDateOnly( minEnd );
				$endInput.attr( 'min', minEndStr );
				if ( !$endInput.val() || $endInput.val() <= startValForMin ) {
					$endInput.val( minEndStr );
				}
			}
		}

		var areaRatio = 1;
		var areaVal = null;
		if ( $areaInput.length && unitCapacity > 0 ) {
			areaVal = Number( $areaInput.val() );
			if ( Number.isFinite( areaVal ) && areaVal > 0 ) {
				areaRatio = Math.max( 0, Math.min( areaVal / unitCapacity, 1 ) );
			} else {
				areaRatio = 0;
			}
		}

		$form.find( '[data-comarine-price-key]' ).each( function() {
			var $priceEl = $( this );
			var key = ( $priceEl.attr( 'data-comarine-price-key' ) || '' ).toString();
			var full = Number( $priceEl.attr( 'data-comarine-price-full' ) || 0 );
			if ( !Number.isFinite( full ) || full <= 0 ) {
				return;
			}

			var adjusted = full;
			if ( $areaInput.length && unitCapacity > 0 && areaRatio > 0 ) {
				adjusted = full * areaRatio;
			}

			$priceEl.text( formatMoney( adjusted, currency ) );
		} );

		if ( !$estimateValue.length ) {
			return;
		}

		if ( !selectedKey || !Object.prototype.hasOwnProperty.call( prices, selectedKey ) ) {
			$estimateValue.text( 'Select booking details' );
			return;
		}

		var base = Number( prices[ selectedKey ] || 0 );
		if ( !Number.isFinite( base ) || base <= 0 ) {
			$estimateValue.text( 'Pricing unavailable' );
			return;
		}

		var noteParts = [];
		var total = base;

		if ( 'daily' === selectedKey ) {
			var startDt = parseDateOnly( $startInput.val() );
			var endDt = parseDateOnly( $endInput.val() );
			if ( !startDt || !endDt || endDt <= startDt ) {
				$estimateValue.text( 'Select valid start/end dates' );
				if ( $estimateNote.length ) {
					$estimateNote.text( 'End date must be after start date. Add-ons are added separately.' );
				}
				return;
			}

			var days = Math.round( ( endDt.getTime() - startDt.getTime() ) / 86400000 );
			if ( days <= 0 ) {
				$estimateValue.text( 'Select valid start/end dates' );
				return;
			}

			total = base * days;
			noteParts.push( days + ' day' + ( days === 1 ? '' : 's' ) );
		}

		if ( $areaInput.length && unitCapacity > 0 ) {
			if ( areaRatio <= 0 ) {
				$estimateValue.text( 'Enter required m²' );
				if ( $estimateNote.length ) {
					$estimateNote.text( 'Set the area required for storage to calculate your estimate. Add-ons are added separately.' );
				}
				return;
			}

			total = total * areaRatio;
			if ( areaVal !== null ) {
				noteParts.push( areaVal.toFixed( 2 ) + ' m² of ' + unitCapacity.toFixed( 2 ) + ' m²' );
			}
		}

		$estimateValue.text( formatMoney( total, currency ) );
		if ( $estimateNote.length ) {
			var noteText = noteParts.length ? ( 'Based on ' + noteParts.join( ' • ' ) + '. ' ) : '';
			$estimateNote.text( noteText + 'Add-ons are added separately.' );
		}
	}

	$( function() {
		var $forms = $( '[data-comarine-price-preview]' );
		if ( !$forms.length ) {
			return;
		}

		$forms.each( function() {
			var $form = $( this );
			updatePricePreviewForForm( $form );

			$form.on( 'input change', 'input[name="comarine_requested_area_m2"], input[name="comarine_start_date"], input[name="comarine_end_date"], input[name="comarine_duration_key"]', function() {
				updatePricePreviewForForm( $form );
			} );
		} );
	} );

})( jQuery );
