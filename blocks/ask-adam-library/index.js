/**
 * Ask Adam Doc It Library block — editor script.
 *
 * No build step: this file is shipped as-is to the editor and uses the
 * globals exposed by wp-blocks, wp-element, wp-block-editor, wp-components,
 * and wp-i18n. The block is rendered server-side by AADI_Block, so save()
 * returns null and the editor shows a Placeholder + Inspector controls.
 */
( function ( blocks, element, blockEditor, components, i18n ) {
	'use strict';

	var el                = element.createElement;
	var useBlockProps     = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody         = components.PanelBody;
	var TextControl       = components.TextControl;
	var ToggleControl     = components.ToggleControl;
	var RangeControl      = components.RangeControl;
	var SelectControl     = components.SelectControl;
	var Placeholder       = components.Placeholder;
	var __                = i18n.__;

	blocks.registerBlockType( 'ask-adam-doc-it/library', {
		edit: function ( props ) {
			var attributes    = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps    = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'Library Settings', 'ask-adam-doc-it' ),
							initialOpen: true
						},
						el( RangeControl, {
							label: __( 'Documents per page', 'ask-adam-doc-it' ),
							value: attributes.perPage,
							onChange: function ( v ) {
								setAttributes( { perPage: v } );
							},
							min: 1,
							max: 50
						} ),
						el( ToggleControl, {
							label: __( 'Show search bar', 'ask-adam-doc-it' ),
							checked: attributes.showSearch,
							onChange: function ( v ) {
								setAttributes( { showSearch: v } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Columns', 'ask-adam-doc-it' ),
							value: String( attributes.columns ),
							options: [
								{ label: '1', value: '1' },
								{ label: '2', value: '2' }
							],
							onChange: function ( v ) {
								setAttributes( { columns: parseInt( v, 10 ) } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Search mode', 'ask-adam-doc-it' ),
							value: attributes.mode,
							options: [
								{ label: __( 'Auto', 'ask-adam-doc-it' ), value: 'auto' },
								{ label: __( 'AI', 'ask-adam-doc-it' ), value: 'ai' },
								{ label: __( 'Keyword only', 'ask-adam-doc-it' ), value: 'core' }
							],
							onChange: function ( v ) {
								setAttributes( { mode: v } );
							}
						} ),
						el( TextControl, {
							label: __( 'Category slug (optional)', 'ask-adam-doc-it' ),
							value: attributes.category,
							onChange: function ( v ) {
								setAttributes( { category: v } );
							}
						} )
					)
				),
				el(
					Placeholder,
					{
						icon: 'media-document',
						label: __( 'Ask Adam Doc It Library', 'ask-adam-doc-it' ),
						instructions: __(
							'Your document library will appear here on the frontend.',
							'ask-adam-doc-it'
						)
					}
				)
			);
		},
		save: function () {
			// Dynamic block — rendered server-side via AADI_Block::render_callback.
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
);
