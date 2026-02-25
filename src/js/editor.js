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

	const settings = window.simpblvSettings || { mobileBreakpoint: 550, tabletBreakpoint: 768, laptopBreakpoint: 1440 };

	/**
	 * Add visibility attributes
	 */
	function addVisibilityAttributes( blockSettings ) {
		return {
			...blockSettings,
			attributes: {
				...blockSettings.attributes,
				hideOnMobile: {
					type: 'boolean',
					default: false,
				},
				hideOnTablet: {
					type: 'boolean',
					default: false,
				},
				hideOnLaptop: {
					type: 'boolean',
					default: false,
				},
				hideOnDesktop: {
					type: 'boolean',
					default: false,
				},
			},
		};
	}

	/**
	 * Add visibility controls to
	 */
	const withVisibilityControls = createHigherOrderComponent( ( BlockEdit ) => {
		return ( props ) => {
			const { attributes, setAttributes } = props;
			const { hideOnMobile, hideOnTablet, hideOnLaptop, hideOnDesktop } = attributes;

			const mobileMax  = parseInt(settings.mobileBreakpoint, 10) || 550;
			const tabletMin  = mobileMax + 1;
			const tabletMax  = parseInt(settings.tabletBreakpoint, 10) || 768;
			const laptopMin  = tabletMax + 1;
			const laptopMax  = parseInt(settings.laptopBreakpoint, 10) || 1440;
			const desktopMin = laptopMax + 1;

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
						createElement( ToggleControl, {
							label: __( 'Hide on Mobile', 'simple-block-visibility' ) + ` (≤ ${ mobileMax }px)`,
							checked: hideOnMobile,
							onChange: ( value ) => setAttributes( { hideOnMobile: value } ),
						} ),
						createElement( ToggleControl, {
							label: __( 'Hide on Tablet', 'simple-block-visibility' ) + ` (${ tabletMin }px – ${ tabletMax }px)`,
							checked: hideOnTablet,
							onChange: ( value ) => setAttributes( { hideOnTablet: value } ),
						} ),
						createElement( ToggleControl, {
							label: __( 'Hide on Laptop', 'simple-block-visibility' ) + ` (${ laptopMin }px – ${ laptopMax }px)`,
							checked: hideOnLaptop,
							onChange: ( value ) => setAttributes( { hideOnLaptop: value } ),
						} ),
						createElement( ToggleControl, {
							label: __( 'Hide on Desktop', 'simple-block-visibility' ) + ` (≥ ${ desktopMin }px)`,
							checked: hideOnDesktop,
							onChange: ( value ) => setAttributes( { hideOnDesktop: value } ),
						} ),
					)
				)
			);
		};
	}, 'withVisibilityControls' );

	/**
	 * Add visibility CSS classes to the block's saved HTML output
	 */
	function addVisibilityClasses( props, _blockType, attributes ) {
		const { hideOnMobile, hideOnTablet, hideOnLaptop, hideOnDesktop } = attributes;

		const classes = [];
		if ( hideOnMobile )  classes.push( 'sblv-hide-mobile' );
		if ( hideOnTablet )  classes.push( 'sblv-hide-tablet' );
		if ( hideOnLaptop )  classes.push( 'sblv-hide-laptop' );
		if ( hideOnDesktop ) classes.push( 'sblv-hide-desktop' );

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
