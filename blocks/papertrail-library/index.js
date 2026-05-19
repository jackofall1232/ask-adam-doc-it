/**
 * PaperTrail AI Library block — editor script.
 *
 * No build step: this file is shipped as-is to the editor and uses the
 * globals exposed by wp-blocks, wp-element, wp-block-editor, wp-components,
 * and wp-i18n. The block is rendered server-side by PTAI_Block, so save()
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

	blocks.registerBlockType( 'papertrail-ai/library', {
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
							title: __( 'Library Settings', 'papertrail-ai' ),
							initialOpen: true
						},
						el( RangeControl, {
							label: __( 'Documents per page', 'papertrail-ai' ),
							value: attributes.perPage,
							onChange: function ( v ) {
								setAttributes( { perPage: v } );
							},
							min: 1,
							max: 50
						} ),
						el( ToggleControl, {
							label: __( 'Show search bar', 'papertrail-ai' ),
							checked: attributes.showSearch,
							onChange: function ( v ) {
								setAttributes( { showSearch: v } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Columns', 'papertrail-ai' ),
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
							label: __( 'Search mode', 'papertrail-ai' ),
							value: attributes.mode,
							options: [
								{ label: __( 'Auto', 'papertrail-ai' ), value: 'auto' },
								{ label: __( 'AI', 'papertrail-ai' ), value: 'ai' },
								{ label: __( 'Keyword only', 'papertrail-ai' ), value: 'core' }
							],
							onChange: function ( v ) {
								setAttributes( { mode: v } );
							}
						} ),
						el( TextControl, {
							label: __( 'Category slug (optional)', 'papertrail-ai' ),
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
						label: __( 'PaperTrail AI Library', 'papertrail-ai' ),
						instructions: __(
							'Your document library will appear here on the frontend.',
							'papertrail-ai'
						)
					}
				)
			);
		},
		save: function () {
			// Dynamic block — rendered server-side via PTAI_Block::render_callback.
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
