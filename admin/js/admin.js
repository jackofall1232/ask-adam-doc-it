/* global aadiAdmin, wp */
( function ( $, aadiAdmin ) {
	'use strict';

	/* 1. Media picker — file attachment
	--------------------------------------------------------- */
	var strings = ( window.aadiAdmin && window.aadiAdmin.strings ) || {};

	function openMediaPicker() {
		var frame = wp.media( {
			title:    strings.select_file || 'Select or upload a file',
			button:   { text: strings.use_this_file || 'Use this file' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#aadi_file_id' ).val( attachment.id );

			var $current = $( '#aadi-file-meta-current' );
			var ext      = ( attachment.filename || '' ).split( '.' ).pop().toUpperCase();
			$current.html(
				'<p><strong>' + $( '<div/>' ).text( attachment.filename || attachment.title || '' ).html() + '</strong></p>' +
				'<ul class="aadi-meta-list">' +
					'<li>Extension: ' + $( '<div/>' ).text( ext ).html() + '</li>' +
					'<li>Type: '      + $( '<div/>' ).text( attachment.mime || '' ).html() + '</li>' +
					( attachment.filesizeHumanReadable
						? '<li>Size: ' + $( '<div/>' ).text( attachment.filesizeHumanReadable ).html() + '</li>'
						: '' ) +
				'</ul>'
			);

			$( '#aadi-attach-file' ).text( strings.replace_file || 'Replace File' );
		} );

		frame.open();
	}

	function removeAttachedFile() {
		if ( ! window.confirm( strings.confirm_remove || 'Remove the attached file?' ) ) {
			return;
		}
		$( '#aadi_file_id' ).val( '0' );
		$( '#aadi-file-meta-current' ).html(
			'<p><em>' + ( strings.no_file_attached || 'No file attached.' ) + '</em></p>'
		);
		$( '#aadi-attach-file' ).text( strings.select_file || 'Attach File' );
		$( '#aadi-remove-file' ).remove();
	}

	$( function () {
		$( document ).on( 'click', '#aadi-attach-file', function ( e ) {
			e.preventDefault();
			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}
			openMediaPicker();
		} );

		$( document ).on( 'click', '#aadi-remove-file', function ( e ) {
			e.preventDefault();
			removeAttachedFile();
		} );
	} );

	/* 2. Character counter — AI Search Summary textarea
	--------------------------------------------------------- */
	var $summary = $( '#aadi_doc_summary' );
	if ( $summary.length ) {
		$summary.on( 'input', function () {
			var len      = $( this ).val().length;
			var $wrap    = $( this ).closest( '.aadi-doc-summary-wrap' );
			var $counter = $wrap.find( '.aadi-char-counter' );
			$wrap.find( '.aadi-char-count' ).text( len );
			$counter
				.toggleClass( 'aadi-char-counter--warning',
					len > 400 && len <= 500 )
				.toggleClass( 'aadi-char-counter--over', len > 500 );
		} ).trigger( 'input' );
	}

	/* 3. Dismiss AI active notice via AJAX
	--------------------------------------------------------- */
	$( document ).on(
		'click',
		'.aadi-ai-active-notice .notice-dismiss',
		function () {
			var nonce = $( this )
				.closest( '.aadi-ai-active-notice' )
				.data( 'nonce' );
			if ( ! nonce ) {
				return;
			}
			$.post( aadiAdmin.ajax_url, {
				action: 'aadi_dismiss_ai_notice',
				nonce:  nonce
			} );
			// WP's native dismiss handler removes the element.
		}
	);

	/* 4. Settings page tabs
	--------------------------------------------------------- */
	$( function () {
		( function initTabs() {
			var $wrap   = $( '.aadi-settings-wrap' );
			var $nav    = $wrap.find( '.aadi-tab-nav' );
			var $panels = $wrap.find( '.aadi-tab-panel' );
			var $btns   = $nav.find( '.aadi-tab-btn' );

			if ( ! $nav.length || ! $panels.length || ! $btns.length ) {
				return;
			}

			// If another plugin injected an extra section the panel and
			// button counts will diverge — bail safely so the no-JS
			// fallback (all panels visible, nav hidden) stays in effect.
			if ( $panels.length !== $btns.length ) {
				return;
			}

			$wrap.removeClass( 'aadi-no-js' );
			$wrap.addClass( 'aadi-tabs-ready' );

			$btns.attr( 'tabindex', '-1' );
			$btns.filter( '.aadi-tab-btn--active' ).attr( 'tabindex', '0' );

			function activateTab( $btn, moveFocus ) {
				if ( ! $btn || ! $btn.length ) {
					return;
				}
				var tabId = $btn.data( 'tab' );
				if ( ! tabId ) {
					return;
				}

				$btns
					.removeClass( 'aadi-tab-btn--active' )
					.attr( 'aria-selected', 'false' )
					.attr( 'tabindex', '-1' );
				$btn
					.addClass( 'aadi-tab-btn--active' )
					.attr( 'aria-selected', 'true' )
					.attr( 'tabindex', '0' );

				$panels.removeClass( 'aadi-tab-panel--active' );
				$wrap.find( '#' + tabId ).addClass( 'aadi-tab-panel--active' );

				// Expose the active tab id on the wrap so CSS can react
				// (e.g. hiding the Save Settings button on the Help tab,
				// which is static documentation and has no form fields).
				$wrap.attr( 'data-active-tab', tabId );

				if ( moveFocus ) {
					$btn.trigger( 'focus' );
				}

				try {
					sessionStorage.setItem( 'aadi_active_tab', tabId );
				} catch ( e ) { /* storage unavailable */ }
			}

			$nav.on( 'click', '.aadi-tab-btn', function () {
				activateTab( $( this ), false );
			} );

			$nav.on( 'keydown', '.aadi-tab-btn', function ( e ) {
				var key  = e.key;
				var idx  = $btns.index( this );
				var last = $btns.length - 1;
				var next = -1;

				if ( 'ArrowRight' === key ) {
					next = idx === last ? 0 : idx + 1;
				} else if ( 'ArrowLeft' === key ) {
					next = 0 === idx ? last : idx - 1;
				} else if ( 'Home' === key ) {
					next = 0;
				} else if ( 'End' === key ) {
					next = last;
				}

				if ( next >= 0 ) {
					e.preventDefault();
					activateTab( $btns.eq( next ), true );
				}
			} );

			var restored = false;
			try {
				var saved = sessionStorage.getItem( 'aadi_active_tab' );
				if ( saved ) {
					// Filter on the existing collection instead of building an
					// attribute selector — avoids selector-injection issues if
					// the stored value contains quotes or special characters.
					var $target = $btns.filter( function () {
						return $( this ).data( 'tab' ) === saved;
					} );
					if ( $target.length ) {
						activateTab( $target.first(), false );
						restored = true;
					}
				}
			} catch ( e ) { /* storage unavailable */ }

			if ( ! restored ) {
				activateTab( $btns.first(), false );
			}
		}() );
	} );

} ( jQuery, window.aadiAdmin || {} ) );
