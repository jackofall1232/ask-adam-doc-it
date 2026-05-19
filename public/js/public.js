/* global jQuery, aadiPublic */
/**
 * Ask Adam Doc It — Public scripts.
 *
 * Handles AJAX search submission for the front-end search bar.
 *
 * @package Ask_Adam_Doc_It
 */
( function ( $, aadiPublic ) {
	'use strict';

	if ( typeof aadiPublic === 'undefined' || ! aadiPublic ) {
		return;
	}

	var $forms = $( '.aadi-search-form' );
	if ( ! $forms.length ) {
		return;
	}

	/* 1. Per-form AJAX submit handler
	------------------------------------------------------------ */
	// Use $(this) inside the handler so each submit is scoped to the
	// form that fired it. A page can render multiple [ask_adam_doc_it]
	// libraries; each must search and update only its own list.
	$forms.on( 'submit', function ( e ) {
		var $form    = $( this );
		var $btn     = $form.find( '.aadi-search-btn' );
		var $library = $form.closest( '.aadi-document-library' );

		if ( ! $library.length ) {
			// No library wrapper — let the form submit normally.
			return;
		}

		e.preventDefault();

		var originalBtn = $btn.data( 'aadi-original-text' );
		if ( typeof originalBtn === 'undefined' ) {
			originalBtn = $btn.text();
			$btn.data( 'aadi-original-text', originalBtn );
		}

		var query    = $form.find( '.aadi-search-input' ).first().val() || '';
		var category = $form.find( '[name="aadi_category"]' ).val() || 0;
		var mode     = $form.find( '[name="aadi_mode"]' ).val() || 'auto';

		$btn.prop( 'disabled', true ).text( aadiPublic.strings.searching );

		$.post( aadiPublic.ajax_url, {
			action:   'aadi_search',
			nonce:    aadiPublic.nonce,
			query:    query,
			category: category,
			page:     1,
			mode:     mode
		} )
			.done( function ( response ) {
				if ( response && response.success && response.data && response.data.posts && response.data.posts.length ) {
					renderResults( $library, response.data.posts );
				} else {
					renderEmpty( $library );
				}
			} )
			.fail( function () {
				renderError( $library );
			} )
			.always( function () {
				$btn.prop( 'disabled', false ).text( originalBtn || aadiPublic.strings.search );
			} );
	} );

	/* 2. Ensure the library has a result list; remove stale state
	------------------------------------------------------------ */
	function ensureList( $library ) {
		// Drop any pre-existing "no results" paragraph from the
		// initial server render — otherwise it sticks around behind
		// fresh AJAX results.
		$library.find( '.aadi-no-results' ).remove();

		// AJAX always asks for page 1, so any pagination from the
		// initial server render no longer applies. Drop it.
		$library.find( '.aadi-pagination' ).remove();

		var $list = $library.find( '.aadi-file-list' );
		if ( ! $list.length ) {
			$list = $( '<ul class="aadi-file-list aadi-columns-1"></ul>' ).appendTo( $library );
		}
		return $list;
	}

	/* 3. Renderers
	------------------------------------------------------------ */
	function renderResults( $library, posts ) {
		var $list = ensureList( $library );
		var html  = '';
		$.each( posts, function ( i, post ) {
			var downloadLink = '';
			if ( post.has_file && post.download_url ) {
				downloadLink =
					'<a class="aadi-download-link" href="' + escHtml( post.download_url ) + '">' +
						escHtml( aadiPublic.strings.download ) +
					'</a>';
			}
			html +=
				'<li class="aadi-file-card">' +
					'<div class="aadi-file-icon">' +
						'<span class="' + escHtml( post.icon_class || 'aadi-icon-file' ) + '" aria-hidden="true"></span>' +
					'</div>' +
					'<div class="aadi-file-info">' +
						'<a class="aadi-file-title" href="' + escHtml( post.permalink ) + '">' +
							escHtml( post.title ) +
						'</a>' +
						'<span class="aadi-file-meta">' + escHtml( post.meta_text || '' ) + '</span>' +
						downloadLink +
					'</div>' +
				'</li>';
		} );
		$list.html( html );
	}

	function renderEmpty( $library ) {
		var $list = ensureList( $library );
		$list.html(
			'<li class="aadi-no-results">' +
			escHtml( aadiPublic.strings.no_results ) +
			'</li>'
		);
	}

	function renderError( $library ) {
		var $list = ensureList( $library );
		$list.html(
			'<li class="aadi-no-results">' +
			escHtml( aadiPublic.strings.error ) +
			'</li>'
		);
	}

	/* 4. Minimal XSS guard for JS-rendered output
	------------------------------------------------------------ */
	function escHtml( str ) {
		if ( typeof str !== 'string' ) {
			return '';
		}
		return str
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

}( jQuery, window.aadiPublic || null ) );
