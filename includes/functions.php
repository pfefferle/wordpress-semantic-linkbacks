<?php
/**
 * Get a Count of Linkbacks by Type
 *
 * @param string $type the comment type
 * @param int $post_id the id of the post
 *
 * @return the number of matching linkbacks
 */
function get_linkbacks_number( $type = null, $post_id = null ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}
	$args = array(
		'post_id' => $post_id,
		'count'   => true,
		'status'  => 'approve',
	);

	if ( $type ) { // use type if set
		if ( 'mention' === $type ) {
			$args['type__not_in'] = 'comment';
			$args['meta_query']   = array(
				'relation' => 'OR',
				array(
					'key'   => 'semantic_linkbacks_type',
					'value' => '',
				),
				array(
					'key'     => 'semantic_linkbacks_type',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => 'semantic_linkbacks_type',
					'value' => 'mention',
				),
			);
		} elseif ( 'rsvp' === $type ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'semantic_linkbacks_type',
					'value'   => 'rsvp',
					'compare' => 'LIKE',
				),
			);
		} else {
			$args['meta_query'] = array(
				array(
					'key'   => 'semantic_linkbacks_type',
					'value' => $type,
				),
			);
		}
	} else { // check only if type exists
		$args['meta_query'] = array(
			array(
				'key'     => 'semantic_linkbacks_type',
				'compare' => 'EXISTS',
			),
		);
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
 * @param string $order the order of the retrieved comments, ASC or DESC (default)
 *
 * @return the matching linkback "comments"
 */
function get_linkbacks( $type = null, $post_id = null, $order = 'DESC' ) {
	if ( null === $post_id ) {
		$post_id = get_the_ID();
	}
	$args = array(
		'post_id' => $post_id,
		'status'  => 'approve',
		'order'   => $order,
	);

	if ( $type ) { // use type if set
		if ( 'mention' === $type ) {
			$args['type__not_in'] = 'comment';
			$args['meta_query']   = array(
				'relation' => 'OR',
				array(
					'key'   => 'semantic_linkbacks_type',
					'value' => '',
				),
				array(
					'key'     => 'semantic_linkbacks_type',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => 'semantic_linkbacks_type',
					'value' => 'mention',
				),
			);
		} elseif ( 'rsvp' === $type ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'semantic_linkbacks_type',
					'value'   => 'rsvp',
					'compare' => 'LIKE',
				),
			);
		} else {
			$args['meta_query'] = array(
				array(
					'key'   => 'semantic_linkbacks_type',
					'value' => $type,
				),
			);
		}
	} else { // check only if type exists
		$args['meta_query'] = array(
			array(
				'key'     => 'semantic_linkbacks_type',
				'compare' => 'EXISTS',
			),
		);
	}

	return get_comments( $args );
}


function has_linkbacks( $type = null, $post_ID = null ) {
	if ( get_linkbacks( $type, $post_ID ) ) {
		return true;
	}
	return false;
}

/**
 * Return marked up linkbacks
 * Based on wp_list_comments()
 */
function list_linkbacks( $args, $comments ) {
	$defaults = array(
		'avatar_size' => 64,
		'style'       => 'ul', // What HTML type to wrap it in. Accepts 'ul', 'ol'.
		'style-class' => 'mention-list', // What class to assign to the wrapper
		'li-class'    => null, // What class to assign to the list elements
		'echo'        => true, // Whether to echo the output or return
		'type'        => 'mention', // Type is the semantic linkbacks type and is here only to automatically add to the classes if present
	);

	$r = wp_parse_args( $args, $defaults );
	/**
	 * Filters the arguments used in retrieving the linkbacks list
	 *
	 *
	 * @param array $r An array of arguments for displaying linkbacks
	 */
	$r = apply_filters( 'list_linkbacks_args', $r );
	if ( ! is_array( $comments ) ) {
		return;
	}
	if ( is_string( $r['li-class'] ) ) {
		$classes = explode( ' ', $r['li-class'] );
	} else {
		$classes = $r['li-class'];
	}
	if ( is_string( $r['style-class'] ) ) {
		$r['style-class'] = explode( ' ', $r['style-class'] );
	}

	$classes[]          = 'linkback-' . $r['type'] . '-single';
	$r['style-class'][] = 'linkback-' . $r['type'];

	$return  = sprintf( '<%1$s class="%2$s">', $r['style'], join( ' ', $r['style-class'] ) );
	$fold_at = (int) get_option( 'semantic_linkbacks_facepiles_fold_limit', 8 );

	$type_labels = [
		'reacji' => __( 'Reacjis', 'semantic-linkbacks' ),
		'like' => __( 'Likes', 'semantic-linkbacks' ),
		'favorite' => __( 'Favourites', 'semantic-linkbacks' ),
		'bookmark' => __( 'Bookmarks', 'semantic-linkbacks' ),
		'repost' => __( 'Reposts', 'semantic-linkbacks' ),
		'tag' => __( 'Tags', 'semantic-linkbacks' ),
		'listen' => __( 'Listening', 'semantic-linkbacks' ),
		'read' => __( 'Reading', 'semantic-linkbacks' ),
		'follow' => __( 'Following', 'semantic-linkbacks' ),
		'watch' => __( 'Watching', 'semantic-linkbacks' ),
		'rsvp-yes' => __( 'RSVPs', 'semantic-linkbacks' ),
		'invited' => __( 'Invited', 'semantic-linkbacks' ),
		'rsvp-maybe' => __( 'Maybe', 'semantic-linkbacks' ),
		'rsvp-no' => __( 'No', 'semantic-linkbacks' ),
		'rsvp-interested' => __( 'Interested', 'semantic-linkbacks' ),
		'mention' => __( 'Mentions', 'semantic-linkbacks' ),
	];

	foreach ( $comments as $i => $comment ) {
		if ( $fold_at && $i === $fold_at ) {
			$classes[] = 'additional-facepile';

			// Add show button.
			$return .= sprintf(
				'<li class="additional-facepile-button-list-item"><button class="show-additional-facepiles"><span aria-hidden="true">&hellip;</span><span class="screen-reader-text">%s</span></button></li>',
				sprintf( /* translators: s=Linback type */
					__( 'Show more %s', 'semantic-linkbacks' ),
					$type_labels[$r['type']]
				)
			);
		}

		// If it's an emoji reaction, overlay the emoji.
		$overlay = '';
		$content = trim( wp_strip_all_tags( $comment->comment_content ) );
		$title   = Linkbacks_Handler::comment_text_excerpt( '', $comment );
		if ( Emoji\is_single_emoji( $content ) ) {
			$overlay = ' <span class="emoji-overlay">' . $content . '</span>';
			$url     = wp_parse_url( Linkbacks_Handler::get_url( $comment ), PHP_URL_HOST );
			$title   = sprintf(
				'%1$s %2$s on %3$s.',
				$comment->comment_author,
				$content,
				preg_replace( '/^www\./', '', $url )
			);
		}
		$class  = get_comment_class( $classes, $comment );
		$class  = join( ' ', $class );
		$avatar = get_avatar( $comment, $r['avatar_size'] );
		// If the avatar comes back empty show the name
		if ( ! $avatar ) {
			$avatar = get_comment_author( $comment );
		}
		$return .= sprintf(
			'<li class="%1$s" id="%5$s"><span class="p-author h-card"><a class="u-url" title="%6$s" href="%3$s">%2$s%8$s</a><span class="hide-name p-name">%4$s</span></span><a class="u-url" href="%7$s"></a></li>',
			$class,
			$avatar,
			get_comment_author_url( $comment ),
			get_comment_author( $comment ),
			esc_attr( 'comment-' . $comment->comment_ID ),
			esc_attr( wp_strip_all_tags( $title ) ),
			esc_url_raw( Linkbacks_Handler::get_canonical_url( $comment ) ),
			$overlay
		);
	}

	if ( $fold_at && count( $comments ) > $fold_at ) {
		// Add hide button at end.
		$return .= sprintf(
			'<li class="additional-facepile-button-list-item is-hidden"><button class="hide-additional-facepiles"><span aria-hidden="true">&hellip;</span><span class="screen-reader-text">%s</span></button></li>',
			sprintf( /* translators: s=Linback type */
				__( 'Show fewer %s', 'semantic-linkbacks' ),
				$type_labels[$r['type']]
			)
		);
	}

	$return .= sprintf( '</%1$s>', $r['style'] );
	if ( $r['echo'] ) {
		echo $return;
	}
	return $return;
}
