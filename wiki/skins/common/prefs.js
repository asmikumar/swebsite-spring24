// generate toc from prefs form, fold sections
// XXX: needs testing on IE/Mac and safari
// more comments to follow
function tabbedprefs() {
	var prefform = document.getElementById( 'preferences' );
	if ( !prefform || !document.createElement ) {
		return;
	}
	if ( prefform.nodeName.toLowerCase() == 'a' ) {
		return; // Occasional IE problem
	}
	prefform.className = prefform.className + 'jsprefs';
	var sections = [];
	var children = prefform.childNodes;
	var seci = 0;
	for ( var i = 0; i < children.length; i++ ) {
		if ( children[i].nodeName.toLowerCase() == 'fieldset' ) {
			children[i].id = 'prefsection-' + seci;
			children[i].className = 'prefsection';
			if ( is_opera ) {
				children[i].className = 'prefsection operaprefsection';
			}
			var legends = children[i].getElementsByTagName('legend');
			sections[seci] = {};
			if ( legends[0] ) {
				legends[0].className = 'mainLegend';
			}
			if ( legends[0] && legends[0].firstChild.nodeValue ) {
				sections[seci].text = legends[0].firstChild.nodeValue;
			} else {
				sections[seci].text = '# ' + seci;
			}
			sections[seci].secid = children[i].id;
			seci++;
			if ( sections.length != 1 ) {
				children[i].style.display = 'none';
			} else {
				var selectedid = children[i].id;
			}
		}
	}
	var toc = document.createElement( 'ul' );
	toc.id = 'preftoc';
	toc.selectedid = selectedid;
	for ( i = 0; i < sections.length; i++ ) {
		var li = document.createElement( 'li' );
		if ( i === 0 ) {
			li.className = 'selected';
		}
		var a = document.createElement( 'a' );
		a.href = '#' + sections[i].secid;
		a.onmousedown = a.onclick = uncoversection;
		a.appendChild( document.createTextNode( sections[i].text ) );
		a.secid = sections[i].secid;
		li.appendChild( a );
		toc.appendChild( li );
	}
	prefform.parentNode.insertBefore( toc, prefform.parentNode.childNodes[0] );
	document.getElementById( 'prefsubmit' ).id = 'prefcontrol';
}

function uncoversection() {
	var oldsecid = this.parentNode.parentNode.selectedid;
	var newsec = document.getElementById( this.secid );
	if ( oldsecid != this.secid ) {
		var ul = document.getElementById( 'preftoc' );
		document.getElementById( oldsecid ).style.display = 'none';
		newsec.style.display = 'block';
		ul.selectedid = this.secid;
		var lis = ul.getElementsByTagName( 'li' );
		for ( var i = 0; i< lis.length; i++ ) {
			lis[i].className = '';
		}
		this.parentNode.className = 'selected';
	}
	return false;
}

// Timezone stuff
// tz in format [+-]HHMM
function checkTimezone( tz, msg ) {
	var localclock = new Date();
	// returns negative offset from GMT in minutes
	var tzRaw = localclock.getTimezoneOffset();
	var tzHour = Math.floor( Math.abs( tzRaw ) / 60 );
	var tzMin = Math.abs( tzRaw ) % 60;
	var tzString = ( ( tzRaw >= 0 ) ? '-' : '+' ) + ( ( tzHour < 10 ) ? '0' : '' ) + tzHour + ( ( tzMin < 10 ) ? '0' : '' ) + tzMin;
	if ( tz != tzString ) {
		var junk = msg.split('$1');
		document.write( junk[0] + 'UTC' + tzString + junk[1] );
	}
}

function timezoneSetup() {
	var tzSelect = document.getElementById( 'mw-input-timecorrection' );
	var tzTextbox = document.getElementById( 'mw-input-timecorrection-other' );

	if ( tzSelect && tzTextbox ) {
		addHandler( tzSelect, 'change', function( e ) { updateTimezoneSelection( false ); } );
		addHandler( tzTextbox, 'blur', function( e ) { updateTimezoneSelection( true ); } );
	}

	updateTimezoneSelection( false );
}

// in [-]HH:MM format...
// won't yet work with non-even tzs
function fetchTimezone() {
	// FIXME: work around Safari bug
	var localclock = new Date();
	// returns negative offset from GMT in minutes
	var tzRaw = localclock.getTimezoneOffset();
	var tzHour = Math.floor( Math.abs( tzRaw ) / 60 );
	var tzMin = Math.abs( tzRaw ) % 60;
	var tzString = ( ( tzRaw >= 0 ) ? '-' : '' ) + ( ( tzHour < 10 ) ? '0' : '' ) + tzHour +
		':' + ( ( tzMin < 10 ) ? '0' : '' ) + tzMin;
	return tzString;
}

function guessTimezone() {
	var textbox = document.getElementById( 'mw-input-timecorrection-other' );
	var selector = document.getElementById( 'mw-input-timecorrection' );

	selector.value = 'other';
	textbox.value = fetchTimezone();
	textbox.disabled = false; // The changed handler doesn't trip, obviously.
	updateTimezoneSelection( true );
}

function updateTimezoneSelection( force_offset ) {
	var selector = document.getElementById( 'mw-input-timecorrection' );

	if ( selector.value == 'guess' ) {
		return guessTimezone();
	}

	var textbox = document.getElementById( 'mw-input-timecorrection-other' );
	var localtimeHolder = document.getElementById( 'wpLocalTime' );
	var servertime = document.getElementsByName( 'wpServerTime' )[0].value;
	var minDiff = 0;

	// Compatibility code.
	if ( !selector.value ) {
		selector.value = selector.options[selector.selectedIndex].value;
	}

	// Handle force_offset
	if ( force_offset ) {
		selector.value = 'other';
	}

	// Get min_diff
	if ( selector.value == 'other' ) {
		// Grab data from the textbox, parse it.
		var diffArr = textbox.value.split(':');
		if ( diffArr.length == 1 ) {
			// Specification is of the form [-]XX
			minDiff = parseInt( diffArr[0], 10 ) * 60;
		} else {
			// Specification is of the form [-]XX:XX
			minDiff = Math.abs( parseInt( diffArr[0], 10 ) ) * 60 + parseInt( diffArr[1], 10 );
			if ( parseInt( diffArr[0], 10 ) < 0 ) {
				minDiff = -minDiff;
			}
		}
	} else {
		// Grab data from the selector value
		var diffArr = selector.value.split('|');
		minDiff = parseInt( diffArr[1], 10 );
	}

	// Gracefully handle non-numbers.
	if ( isNaN( minDiff ) ) {
		minDiff = 0;
	}

	// Determine local time from server time and minutes difference, for display.
	var localTime = parseInt( servertime, 10 ) + minDiff;

	// Bring time within the [0,1440) range.
	while ( localTime < 0 ) {
		localTime += 1440;
	}
	while ( localTime >= 1440 ) {
		localTime -= 1440;
	}

	// Split to hour and minute
	var hour = String( Math.floor( localTime / 60 ) );
	if ( hour.length < 2 ) {
		hour = '0' + hour;
	}
	var min = String(localTime%60);
	if ( min.length < 2 ) {
		min = '0' + min;
	}
	changeText( localtimeHolder, hour + ':' + min );

	// If the user selected from the drop-down, fill the offset field.
	if ( selector.value != 'other' ) {
		hour = String( Math.abs( Math.floor( minDiff / 60 ) ) );
		if ( hour.length < 2 ) {
			hour = '0' + hour;
		}
		if ( minDiff < 0 ) {
			hour = '-' + hour;
		}
		min = String(minDiff%60);
		if ( min.length < 2 ) {
			min = '0' + min;
		}
		textbox.value = hour + ':' + min;
	}
}

addOnloadHook( timezoneSetup );
addOnloadHook( tabbedprefs );
