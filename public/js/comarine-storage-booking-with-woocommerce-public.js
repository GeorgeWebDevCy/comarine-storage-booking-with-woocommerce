(function( $ ) {
	'use strict';

	var availabilityRequestCache = {};

	function getPublicConfig() {
		if ( typeof window.comarineStorageBookingPublic !== 'object' || !window.comarineStorageBookingPublic ) {
			return {};
		}

		return window.comarineStorageBookingPublic;
	}

	function getI18n( key, fallback ) {
		var cfg = getPublicConfig();
		if ( cfg.i18n && typeof cfg.i18n[ key ] === 'string' && cfg.i18n[ key ] ) {
			return cfg.i18n[ key ];
		}

		return fallback;
	}

	function parseDateOnly( value ) {
		if ( typeof value !== 'string' ) {
			return null;
		}

		var normalized = value.trim();
		var parts = null;
		var year = 0;
		var month = 0;
		var day = 0;
		if ( !normalized ) {
			return null;
		}

		if ( /^\d{2}-\d{2}-\d{4}$/.test( normalized ) ) {
			parts = normalized.match( /^(\d{2})-(\d{2})-(\d{4})$/ );
			day = Number( parts[ 1 ] );
			month = Number( parts[ 2 ] );
			year = Number( parts[ 3 ] );
		} else if ( /^\d{4}-\d{2}-\d{2}$/.test( normalized ) ) {
			parts = normalized.match( /^(\d{4})-(\d{2})-(\d{2})$/ );
			year = Number( parts[ 1 ] );
			month = Number( parts[ 2 ] );
			day = Number( parts[ 3 ] );
		} else {
			return null;
		}

		var dt = new Date( year, month - 1, day );
		if (
			Number.isNaN( dt.getTime() ) ||
			dt.getFullYear() !== year ||
			dt.getMonth() !== month - 1 ||
			dt.getDate() !== day
		) {
			return null;
		}

		return Number.isNaN( dt.getTime() ) ? null : dt;
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

	function formatDisplayDateOnly( dateObj ) {
		if ( !( dateObj instanceof Date ) || Number.isNaN( dateObj.getTime() ) ) {
			return '';
		}

		var day = String( dateObj.getDate() ).padStart( 2, '0' );
		var month = String( dateObj.getMonth() + 1 ).padStart( 2, '0' );
		var year = dateObj.getFullYear();
		return day + '-' + month + '-' + year;
	}

	function setDateInputValue( $input, dateObj ) {
		if ( !$input || !$input.length ) {
			return;
		}

		$input.val( formatDisplayDateOnly( dateObj ) );
	}

	function normalizeDateInputsForSubmit( $form ) {
		if ( !$form || !$form.length ) {
			return;
		}

		[ 'comarine_start_date', 'comarine_end_date' ].forEach( function( fieldName ) {
			var $input = $form.find( 'input[name="' + fieldName + '"]' );
			if ( !$input.length ) {
				return;
			}

			var parsed = parseDateOnly( $input.val() );
			if ( parsed ) {
				$input.val( formatDateOnly( parsed ) );
			}
		} );
	}

	function addDays( dateObj, days ) {
		if ( !( dateObj instanceof Date ) || Number.isNaN( dateObj.getTime() ) ) {
			return null;
		}

		var next = new Date( dateObj.getTime() );
		next.setDate( next.getDate() + Number( days || 0 ) );
		return next;
	}

	function addMonths( dateObj, months ) {
		if ( !( dateObj instanceof Date ) || Number.isNaN( dateObj.getTime() ) ) {
			return null;
		}

		var count = Number( months || 0 );
		if ( !Number.isFinite( count ) ) {
			count = 0;
		}

		var next = new Date( dateObj.getTime() );
		next.setMonth( next.getMonth() + count );
		return Number.isNaN( next.getTime() ) ? null : next;
	}

	function getTodayDate() {
		return parseDateOnly( formatDateOnly( new Date() ) );
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

	function getSelectedDurationKey( $form ) {
		var $checked = $form.find( 'input[name="comarine_duration_key"]:checked' );
		if ( $checked.length ) {
			return $checked.val();
		}

		var $hidden = $form.find( 'input[type="hidden"][name="comarine_duration_key"]' );
		return $hidden.length ? $hidden.val() : '';
	}

	function getDurationMonthsForKey( durationKey ) {
		var key = ( durationKey || '' ).toString();
		var monthsByKey = {
			monthly: 1,
			'6m': 6,
			'12m': 12
		};

		if ( Object.prototype.hasOwnProperty.call( monthsByKey, key ) ) {
			return monthsByKey[ key ];
		}

		return 0;
	}

	function getSelectedDurationRangeEndForForm( $form, startDateObj ) {
		if ( !( startDateObj instanceof Date ) || Number.isNaN( startDateObj.getTime() ) ) {
			return null;
		}

		var selectedKey = ( getSelectedDurationKey( $form ) || '' ).toString();
		if ( !selectedKey || selectedKey === 'daily' ) {
			return null;
		}

		var months = getDurationMonthsForKey( selectedKey );
		if ( months <= 0 ) {
			return null;
		}

		return addMonths( startDateObj, months );
	}

	function parsePricePreviewPayload( $form ) {
		var payloadRaw = $form.attr( 'data-comarine-price-preview' );
		if ( !payloadRaw ) {
			return {};
		}

		try {
			return JSON.parse( payloadRaw ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function updatePricePreviewForForm( $form ) {
		var payload = parsePricePreviewPayload( $form );
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
				var minEnd = addDays( startDtForMin, 1 );
				var minEndStr = formatDateOnly( minEnd );
				$endInput.attr( 'min', minEndStr );
				var currentEndForMin = parseDateOnly( $endInput.val() );
				if ( !currentEndForMin || currentEndForMin <= startDtForMin ) {
					setDateInputValue( $endInput, minEnd );
					$endInput.trigger( 'change' );
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
				$estimateValue.text( 'Enter required m2' );
				if ( $estimateNote.length ) {
					$estimateNote.text( 'Set the area required for storage to calculate your estimate. Add-ons are added separately.' );
				}
				return;
			}

			total = total * areaRatio;
			if ( areaVal !== null ) {
				noteParts.push( areaVal.toFixed( 2 ) + ' m2 of ' + unitCapacity.toFixed( 2 ) + ' m2' );
			}
		}

		$estimateValue.text( formatMoney( total, currency ) );
		if ( $estimateNote.length ) {
			var noteText = noteParts.length ? ( 'Based on ' + noteParts.join( ' | ' ) + '. ' ) : '';
			$estimateNote.text( noteText + 'Add-ons are added separately.' );
		}
	}

	function buildAvailabilityRequestWindow() {
		var cfg = getPublicConfig();
		var horizonDays = Number( cfg.availabilityHorizonDays || 540 );
		if ( !Number.isFinite( horizonDays ) || horizonDays < 30 ) {
			horizonDays = 540;
		}

		var today = getTodayDate();
		var start = today || new Date();
		var end = addDays( start, horizonDays );
		return {
			start: formatDateOnly( start ),
			end: formatDateOnly( end )
		};
	}

	function getOrCreateAvailabilityState( $form ) {
		var state = $form.data( 'comarineDateAvailabilityState' );
		if ( state && typeof state === 'object' ) {
			return state;
		}

		state = {
			loading: false,
			loaded: false,
			error: '',
			blockedByStatus: false,
			unitStatus: 'available',
			isCapacityManaged: false,
			capacityM2: 0,
			rangeStart: '',
			rangeEnd: '',
			days: {}
		};
		$form.data( 'comarineDateAvailabilityState', state );
		return state;
	}

	function getUnitIdFromForm( $form ) {
		var raw = $form.find( 'input[name="comarine_unit_post_id"]' ).val();
		var unitId = parseInt( raw, 10 );
		return Number.isFinite( unitId ) && unitId > 0 ? unitId : 0;
	}

	function fetchDailyAvailabilityForForm( $form ) {
		var state = getOrCreateAvailabilityState( $form );
		var unitId = getUnitIdFromForm( $form );
		var cfg = getPublicConfig();
		var windowRange = buildAvailabilityRequestWindow();

		if ( !unitId || !cfg.ajaxUrl || !cfg.availabilityAction || !cfg.availabilityNonce ) {
			state.error = getI18n( 'availabilityError', 'Availability could not be loaded.' );
			state.loaded = false;
			return $.Deferred().reject().promise();
		}

		var cacheKey = [ unitId, windowRange.start, windowRange.end ].join( ':' );
		if ( availabilityRequestCache[ cacheKey ] ) {
			state.loading = true;
			return availabilityRequestCache[ cacheKey ].then( function( response ) {
				applyAvailabilityResponseToForm( $form, response );
				return response;
			} ).always( function() {
				state.loading = false;
			} );
		}

		state.loading = true;
		state.error = '';

		availabilityRequestCache[ cacheKey ] = $.ajax( {
			url: cfg.ajaxUrl,
			method: 'GET',
			dataType: 'json',
			cache: true,
			data: {
				action: cfg.availabilityAction,
				nonce: cfg.availabilityNonce,
				unit_post_id: unitId,
				start_date: windowRange.start,
				end_date: windowRange.end
			}
		} );

		return availabilityRequestCache[ cacheKey ].done( function( response ) {
			applyAvailabilityResponseToForm( $form, response );
		} ).fail( function() {
			delete availabilityRequestCache[ cacheKey ];
			state.loaded = false;
			state.error = getI18n( 'availabilityError', 'Availability could not be loaded.' );
			updateAvailabilityHelpMessage( $form );
		} ).always( function() {
			state.loading = false;
			refreshDailyDatepickers( $form );
		} );
	}

	function applyAvailabilityResponseToForm( $form, response ) {
		var state = getOrCreateAvailabilityState( $form );

		if ( !response || response.success !== true || !response.data ) {
			state.loaded = false;
			state.error = getI18n( 'availabilityError', 'Availability could not be loaded.' );
			updateAvailabilityHelpMessage( $form );
			return;
		}

		var data = response.data || {};
		var availability = data.availability || {};
		var days = availability.days || {};
		var normalizedDays = {};

		Object.keys( days ).forEach( function( key ) {
			if ( !/^\d{4}-\d{2}-\d{2}$/.test( key ) ) {
				return;
			}

			var row = days[ key ] || {};
			normalizedDays[ key ] = {
				reserved_m2: Number( row.reserved_m2 || 0 ),
				remaining_m2: Number( row.remaining_m2 || 0 ),
				is_blocked: !!row.is_blocked,
				is_available: !!row.is_available
			};
		} );

		state.loaded = true;
		state.error = '';
		state.blockedByStatus = !!data.blocked_by_status;
		state.unitStatus = ( data.unit_status || 'available' ).toString();
		state.isCapacityManaged = !!availability.is_capacity_managed;
		state.capacityM2 = Number( availability.capacity_m2 || 0 );
		state.rangeStart = ( availability.range_start_date || '' ).toString();
		state.rangeEnd = ( availability.range_end_date || '' ).toString();
		state.days = normalizedDays;

		updateAvailabilityHelpMessage( $form );
	}

	function getRequiredAreaForForm( $form, state ) {
		if ( !state || !state.isCapacityManaged ) {
			return 0;
		}

		var $areaInput = $form.find( 'input[name="comarine_requested_area_m2"]' );
		if ( !$areaInput.length ) {
			return 0;
		}

		var value = Number( $areaInput.val() );
		if ( Number.isFinite( value ) && value > 0 ) {
			return value;
		}

		return 0;
	}

	function getDayStateForForm( $form, dateObj ) {
		var state = getOrCreateAvailabilityState( $form );
		var dateKey = formatDateOnly( dateObj );
		var day = state.days && state.days[ dateKey ] ? state.days[ dateKey ] : null;
		var requiredArea = getRequiredAreaForForm( $form, state );

		if ( state.blockedByStatus ) {
			return {
				known: true,
				available: false,
				reason: getI18n( 'noCapacityForDate', 'Unavailable on this date' )
			};
		}

		if ( !state.loaded ) {
			return {
				known: false,
				available: true,
				reason: ''
			};
		}

		if ( !day ) {
			return {
				known: false,
				available: false,
				reason: getI18n( 'availabilityError', 'Availability could not be loaded.' )
			};
		}

		if ( state.isCapacityManaged ) {
			var remaining = Number( day.remaining_m2 || 0 );
			var threshold = requiredArea > 0 ? requiredArea : 0.01;
			var available = remaining + 0.0001 >= threshold;
			return {
				known: true,
				available: available,
				reason: available ? '' : ( requiredArea > 0 ? getI18n( 'insufficientArea', 'Not enough available area for selected m2' ) : getI18n( 'noCapacityForDate', 'Unavailable on this date' ) ),
				remaining: remaining
			};
		}

		return {
			known: true,
			available: !( day.is_blocked || day.is_available === false ),
			reason: ( day.is_blocked || day.is_available === false ) ? getI18n( 'noCapacityForDate', 'Unavailable on this date' ) : ''
		};
	}

	function isContinuousRangeAvailableForForm( $form, startDateObj, endDateObj ) {
		if ( !( startDateObj instanceof Date ) || !( endDateObj instanceof Date ) ) {
			return false;
		}

		if ( endDateObj <= startDateObj ) {
			return false;
		}

		var cursor = new Date( startDateObj.getTime() );
		while ( cursor < endDateObj ) {
			var dayState = getDayStateForForm( $form, cursor );
			if ( !dayState.available ) {
				return false;
			}
			cursor = addDays( cursor, 1 );
		}

		return true;
	}

	function ensureAvailabilityHelpNode( $form ) {
		var $node = $form.find( '.comarine-storage-unit-card__datepicker-note' );
		if ( $node.length ) {
			return $node;
		}

		$node = $( '<p class="comarine-storage-unit-card__datepicker-note" aria-live="polite"></p>' );
		var $startField = $form.find( '.comarine-storage-unit-card__start-date-field' ).first();
		if ( $startField.length ) {
			$startField.append( $node );
		} else {
			$form.prepend( $node );
		}

		return $node;
	}

	function updateAvailabilityHelpMessage( $form ) {
		var state = getOrCreateAvailabilityState( $form );
		var $note = ensureAvailabilityHelpNode( $form );

		if ( state.loading && !state.loaded ) {
			$note.text( getI18n( 'loadingAvailability', 'Loading availability...' ) ).removeClass( 'is-error is-ready' );
			return;
		}

		if ( state.error ) {
			$note.text( state.error ).addClass( 'is-error' ).removeClass( 'is-ready' );
			return;
		}

		if ( state.loaded ) {
			$note.text( '' ).removeClass( 'is-error' ).addClass( 'is-ready' );
			return;
		}

		$note.text( '' ).removeClass( 'is-error is-ready' );
	}

	function refreshDailyDatepickers( $form ) {
		var $startInput = $form.find( 'input[name="comarine_start_date"]' );
		var $endInput = $form.find( 'input[name="comarine_end_date"]' );

		if ( $startInput.data( 'datepicker' ) ) {
			$startInput.datepicker( 'refresh' );
		}

		if ( $endInput.length && $endInput.data( 'datepicker' ) ) {
			var startDate = parseDateOnly( $startInput.val() );
			var minEndDate = startDate ? addDays( startDate, 1 ) : addDays( getTodayDate() || new Date(), 1 );
			if ( minEndDate ) {
				$endInput.datepicker( 'option', 'minDate', minEndDate );
			}

			var currentEnd = parseDateOnly( $endInput.val() );
			if ( currentEnd && startDate && ( currentEnd <= startDate || !isContinuousRangeAvailableForForm( $form, startDate, currentEnd ) ) ) {
				var nextSuggested = addDays( startDate, 1 );
				if ( nextSuggested && isContinuousRangeAvailableForForm( $form, startDate, nextSuggested ) ) {
					setDateInputValue( $endInput, nextSuggested );
					$endInput.trigger( 'change' );
				}
			}

			$endInput.datepicker( 'refresh' );
		}
	}

	function ensureDailyDatepickersForForm( $form ) {
		if ( !$form || !$form.length || $form.data( 'comarineDatepickerReady' ) ) {
			return;
		}

		var payload = parsePricePreviewPayload( $form );
		if ( !payload ) {
			return;
		}

		if ( !$.fn.datepicker ) {
			return;
		}

		var $startInput = $form.find( 'input[name="comarine_start_date"]' );
		var $endInput = $form.find( 'input[name="comarine_end_date"]' );
		var hasDailyRange = !!payload.uses_daily && !!$endInput.length;
		if ( !$startInput.length ) {
			return;
		}

		[ $startInput, $endInput ].forEach( function( $input ) {
			if ( !$input.length ) {
				return;
			}

			if ( ( $input.attr( 'type' ) || '' ).toLowerCase() === 'date' ) {
				$input.attr( 'type', 'text' );
			}

			$input.attr( 'autocomplete', 'off' );
			$input.attr( 'placeholder', 'dd-mm-yyyy' );
			$input.addClass( 'comarine-storage-unit-card__datepicker-input' );

			var normalizedDate = parseDateOnly( $input.val() );
			if ( normalizedDate ) {
				setDateInputValue( $input, normalizedDate );
			}
		} );

		var cfg = getPublicConfig();
		var datepickerOptions = $.extend( {}, cfg.datepicker || {}, {
			dateFormat: 'dd-mm-yy',
			showAnim: '',
			beforeShow: function() {
				fetchDailyAvailabilityForForm( $form );
				updateAvailabilityHelpMessage( $form );
			}
		} );

		$startInput.datepicker( $.extend( {}, datepickerOptions, {
			minDate: 0,
			beforeShowDay: function( dateObj ) {
				var state = getOrCreateAvailabilityState( $form );
				if ( state.loading && !state.loaded ) {
					return [ false, 'comarine-ui-day--loading', getI18n( 'loadingAvailability', 'Loading availability...' ) ];
				}

				if ( hasDailyRange ) {
					var dayState = getDayStateForForm( $form, dateObj );
					if ( !dayState.known && !state.loaded ) {
						return [ true, '', '' ];
					}

					if ( !dayState.available ) {
						return [ false, 'comarine-ui-day--blocked', dayState.reason || getI18n( 'noCapacityForDate', 'Unavailable on this date' ) ];
					}

					if ( state.isCapacityManaged && Number.isFinite( dayState.remaining ) ) {
						return [ true, dayState.remaining <= 0 ? 'comarine-ui-day--blocked' : 'comarine-ui-day--capacity', '' ];
					}

					return [ true, '', '' ];
				}

				if ( !state.loaded ) {
					return [ true, '', '' ];
				}

				var rangeEnd = getSelectedDurationRangeEndForForm( $form, dateObj );
				if ( !( rangeEnd instanceof Date ) || Number.isNaN( rangeEnd.getTime() ) ) {
					return [ true, '', '' ];
				}

				if ( !isContinuousRangeAvailableForForm( $form, dateObj, rangeEnd ) ) {
					return [ false, 'comarine-ui-day--blocked', getI18n( 'endDateBlocked', 'Selected range includes unavailable dates' ) ];
				}

				return [ true, state.isCapacityManaged ? 'comarine-ui-day--capacity' : '', '' ];
			},
			onSelect: function( selectedDate ) {
				var startDate = parseDateOnly( selectedDate );
				if ( hasDailyRange ) {
					var endMin = startDate ? addDays( startDate, 1 ) : null;
					if ( endMin ) {
						$endInput.datepicker( 'option', 'minDate', endMin );

						var currentEnd = parseDateOnly( $endInput.val() );
						if ( !currentEnd || currentEnd <= startDate || !isContinuousRangeAvailableForForm( $form, startDate, currentEnd ) ) {
							var proposedEnd = endMin;
							if ( isContinuousRangeAvailableForForm( $form, startDate, proposedEnd ) ) {
								setDateInputValue( $endInput, proposedEnd );
							}
						}
					}

					$endInput.datepicker( 'refresh' );
				}

				$startInput.trigger( 'change' );
				if ( hasDailyRange ) {
					$endInput.trigger( 'change' );
				}
			}
		} ) );

		if ( hasDailyRange ) {
			$endInput.datepicker( $.extend( {}, datepickerOptions, {
				minDate: 1,
				beforeShowDay: function( dateObj ) {
					var state = getOrCreateAvailabilityState( $form );
					var startDate = parseDateOnly( $startInput.val() );

					if ( state.loading && !state.loaded ) {
						return [ false, 'comarine-ui-day--loading', getI18n( 'loadingAvailability', 'Loading availability...' ) ];
					}

					if ( !startDate ) {
						return [ false, 'comarine-ui-day--blocked', getI18n( 'selectStartDateFirst', 'Select start date first' ) ];
					}

					if ( dateObj <= startDate ) {
						return [ false, 'comarine-ui-day--blocked', getI18n( 'endDateBlocked', 'Selected range includes unavailable dates' ) ];
					}

					if ( !state.loaded ) {
						return [ true, '', '' ];
					}

					if ( !isContinuousRangeAvailableForForm( $form, startDate, dateObj ) ) {
						return [ false, 'comarine-ui-day--blocked', getI18n( 'endDateBlocked', 'Selected range includes unavailable dates' ) ];
					}

					return [ true, 'comarine-ui-day--checkout', '' ];
				},
				onSelect: function() {
					$endInput.trigger( 'change' );
				}
			} ) );
		}

		$form.on( 'input change', 'input[name="comarine_requested_area_m2"]', function() {
			refreshDailyDatepickers( $form );
		} );

		$form.on( 'change', 'input[name="comarine_start_date"]', function() {
			refreshDailyDatepickers( $form );
		} );

		$form.on( 'change', 'input[name="comarine_duration_key"]', function() {
			refreshDailyDatepickers( $form );
		} );

		$form.on( 'submit', function() {
			normalizeDateInputsForSubmit( $form );
		} );

		fetchDailyAvailabilityForForm( $form );
		$form.data( 'comarineDatepickerReady', true );
	}

	function initStorageUnitLightboxes() {
		$( '[data-comarine-lightbox-gallery]' ).each( function( galleryIdx ) {
			var $gallery = $( this );
			var $overlay = $gallery.find( '[data-comarine-lightbox]' ).first();
			var $triggers = $gallery.find( '[data-comarine-lightbox-image]' );
			var $lightboxImage = $overlay.find( '[data-comarine-lightbox-current-image]' );
			var $lightboxCaption = $overlay.find( '[data-comarine-lightbox-caption]' );
			var $prevButton = $overlay.find( '[data-comarine-lightbox-prev]' );
			var $nextButton = $overlay.find( '[data-comarine-lightbox-next]' );
			var currentIndex = 0;
			var items = [];
			var keydownNamespace = '.comarineLightbox' + galleryIdx;

			if ( !$overlay.length || !$triggers.length || !$lightboxImage.length ) {
				return;
			}

			$triggers.each( function( idx ) {
				var $trigger = $( this );
				var href = ( $trigger.attr( 'href' ) || '' ).toString();
				var caption = ( $trigger.attr( 'data-comarine-lightbox-caption' ) || '' ).toString();
				var imgAlt = $trigger.find( 'img' ).first().attr( 'alt' );

				if ( !href ) {
					return;
				}

				$trigger.attr( 'data-comarine-lightbox-index', items.length );
				items.push( {
					src: href,
					alt: caption || ( imgAlt ? imgAlt.toString() : '' )
				} );
			} );

			if ( !items.length ) {
				return;
			}

			function renderCurrentItem() {
				var item = items[ currentIndex ] || items[ 0 ];
				if ( !item ) {
					return;
				}

				$lightboxImage.attr( 'src', item.src );
				$lightboxImage.attr( 'alt', item.alt || '' );

				if ( $lightboxCaption.length ) {
					$lightboxCaption.text( item.alt || '' );
					$lightboxCaption.toggle( !!item.alt );
				}

				if ( items.length <= 1 ) {
					$prevButton.prop( 'disabled', true );
					$nextButton.prop( 'disabled', true );
				} else {
					$prevButton.prop( 'disabled', false );
					$nextButton.prop( 'disabled', false );
				}
			}

			function openLightboxAt( index ) {
				if ( !items.length ) {
					return;
				}

				currentIndex = Number.isFinite( index ) ? Math.max( 0, Math.min( index, items.length - 1 ) ) : 0;
				renderCurrentItem();
				$overlay.prop( 'hidden', false ).attr( 'aria-hidden', 'false' );
				$( 'body' ).addClass( 'comarine-storage-unit-lightbox-open' );
			}

			function closeLightbox() {
				$overlay.prop( 'hidden', true ).attr( 'aria-hidden', 'true' );
				$( 'body' ).removeClass( 'comarine-storage-unit-lightbox-open' );
			}

			function stepLightbox( delta ) {
				if ( items.length <= 1 ) {
					return;
				}

				currentIndex = ( currentIndex + delta + items.length ) % items.length;
				renderCurrentItem();
			}

			$gallery.on( 'click', '[data-comarine-lightbox-image]', function( event ) {
				var idx = parseInt( $( this ).attr( 'data-comarine-lightbox-index' ) || '0', 10 );
				event.preventDefault();
				openLightboxAt( Number.isFinite( idx ) ? idx : 0 );
			} );

			$overlay.on( 'click', '[data-comarine-lightbox-close]', function( event ) {
				event.preventDefault();
				closeLightbox();
			} );

			$overlay.on( 'click', '[data-comarine-lightbox-prev]', function( event ) {
				event.preventDefault();
				stepLightbox( -1 );
			} );

			$overlay.on( 'click', '[data-comarine-lightbox-next]', function( event ) {
				event.preventDefault();
				stepLightbox( 1 );
			} );

			$overlay.on( 'click', function( event ) {
				if ( event.target === $overlay.get( 0 ) ) {
					closeLightbox();
				}
			} );

			$( document ).off( 'keydown' + keydownNamespace ).on( 'keydown' + keydownNamespace, function( event ) {
				if ( $overlay.prop( 'hidden' ) ) {
					return;
				}

				if ( 'Escape' === event.key ) {
					closeLightbox();
					return;
				}

				if ( 'ArrowLeft' === event.key ) {
					event.preventDefault();
					stepLightbox( -1 );
					return;
				}

				if ( 'ArrowRight' === event.key ) {
					event.preventDefault();
					stepLightbox( 1 );
				}
			} );
		} );
	}

	$( function() {
		var $forms = $( '[data-comarine-price-preview]' );
		initStorageUnitLightboxes();

		if ( $forms.length ) {
			$forms.each( function() {
				var $form = $( this );

				updatePricePreviewForForm( $form );
				ensureDailyDatepickersForForm( $form );

				$form.on( 'input change', 'input[name="comarine_requested_area_m2"], input[name="comarine_start_date"], input[name="comarine_end_date"], input[name="comarine_duration_key"]', function() {
					updatePricePreviewForForm( $form );
				} );
			} );
		}
	} );

})( jQuery );
