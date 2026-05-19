/* global jQuery, ptaiPublic */
/**
 * PaperTrail AI — Public scripts.
 *
 * Handles AJAX search submission for the front-end search bar.
 *
 * @package PaperTrail_AI
 */
( function ( $, ptaiPublic ) {
	'use strict';

	if ( typeof ptaiPublic === 'undefined' || ! ptaiPublic ) {
		return;
	}

	var $forms = $( '.ptai-search-form' );
	if ( ! $forms.length ) {
		return;
	}

	/* 1. Per-form AJAX submit handler
	------------------------------------------------------------ */
	// Use $(this) inside the handler so each submit is scoped to the
	// form that fired it. A page can render multiple [papertrail]
	// libraries; each must search and update only its own list.
	$forms.on( 'submit', function ( e ) {
		var $form    = $( this );
		var $btn     = $form.find( '.ptai-search-btn' );
		var $library = $form.closest( '.ptai-document-library' );

		if ( ! $library.length ) {
			// No library wrapper — let the form submit normally.
			return;
		}

		e.preventDefault();

		var originalBtn = $btn.data( 'ptai-original-text' );
		if ( typeof originalBtn === 'undefined' ) {
			originalBtn = $btn.text();
			$btn.data( 'ptai-original-text', originalBtn );
		}

		var query    = $form.find( '.ptai-search-input' ).first().val() || '';
		var category = $form.find( '[name="ptai_category"]' ).val() || 0;
		var mode     = $form.find( '[name="ptai_mode"]' ).val() || 'auto';

		$btn.prop( 'disabled', true ).text( ptaiPublic.strings.searching );

		$.post( ptaiPublic.ajax_url, {
			action:   'ptai_search',
			nonce:    ptaiPublic.nonce,
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
				$btn.prop( 'disabled', false ).text( originalBtn || ptaiPublic.strings.search );
			} );
	} );

	/* 2. Ensure the library has a result list; remove stale state
	------------------------------------------------------------ */
	function ensureList( $library ) {
		// Drop any pre-existing "no results" paragraph from the
		// initial server render — otherwise it sticks around behind
		// fresh AJAX results.
		$library.find( '.ptai-no-results' ).remove();

		// AJAX always asks for page 1, so any pagination from the
		// initial server render no longer applies. Drop it.
		$library.find( '.ptai-pagination' ).remove();

		var $list = $library.find( '.ptai-file-list' );
		if ( ! $list.length ) {
			$list = $( '<ul class="ptai-file-list ptai-columns-1"></ul>' ).appendTo( $library );
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
					'<a class="ptai-download-link" href="' + escHtml( post.download_url ) + '">' +
						escHtml( ptaiPublic.strings.download ) +
					'</a>';
			}
			html +=
				'<li class="ptai-file-card">' +
					'<div class="ptai-file-icon">' +
						'<span class="' + escHtml( post.icon_class || 'ptai-icon-file' ) + '" aria-hidden="true"></span>' +
					'</div>' +
					'<div class="ptai-file-info">' +
						'<a class="ptai-file-title" href="' + escHtml( post.permalink ) + '">' +
							escHtml( post.title ) +
						'</a>' +
						'<span class="ptai-file-meta">' + escHtml( post.meta_text || '' ) + '</span>' +
						downloadLink +
					'</div>' +
				'</li>';
		} );
		$list.html( html );
	}

	function renderEmpty( $library ) {
		var $list = ensureList( $library );
		$list.html(
			'<li class="ptai-no-results">' +
			escHtml( ptaiPublic.strings.no_results ) +
			'</li>'
		);
	}

	function renderError( $library ) {
		var $list = ensureList( $library );
		$list.html(
			'<li class="ptai-no-results">' +
			escHtml( ptaiPublic.strings.error ) +
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

}( jQuery, window.ptaiPublic || null ) );
