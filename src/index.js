import { registerBlockVariation } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { createElement, useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';

import {
	RELATED_POST_PARAMETER,
	RELATED_QUERY_VARIATION,
	VARIATION_NAMESPACE,
} from './variation';

registerBlockVariation( 'core/query', RELATED_QUERY_VARIATION );

function RelatedQueryBlockEdit( { BlockEdit, ...props } ) {
	const { attributes, setAttributes } = props;
	const { query = {} } = attributes;
	const editorContext = useSelect( ( select ) => {
		const editor = select( 'core/editor' );

		return {
			postId: editor?.getCurrentPostId(),
			postType: editor?.getCurrentPostType(),
		};
	}, [] );
	const sourcePostId = Number( editorContext.postId );
	const hasUsablePostContext =
		Number.isInteger( sourcePostId ) &&
		0 < sourcePostId &&
		'string' === typeof editorContext.postType &&
		! editorContext.postType.startsWith( 'wp_' );

	useEffect( () => {
		if ( ! hasUsablePostContext ) {
			return;
		}

		if (
			sourcePostId === query[ RELATED_POST_PARAMETER ] &&
			editorContext.postType === query.postType
		) {
			return;
		}

		setAttributes( {
			query: {
				...query,
				postType: editorContext.postType,
				[ RELATED_POST_PARAMETER ]: sourcePostId,
			},
		} );
	}, [
		editorContext.postType,
		hasUsablePostContext,
		query,
		setAttributes,
		sourcePostId,
	] );

	return createElement( BlockEdit, props );
}

const withRelatedQueryContext = createHigherOrderComponent(
	( BlockEdit ) =>
		function RelatedQueryContextWrapper( props ) {
			const isRelatedQuery =
				'core/query' === props.name &&
				VARIATION_NAMESPACE === props.attributes.namespace;

			if ( ! isRelatedQuery ) {
				return createElement( BlockEdit, props );
			}

			return createElement( RelatedQueryBlockEdit, {
				BlockEdit,
				...props,
			} );
		},
	'withRelatedQueryContext'
);

addFilter(
	'editor.BlockEdit',
	'od-related-query/with-related-query-context',
	withRelatedQueryContext
);
