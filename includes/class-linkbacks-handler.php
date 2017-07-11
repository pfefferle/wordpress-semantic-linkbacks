<?php

// Mentions with content less than this length will be rendered inline.
define( 'MAX_INLINE_MENTION_LENGTH', 300 );

/**
 * Semantic linkbacks class
 *
 * @author Matthias Pfefferle
 */
class Linkbacks_Handler {
	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		// enhance linkbacks
		add_filter( 'preprocess_comment', array( 'Linkbacks_Handler', 'enhance' ), 0, 1 );
		add_filter( 'wp_update_comment_data', array( 'Linkbacks_Handler', 'enhance' ), 11, 3 );

		// Updates Comment Meta if set in commentdata
		add_action( 'edit_comment', array( 'Linkbacks_Handler', 'update_meta' ), 10, 2 );

		add_filter( 'pre_get_avatar_data', array( 'Linkbacks_Handler', 'pre_get_avatar_data' ), 11, 5 );
		// To extend or to override the default behavior, just use the `comment_text` filter with a lower
		// priority (so that it's called after this one) or remove the filters completely in
		// your code: `remove_filter('comment_text', array('Linkbacks_Handler', 'comment_text_add_cite'), 11);`
		add_filter( 'comment_text', array( 'Linkbacks_Handler', 'comment_text_add_cite' ), 11, 3 );
		add_filter( 'comment_text', array( 'Linkbacks_Handler', 'comment_text_excerpt' ), 12, 3 );
		add_filter( 'comment_excerpt', array( 'Linkbacks_Handler', 'comment_text_excerpt' ), 5, 2 );

		add_filter( 'get_comment_link', array( 'Linkbacks_Handler', 'get_comment_link' ), 99, 3 );
		add_filter( 'get_comment_author_url', array( 'Linkbacks_Handler', 'get_comment_author_url' ), 99, 3 );
		add_filter( 'get_avatar_comment_types', array( 'Linkbacks_Handler', 'get_avatar_comment_types' ) );
		add_filter( 'comment_class', array( 'Linkbacks_Handler', 'comment_class' ), 10, 4 );

		// Register Meta Keys
		self::register_meta();
	}

	/**
	 * This is more to lay out the data structure than anything else.
	 */
	public static function register_meta() {
		$args = array(
			'sanitize_callback' => 'esc_url_raw',
			'type' => 'string',
			'description' => 'Author URL',
			'single' => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_author_url', $args );

		$args = array(
			'sanitize_callback' => 'esc_url_raw',
			'type' => 'string',
			'description' => 'Avatar URL',
			'single' => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_avatar', $args );

		$args = array(
			'sanitize_callback' => 'esc_url_raw',
			'type' => 'string',
			'description' => 'Canonical URL',
			'single' => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_canonical', $args );

		$args = array(
			'type' => 'string',
			'description' => 'Linkbacks Type',
			'single' => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_type', $args );
	}

	/**
	 * Update an Enhanced Comment
	 */
	public static function enhance( $commentdata, $comment = array(), $commentarr = array() ) {
		// If $comment is empty this is a new comment
		if ( empty( $comment ) ) {
			if ( ! in_array( $commentdata['comment_type'], array( 'webmention', 'pingback', 'trackback' ) ) ) {
			       return $commentdata;
			}
		}
		else {
			// For an update the only acceptable types are webmention or comment
			if ( ! in_array( $comment['comment_type'], array( 'webmention', '' ) ) ) {
				return $commentdata;
			}
		}

		// only run the enhancer if `remote_source_original` is set
		if ( empty( $commentdata['remote_source_original'] ) ) {
			return $commentdata;
		}

		// initialize comment_meta array
		if ( ! array_key_exists( 'comment_meta', $commentdata ) ) {
			$commentdata['comment_meta'] = array();
		}

		//  If target is not set then set based on permalink
		if ( ! isset( $commentdata['target'] ) ) {
			$commentdata['target'] = get_permalink( $commentdata['comment_post_ID'] );
		}
		// add replytocom if present
		if ( isset( $commentdata['comment_parent'] ) && ! empty( $commentdata['comment_parent'] ) ) {
			$commentdata['target'] = add_query_arg( array( 'replytocom' => $commentdata['comment_parent'] ), $commentdata['target'] );
		}

		// add source url as comment-meta for pingbacks and trackbacks
		if ( ! array_key_exists( 'webmention_source_url', $commentdata['comment_meta'] ) ) {
			$commentdata['comment_meta']['semantic_linkbacks_source'] = esc_url_raw( $commentdata['comment_author_url'] );
		}
		// adds a hook to enable some other semantic handlers for example schema.org
		$commentdata = apply_filters( 'semantic_linkbacks_commentdata', $commentdata );

		// remove "webmention" comment-type if $type is "reply"
		if ( isset( $commentdata['comment_meta']['semantic_linkbacks_type'] ) ) {
			if ( in_array( $commentdata['comment_meta']['semantic_linkbacks_type'], apply_filters( 'semantic_linkbacks_comment_types', array( 'reply' ) ) ) ) {
				$commentdata['comment_type'] = '';
			}
		}

		return wp_unslash( $commentdata );
	}

	/**
	 * Retrieve Remote Source
	 *
	 * @param string $url URL to Retrieve
	 * @return remote source
	 */
	public static function retrieve( $url ) {
		global $wp_version;
		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args = array(
			'timeout' => 100,
			'limit_response_size' => 1048576,
			'redirection' => 20,
			'user-agent' => "$user_agent; verifying linkback",
		);
		return wp_safe_remote_get( $url, $args );
	}

	/**
	 * Save Meta - to Match the core functionality in wp_insert_comment.
	 * To be Removed in WordPress 4.9.
	 *
	 * @param array $commentdata The new comment data
	 * @param array $comment The old comment data
	 */
	public static function update_meta( $comment_id, $commentdata ) {
		// If metadata is provided, store it.
		if ( isset( $commentdata['comment_meta'] ) && is_array( $commentdata['comment_meta'] ) ) {
			foreach ( $commentdata['comment_meta'] as $meta_key => $meta_value ) {
				update_comment_meta( $comment_id, $meta_key, $meta_value, true );
			}
		}
	}

	/**
	 * Returns an array of comment type excerpts to their translated and pretty display versions
	 *
	 * @return array The array of translated post format excerpts.
	 */
	public static function get_comment_type_excerpts() {
		$strings = array(
			// special case. any value that evals to false will be considered standard
			'mention'		=> __( '%1$s mentioned %2$s on <a href="%3$s">%4$s</a>.',	'semantic-linkbacks' ),

			'reply'			=> __( '%1$s replied to %2$s on <a href="%3$s">%4$s</a>.',	'semantic-linkbacks' ),
			'repost'		=> __( '%1$s reposted %2$s on <a href="%3$s">%4$s</a>.',	'semantic-linkbacks' ),
			'like'			=> __( '%1$s liked %2$s on <a href="%3$s">%4$s</a>.',		'semantic-linkbacks' ),
			'favorite'		=> __( '%1$s favorited %2$s on <a href="%3$s">%4$s</a>.',	'semantic-linkbacks' ),
			'tag'			=> __( '%1$s tagged %2$s on <a href="%3$s">%4$s</a>.',		'semantic-linkbacks' ),
			'bookmark'		=> __( '%1$s bookmarked %2$s on <a href="%3$s">%4$s</a>.',	'semantic-linkbacks' ),
			'rsvp:yes'		=> __( '%1$s is <strong>attending</strong>.',				'semantic-linkbacks' ),
			'rsvp:no'		=> __( '%1$s is <strong>not attending</strong>.',			'semantic-linkbacks' ),
			'rsvp:maybe'	=> __( 'Maybe %1$s will be <strong>attending</strong>.',	'semantic-linkbacks' ),
			'rsvp:invited'	=> __( '%1$s is <strong>invited</strong>.',					'semantic-linkbacks' ),
			'rsvp:tracking'	=> __( '%1$s <strong>tracks</strong> this event.',			'semantic-linkbacks' ),
		);

		return $strings;
	}

	/**
	* Returns an array of comment type slugs to their translated and pretty display versions
	*
	* @return array The array of translated comment type names.
	*/
	public static function get_comment_type_strings() {
		$strings = array(
			// Special case. any value that evals to false will be considered standard
			'mention'		=> __( 'Mention',	'semantic-linkbacks' ),

			'reply'			=> __( 'Reply',		'semantic-linkbacks' ),
			'repost'		=> __( 'Repost',	'semantic-linkbacks' ),
			'like'			=> __( 'Like',		'semantic-linkbacks' ),
			'favorite'		=> __( 'Favorite',	'semantic-linkbacks' ),
			'tag'			=> __( 'Tag',		'semantic-linkbacks' ),
			'bookmark'		=> __( 'Bookmark',	'semantic-linkbacks' ),
			'rsvp:yes'		=> __( 'RSVP',		'semantic-linkbacks' ),
			'rsvp:no'		=> __( 'RSVP',		'semantic-linkbacks' ),
			'rsvp:invited'	=> __( 'RSVP',		'semantic-linkbacks' ),
			'rsvp:maybe'	=> __( 'RSVP',		'semantic-linkbacks' ),
			'rsvp:tracking'	=> __( 'RSVP',		'semantic-linkbacks' ),
		);

		return $strings;
	}

	/**
	* Returns an array of post formats (with articlex)
	*
	* @return array The array of translated comment type names.
	*/
	public static function get_post_format_strings() {
		$strings = array(
			// Special case. any value that evals to false will be considered standard
			'standard'	=> __( 'this Article',	'semantic-linkbacks' ),

			'aside'		=> __( 'this Aside',	'semantic-linkbacks' ),
			'chat'		=> __( 'this Chat',		'semantic-linkbacks' ),
			'gallery'	=> __( 'this Gallery',	'semantic-linkbacks' ),
			'link'		=> __( 'this Link',		'semantic-linkbacks' ),
			'image'		=> __( 'this Image',	'semantic-linkbacks' ),
			'quote'		=> __( 'this Quote',	'semantic-linkbacks' ),
			'status'	=> __( 'this Status',	'semantic-linkbacks' ),
			'video'		=> __( 'this Video',	'semantic-linkbacks' ),
			'audio'		=> __( 'this Audio',	'semantic-linkbacks' ),
		);

		return $strings;
	}

	/**
	 * Return correct URL
	 *
	 * @param WP_Comment $comment the comment object
	 *
	 * @return string the URL
	 */
	public static function get_url( $comment = null ) {
		// get canonical url...
		$url = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_canonical', true );
		// ...or fall back to source
		if ( ! $url && in_array( $comment->comment_type, array( 'webmention', '' ) ) ) {
			$url = get_comment_meta( $comment->comment_ID, 'webmention_source_url' );
		}
		if ( ! $url ) {
			$url = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_source', true );
		}
		// ...or author url
		if ( ! $url ) {
			$url = $comment->comment_author_url;
		}

		return $url;
	}

	/**
	 * Add cite to "reply"s
	 *
	 * thanks to @snarfed for the idea
	 *
	 * @param string $text the comment text
	 * @param WP_Comment $comment the comment object
	 * @param array $args a list of arguments
	 *
	 * @return string the filtered comment text
	 */
	public static function comment_text_add_cite( $text, $comment = null, $args = array() ) {
		if ( ! $comment ) {
			return $text;
		}

		$semantic_linkbacks_type = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_type', true );

		// only change text for "real" comments (replys)
		if ( ! $semantic_linkbacks_type ||
			'' != $comment->comment_type ||
			'reply' != $semantic_linkbacks_type ) {
			return $text;
		}

		$url = self::get_url( $comment );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );
		// note that WordPress's sanitization strips the class="u-url". sigh. :/ also,
		// <cite> is one of the few elements that make it through the sanitization and
		// is still uncommon enough that we can use it for styling.
		$text .= '<p><cite><a class="u-url" href="' . $url . '">via ' . $host . '</a></cite></p>';

		return apply_filters( 'semantic_linkbacks_cite', $text );
	}

	/**
	 * Generate excerpt for all types except "reply"
	 *
	 * @param string $text the comment text
	 * @param WP_Comment $comment the comment object
	 * @param array $args a list of arguments
	 *
	 * @return string the filtered comment text
	 */
	public static function comment_text_excerpt( $text, $comment = null, $args = array() ) {
		if ( ! is_object( $comment ) ) {
			$comment = get_comment( $comment );
		}

		if ( ! $comment ) {
			return $text;
		}

		$semantic_linkbacks_type = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_type', true );

		// only change text for pingbacks/trackbacks/webmentions
		if ( '' == $comment->comment_type ||
			! $semantic_linkbacks_type ||
			'reply' == $semantic_linkbacks_type ) {
			return $text;
		}

		// check semantic linkback type
		if ( ! in_array( $semantic_linkbacks_type, array_keys( self::get_comment_type_strings() ) ) ) {
			$semantic_linkbacks_type = 'mention';
		}

		if ( ! current_theme_supports( 'post-formats' ) ) {
			$post_format = 'standard';
		} else {
			$post_format = get_post_format( $comment->comment_post_ID );
			// add "standard" as default
			if ( ! $post_format || ! in_array( $post_format, array_keys( self::get_post_format_strings() ) ) ) {
				$post_format = 'standard';
			}
		}

		// get post type
		$post_formatstrings = self::get_post_format_strings();
		$post_type = $post_formatstrings[ $post_format ];

		$post_type = apply_filters( 'semantic_linkbacks_post_type', $post_type, $comment->comment_post_ID );

		// get all the excerpts
		$comment_type_excerpts = self::get_comment_type_excerpts();

		$url = self::get_url( $comment );

		// parse host
		$host = parse_url( $url, PHP_URL_HOST );
		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );

		// generate output. use full content if it's small enough, otherwise use excerpt.
		if ( ! ( 'mention' == $semantic_linkbacks_type && strlen( wp_strip_all_tags( $text ) ) <= MAX_INLINE_MENTION_LENGTH ) ) {
			$text = sprintf( $comment_type_excerpts[ $semantic_linkbacks_type ], get_comment_author_link( $comment->comment_ID ), $post_type, $url, $host );
		}

		return apply_filters( 'semantic_linkbacks_excerpt', $text );
	}

	/**
	 * Replaces the default avatar with the WebMention uf2 photo
	 *
	 * @param array             $args Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object
	 *
	 * @return array $args
	 */
	public static function pre_get_avatar_data( $args, $id_or_email ) {
		if ( ! $id_or_email instanceof WP_Comment ||
			! isset( $id_or_email->comment_type ) ||
				   $id_or_email->user_id ) {
			return $args;
		}
		// check if comment has an avatar
		$avatar = get_comment_meta( $id_or_email->comment_ID, 'semantic_linkbacks_avatar', true );
		if ( $avatar ) {
			if ( ! isset( $args['class'] ) ) {
				$args['class'] = array( 'u-photo' );
			} else {
				$args['class'][] = 'u-photo';
				$args['class'] = array_unique( $args['class'] );
			}
			$args['url'] = $avatar;
			$args['class'][] = 'avatar-semantic-linkbacks';
		}
		return $args;
	}

	/**
	 * Replace comment url with canonical url
	 *
	 * @param string     $link the link url
	 * @param WP_Comment $comment the comment object
	 * @param array      $args a list of arguments to generate the final link tag
	 *
	 * @return string the linkback source or the original comment link
	 */
	public static function get_comment_link( $link, $comment, $args ) {
		$semantic_linkbacks_canonical = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_canonical', true );

		if ( is_singular() && $semantic_linkbacks_canonical ) {
			return $semantic_linkbacks_canonical;
		}

		return $link;
	}

	/**
	 * Replace comment url with author url
	 *
	 * @param string     $url        The comment author's URL.
	 * @param int        $comment_ID The comment ID.
	 * @param WP_Comment $comment    The comment object.
	 *
	 * @return string the replaced/parsed author url or the original comment link
	 */
	public static function get_comment_author_url( $url, $id, $comment ) {
		$author_url = get_comment_meta( $id, 'semantic_linkbacks_author_url', true );
		if ( ! $author_url ) {
			return $url;
		}
		if ( get_comment_meta( $id, 'webmention_source_url' ) ) {
			wp_update_comment( 
				array(
					'comment_ID' => $id,
					'comment_author_url' => $author_url
				)
			);
			delete_comment_meta( $id, 'semantic_linkbacks_author_url' );
			delete_comment_meta( $id, 'semantic_linkbacks_source' );
		}
		return $author_url;
	}

	/**
	 * Add comment classes from `semantic_linkbacks_type`s
	 *
	 * @return array the extended comment classes as array
	 */
	public static function comment_class( $classes, $class, $comment_id, $post_id ) {
		// get comment
		$comment = get_comment( $comment_id );

		// "comment type to class" mapper
		$class_mapping = array(
			'mention'		=> array( 'h-as-mention' ),

			'reply'			=> array( 'h-as-reply' ),
			'repost'		=> array( 'h-as-repost', 'p-repost' ),
			'like'			=> array( 'h-as-like', 'p-like' ),
			'favorite'		=> array( 'h-as-favorite', 'p-favorite' ),
			'tag'			=> array( 'h-as-tag', 'p-tag' ),
			'bookmark'		=> array( 'h-as-bookmark', 'p-bookmark' ),
			'rsvp:yes'		=> array( 'h-as-rsvp' ),
			'rsvp:no'		=> array( 'h-as-rsvp' ),
			'rsvp:maybe'	=> array( 'h-as-rsvp' ),
			'rsvp:invited'	=> array( 'h-as-rsvp' ),
			'rsvp:tracking'	=> array( 'h-as-rsvp' ),
		);

		$semantic_linkbacks_type = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_type', true );

		// check the comment type
		if ( $semantic_linkbacks_type && isset( $class_mapping[ $semantic_linkbacks_type ] ) ) {
			$classes = array_merge( $classes, $class_mapping[ $semantic_linkbacks_type ] );

			$classes = array_unique( $classes );
		}

		return $classes;
	}

	/**
	 * Show avatars also on trackbacks and pingbacks
	 *
	 * @param array $types list of avatar enabled comment types
	 *
	 * @return array show avatars also on trackbacks and pingbacks
	 */
	public static function get_avatar_comment_types( $types ) {
		$types[] = 'pingback';
		$types[] = 'trackback';
		$types[] = 'webmention';

		return array_unique( $types );
	}

}
