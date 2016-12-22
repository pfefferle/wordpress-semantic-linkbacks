<?php

/**
 * Get a Count of Linkbacks by Type
 *
 * @param string $type the comment type
 * @param int $post_id the id of the post
 *
 * @return the number of matching linkbacks
 */
function get_linkbacks_number( $type = null, $post_id = 0 ) {
	$args = array(
		'post_id'	=> $post_id,
		'count'	 	=> true,
		'status'	=> 'approve',
	);

	if ( $type ) { // use type if set
		$args['meta_query'] = array( array( 'key' => 'semantic_linkbacks_type', 'value' => $type ) );
	} else { // check only if type exists
		$args['meta_query'] = array( array( 'key' => 'semantic_linkbacks_type', 'compare' => 'EXISTS' ) );
	}

	$comments = get_comments( $args );
	if ( $comments ) {
		return $comments;
	} else {
		return 0;
	}
}

/**
 * Returns comments of linkback type
 *
 * @param string $type the comment type
 * @param int $post_id the id of the post
 *
 * @return the matching linkback "comments"
 */
function get_linkbacks( $type = null, $post_id = 0 ) {
	$args = array(
		'post_id'	=> $post_id,
		'status'	=> 'approve',
	);

	if ( $type ) { // use type if set
		$args['meta_query'] = array( array( 'key' => 'semantic_linkbacks_type', 'value' => $type ) );
	} else { // check only if type exists
		$args['meta_query'] = array( array( 'key' => 'semantic_linkbacks_type', 'compare' => 'EXISTS' ) );
	}

	return get_comments( $args );
}
