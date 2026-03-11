import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { createElement, Fragment } from '@wordpress/element';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import '../scss/editor.scss';

// Prevent multiple executions
if ( ! window.simpleBlockVisibilityFiltersAdded ) {
	window.simpleBlockVisibilityFiltersAdded = true;

	const rawSettings = window.simpblvSettings || {};
	const bp    = rawSettings.breakpoints || {};
	const order = rawSettings.order || [ 'mobile', 'tablet', 'laptop', 'desktop' ];

	/**
	 * Map breakpoint key to block attribute name and label
	 */
	const bpConfig = {
		mobile:       { attr: 'hideOnMobile',       label: __( 'Hide on Mobile', 'simple-block-visibility' ) },
		tablet:       { attr: 'hideOnTablet',       label: __( 'Hide on Tablet', 'simple-block-visibility' ) },
		contentWidth: { attr: 'hideOnContentWidth', label: __( 'Hide on Content Width', 'simple-block-visibility' ) },
		laptop:       { attr: 'hideOnLaptop',       label: __( 'Hide on Laptop', 'simple-block-visibility' ) },
		wideWidth:    { attr: 'hideOnWideWidth',    label: __( 'Hide on Wide Width', 'simple-block-visibility' ) },
		desktop:      { attr: 'hideOnDesktop',      label: __( 'Hide on Desktop', 'simple-block-visibility' ) },
	};

	/**
	 * Format a breakpoint range for display
	 */
	function formatRange( range ) {
		if ( range.min === 0 ) return `(\u2264 ${ range.max }px)`;
		if ( ! range.max )     return `(\u2265 ${ range.min }px)`;
		return `(${ range.min }px \u2013 ${ range.max }px)`;
	}

	/**
	 * Add visibility attributes to all blocks
	 */
	function addVisibilityAttributes( blockSettings ) {
		return {
			...blockSettings,
			attributes: {
				...blockSettings.attributes,
				hideOnMobile:       { type: 'boolean', default: false },
				hideOnTablet:       { type: 'boolean', default: false },
				hideOnLaptop:       { type: 'boolean', default: false },
				hideOnDesktop:      { type: 'boolean', default: false },
				hideOnContentWidth: { type: 'boolean', default: false },
				hideOnWideWidth:    { type: 'boolean', default: false },
			},
		};
	}

	/**
	 * Add visibility toggle controls to the block inspector
	 */
	const withVisibilityControls = createHigherOrderComponent( ( BlockEdit ) => {
		return ( props ) => {
			const { attributes, setAttributes } = props;

			const toggles = order.map( ( key ) => {
				const config = bpConfig[ key ];
				const range  = bp[ key ];
				if ( ! config || ! range || ! range.enabled ) return null;

				return createElement( ToggleControl, {
					key,
					label: config.label + ' ' + formatRange( range ),
					checked: !! attributes[ config.attr ],
					onChange: ( value ) => setAttributes( { [ config.attr ]: value } ),
				} );
			} );

			return createElement(
				Fragment,
				null,
				createElement( BlockEdit, props ),
				createElement(
					InspectorControls,
					{ group: 'settings' },
					createElement(
						PanelBody,
						{
							title: __( 'Visibility', 'simple-block-visibility' ),
							initialOpen: false,
						},
						...toggles,
					)
				)
			);
		};
	}, 'withVisibilityControls' );

	/**
	 * Add visibility CSS classes to the block's saved HTML output
	 */
	function addVisibilityClasses( props, _blockType, attributes ) {
		const { hideOnMobile, hideOnTablet, hideOnLaptop, hideOnDesktop, hideOnContentWidth, hideOnWideWidth } = attributes;

		const classes = [];
		if ( hideOnMobile )       classes.push( 'sblv-hide-mobile' );
		if ( hideOnTablet )       classes.push( 'sblv-hide-tablet' );
		if ( hideOnLaptop )       classes.push( 'sblv-hide-laptop' );
		if ( hideOnDesktop )      classes.push( 'sblv-hide-desktop' );
		if ( hideOnContentWidth ) classes.push( 'sblv-hide-content-width' );
		if ( hideOnWideWidth )    classes.push( 'sblv-hide-wide-width' );

		if ( classes.length === 0 ) {
			return props;
		}

		return {
			...props,
			className: props.className
				? `${ props.className } ${ classes.join( ' ' ) }`
				: classes.join( ' ' ),
		};
	}

	addFilter( 'blocks.registerBlockType', 'simple_block_visibility/visibility-attributes', addVisibilityAttributes );
	addFilter( 'editor.BlockEdit', 'simple_block_visibility/with-visibility-controls', withVisibilityControls );
	addFilter( 'blocks.getSaveContent.extraProps', 'simple_block_visibility/add-visibility-classes', addVisibilityClasses );
}
