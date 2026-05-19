/* global ptaiAdmin, wp */
( function ( $, ptaiAdmin ) {
	'use strict';

	/* 1. Media picker — file attachment
	--------------------------------------------------------- */
	var strings = ( window.ptaiAdmin && window.ptaiAdmin.strings ) || {};

	function openMediaPicker() {
		var frame = wp.media( {
			title:    strings.select_file || 'Select or upload a file',
			button:   { text: strings.use_this_file || 'Use this file' },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$( '#ptai_file_id' ).val( attachment.id );

			var $current = $( '#ptai-file-meta-current' );
			var ext      = ( attachment.filename || '' ).split( '.' ).pop().toUpperCase();
			$current.html(
				'<p><strong>' + $( '<div/>' ).text( attachment.filename || attachment.title || '' ).html() + '</strong></p>' +
				'<ul class="ptai-meta-list">' +
					'<li>Extension: ' + $( '<div/>' ).text( ext ).html() + '</li>' +
					'<li>Type: '      + $( '<div/>' ).text( attachment.mime || '' ).html() + '</li>' +
					( attachment.filesizeHumanReadable
						? '<li>Size: ' + $( '<div/>' ).text( attachment.filesizeHumanReadable ).html() + '</li>'
						: '' ) +
				'</ul>'
			);

			$( '#ptai-attach-file' ).text( strings.replace_file || 'Replace File' );
		} );

		frame.open();
	}

	function removeAttachedFile() {
		if ( ! window.confirm( strings.confirm_remove || 'Remove the attached file?' ) ) {
			return;
		}
		$( '#ptai_file_id' ).val( '0' );
		$( '#ptai-file-meta-current' ).html(
			'<p><em>' + ( strings.no_file_attached || 'No file attached.' ) + '</em></p>'
		);
		$( '#ptai-attach-file' ).text( strings.select_file || 'Attach File' );
		$( '#ptai-remove-file' ).remove();
	}

	$( function () {
		$( document ).on( 'click', '#ptai-attach-file', function ( e ) {
			e.preventDefault();
			if ( typeof wp === 'undefined' || ! wp.media ) {
				return;
			}
			openMediaPicker();
		} );

		$( document ).on( 'click', '#ptai-remove-file', function ( e ) {
			e.preventDefault();
			removeAttachedFile();
		} );
	} );

	/* 2. Character counter — AI Search Summary textarea
	--------------------------------------------------------- */
	var $summary = $( '#ptai_doc_summary' );
	if ( $summary.length ) {
		$summary.on( 'input', function () {
			var len      = $( this ).val().length;
			var $wrap    = $( this ).closest( '.ptai-doc-summary-wrap' );
			var $counter = $wrap.find( '.ptai-char-counter' );
			$wrap.find( '.ptai-char-count' ).text( len );
			$counter
				.toggleClass( 'ptai-char-counter--warning',
					len > 400 && len <= 500 )
				.toggleClass( 'ptai-char-counter--over', len > 500 );
		} ).trigger( 'input' );
	}

	/* 3. Dismiss AI active notice via AJAX
	--------------------------------------------------------- */
	$( document ).on(
		'click',
		'.ptai-ai-active-notice .notice-dismiss',
		function () {
			var nonce = $( this )
				.closest( '.ptai-ai-active-notice' )
				.data( 'nonce' );
			if ( ! nonce ) {
				return;
			}
			$.post( ptaiAdmin.ajax_url, {
				action: 'ptai_dismiss_ai_notice',
				nonce:  nonce
			} );
			// WP's native dismiss handler removes the element.
		}
	);

	/* 4. Settings page tabs
	--------------------------------------------------------- */
	$( function () {
		( function initTabs() {
			var $wrap   = $( '.ptai-settings-wrap' );
			var $nav    = $wrap.find( '.ptai-tab-nav' );
			var $panels = $wrap.find( '.ptai-tab-panel' );
			var $btns   = $nav.find( '.ptai-tab-btn' );

			if ( ! $nav.length || ! $panels.length || ! $btns.length ) {
				return;
			}

			// If another plugin injected an extra section the panel and
			// button counts will diverge — bail safely so the no-JS
			// fallback (all panels visible, nav hidden) stays in effect.
			if ( $panels.length !== $btns.length ) {
				return;
			}

			$wrap.removeClass( 'ptai-no-js' );
			$wrap.addClass( 'ptai-tabs-ready' );

			$btns.attr( 'tabindex', '-1' );
			$btns.filter( '.ptai-tab-btn--active' ).attr( 'tabindex', '0' );

			function activateTab( $btn, moveFocus ) {
				if ( ! $btn || ! $btn.length ) {
					return;
				}
				var tabId = $btn.data( 'tab' );
				if ( ! tabId ) {
					return;
				}

				$btns
					.removeClass( 'ptai-tab-btn--active' )
					.attr( 'aria-selected', 'false' )
					.attr( 'tabindex', '-1' );
				$btn
					.addClass( 'ptai-tab-btn--active' )
					.attr( 'aria-selected', 'true' )
					.attr( 'tabindex', '0' );

				$panels.removeClass( 'ptai-tab-panel--active' );
				$wrap.find( '#' + tabId ).addClass( 'ptai-tab-panel--active' );

				// Expose the active tab id on the wrap so CSS can react
				// (e.g. hiding the Save Settings button on the Help tab,
				// which is static documentation and has no form fields).
				$wrap.attr( 'data-active-tab', tabId );

				if ( moveFocus ) {
					$btn.trigger( 'focus' );
				}

				try {
					sessionStorage.setItem( 'ptai_active_tab', tabId );
				} catch ( e ) { /* storage unavailable */ }
			}

			$nav.on( 'click', '.ptai-tab-btn', function () {
				activateTab( $( this ), false );
			} );

			$nav.on( 'keydown', '.ptai-tab-btn', function ( e ) {
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
				var saved = sessionStorage.getItem( 'ptai_active_tab' );
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

} ( jQuery, window.ptaiAdmin || {} ) );
