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
		if ( ! self::render_comments() ) {
			add_filter( 'comment_text', array( 'Linkbacks_Handler', 'comment_text_add_cite' ), 11, 3 );
		}
		add_filter( 'comment_text', array( 'Linkbacks_Handler', 'comment_text_excerpt' ), 12, 3 );
		add_filter( 'comment_excerpt', array( 'Linkbacks_Handler', 'comment_text_excerpt' ), 5, 2 );

		add_filter( 'get_comment_link', array( 'Linkbacks_Handler', 'get_comment_link' ), 99, 3 );
		add_filter( 'get_comment_author_url', array( 'Linkbacks_Handler', 'get_comment_author_url' ), 99, 3 );
		add_filter( 'get_avatar_comment_types', array( 'Linkbacks_Handler', 'get_avatar_comment_types' ) );
		add_filter( 'comment_class', array( 'Linkbacks_Handler', 'comment_class' ), 10, 4 );
		add_filter( 'wp_list_comments_args', array( 'Linkbacks_Handler', 'filter_comment_args' ) );
		add_action( 'comment_form_before', array( 'Linkbacks_Handler', 'show_mentions' ) );

		// Register Meta Keys
		self::register_meta();
	}

	/**
	 * Filter the comments and ignore every comment other than 'comment' and 'mention'
	 *
	 * @param array $args an array of arguments for displaying comments
	 *
	 * @return array the filtered array
	 */
	public static function filter_comment_args( $args ) {
		$args['walker'] = new Semantic_Linkbacks_Walker_Comment();
		return $args;
	}

	/**
	 * Filter whether to override comment presentation.
	 * To use the default html5_comment set this filter to false
	 *
	 * @return boolean
	 */
	public static function render_comments() {
		return apply_filters( 'semantic_linkbacks_render_comments', ! current_theme_supports( 'microformats2' ) );
	}

	/**
	 *
	 *
	 */
	public static function show_mentions() {
		// If this filter is set to false then hide the template and hide the option. This should be used by themes
		if ( apply_filters( 'semantic_linkbacks_facepile', true ) ) {
			load_template( plugin_dir_path( dirname( __FILE__ ) ) . 'templates/linkbacks.php' );
		}
	}

	/**
	 * This is more to lay out the data structure than anything else.
	 */
	public static function register_meta() {
		$args = array(
			'sanitize_callback' => 'esc_url_raw',
			'type'              => 'string',
			'description'       => 'Author URL',
			'single'            => true,
			'show_in_rest'      => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_author_url', $args );

		$args = array(
			'sanitize_callback' => 'esc_url_raw',
			'type'              => 'string',
			'description'       => 'Avatar URL',
			'single'            => true,
			'show_in_rest'      => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_avatar', $args );

		$args = array(
			'sanitize_callback' => 'esc_url_raw',
			'type'              => 'string',
			'description'       => 'Canonical URL',
			'single'            => true,
			'show_in_rest'      => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_canonical', $args );

		$args = array(
			'type'         => 'string',
			'description'  => 'Linkbacks Type',
			'single'       => true,
			'show_in_rest' => true,
		);
		register_meta( 'comment', 'semantic_linkbacks_type', $args );
	}

	/**
	 * Update an Enhanced Comment
	 */
	public static function enhance( $commentdata, $comment = array(), $commentarr = array() ) {
		// check if comment is a linkback
		if ( ! in_array( $commentdata['comment_type'], array( 'webmention', 'pingback', 'trackback' ), true ) ) {
			return $commentdata;
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

		// add source url as comment-meta
		$commentdata['comment_meta']['semantic_linkbacks_source'] = esc_url_raw( $commentdata['comment_author_url'] );

		// adds a hook to enable some other semantic handlers for example schema.org
		$commentdata = apply_filters( 'semantic_linkbacks_commentdata', $commentdata );

		// remove "webmention" comment-type if $type is "reply"
		if ( isset( $commentdata['comment_meta']['semantic_linkbacks_type'] ) ) {
			if ( in_array( $commentdata['comment_meta']['semantic_linkbacks_type'], apply_filters( 'semantic_linkbacks_comment_types', array( 'reply' ) ), true ) ) {
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
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 1048576,
			'redirection'         => 20,
			'user-agent'          => "$user_agent; verifying linkback",
		);
		return wp_safe_remote_get( $url, $args );
	}

	/**
	 * Save Meta - to Match the core functionality in wp_insert_comment.
	 * To be Removed if This Functionality Hits Core.
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
			// translators: Name verb on domain
			'mention'         => __( '%1$s mentioned %2$s on <a href="%3$s">%4$s</a>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'reply'           => __( '%1$s replied to %2$s on <a href="%3$s">%4$s</a>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'repost'          => __( '%1$s reposted %2$s on <a href="%3$s">%4$s</a>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'like'            => __( '%1$s liked %2$s on <a href="%3$s">%4$s</a>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'favorite'        => __( '%1$s favorited %2$s on <a href="%3$s">%4$s</a>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'tag'             => __( '%1$s tagged %2$s on <a href="%3$s">%4$s</a>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'bookmark'        => __( '%1$s bookmarked %2$s on <a href="%3$s">%4$s</a>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'rsvp:yes'        => __( '%1$s is <strong>attending</strong>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'rsvp:no'         => __( '%1$s is <strong>not attending</strong>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'rsvp:maybe'      => __( 'Maybe %1$s will be <strong>attending</strong>.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'rsvp:interested' => __( '%1$s is <strong>interested</strong> in this event.', 'semantic-linkbacks' ),
			// translators: Name verb on domain
			'invited'         => __( '%1$s is <strong>invited</strong>.', 'semantic-linkbacks' ),
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
			'mention'         => __( 'Mention', 'semantic-linkbacks' ),

			'reply'           => __( 'Reply', 'semantic-linkbacks' ),
			'repost'          => __( 'Repost', 'semantic-linkbacks' ),
			'like'            => __( 'Like', 'semantic-linkbacks' ),
			'favorite'        => __( 'Favorite', 'semantic-linkbacks' ),
			'tag'             => __( 'Tag', 'semantic-linkbacks' ),
			'bookmark'        => __( 'Bookmark', 'semantic-linkbacks' ),
			'rsvp:yes'        => __( 'RSVP', 'semantic-linkbacks' ),
			'rsvp:no'         => __( 'RSVP', 'semantic-linkbacks' ),
			'rsvp:maybe'      => __( 'RSVP', 'semantic-linkbacks' ),
			'rsvp:interested' => __( 'RSVP', 'semantic-linkbacks' ),
			'invited'         => __( 'Invited', 'semantic-linkbacks' ),
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
			'standard' => __( 'this Article', 'semantic-linkbacks' ),

			'aside'    => __( 'this Aside', 'semantic-linkbacks' ),
			'chat'     => __( 'this Chat', 'semantic-linkbacks' ),
			'gallery'  => __( 'this Gallery', 'semantic-linkbacks' ),
			'link'     => __( 'this Link', 'semantic-linkbacks' ),
			'image'    => __( 'this Image', 'semantic-linkbacks' ),
			'quote'    => __( 'this Quote', 'semantic-linkbacks' ),
			'status'   => __( 'this Status', 'semantic-linkbacks' ),
			'video'    => __( 'this Video', 'semantic-linkbacks' ),
			'audio'    => __( 'this Audio', 'semantic-linkbacks' ),
		);

		return $strings;
	}

	/**
	 * Return correct URL
	 *
	 * @param WP_Comment|int $comment the comment object or comment ID
	 *
	 * @return string the URL
	 */
	public static function get_url( $comment ) {
		if ( is_numeric( $comment ) ) {
			$comment = get_comment( $comment );
		}
		// get canonical url...
		$semantic_linkbacks_canonical = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_canonical', true );
		// ...or fall back to source
		if ( ! $semantic_linkbacks_canonical ) {
			$semantic_linkbacks_canonical = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_source', true );
		}
		// ...or author url
		if ( ! $semantic_linkbacks_canonical ) {
			$semantic_linkbacks_canonical = $comment->comment_author_url;
		}

		return $semantic_linkbacks_canonical;
	}

	/**
	 * Return canonical URL
	 *
	 * @param int|WP_Comment $comment Comment
	 *
	 * @return string the URL
	 */
	public static function get_canonical_url( $comment ) {
		if ( is_numeric( $comment ) ) {
			$comment = get_comment( $comment );
		}
		// get canonical url...
		return get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_canonical', true );
	}

	/**
	 * Return type
	 *
	 * @param int|WP_Comment $comment Comment
	 *
	 * @return string the type
	 */
	public static function get_type( $comment ) {
		if ( $comment instanceof WP_Comment ) {
			$comment = $comment->comment_ID;
		}
		// get type...
		return get_comment_meta( $comment, 'semantic_linkbacks_type', true );
	}

	/**
	 * Return property
	 *
	 * @param int|WP_Comment $comment Comment
	 * @param string $key Key
	 *
	 * @return boolean|string|array Property or False if Property Does Not Exist
	 */
	public static function get_prop( $comment, $key ) {
		if ( $comment instanceof WP_Comment ) {
			$comment = $comment->comment_ID;
		}
		$prop = get_comment_meta( $comment, $key );
		if ( ! $prop || is_string( $prop ) ) {
			return $prop;
		}
		if ( 1 === count( $prop ) ) {
			return array_shift( $prop );
		}
		return $prop;
	}


	/**
	 * Return author URL
	 *
	 * @param int|WP_Comment $comment Comment
	 *
	 * @return string the URL
	 */
	public static function get_author_url( $comment ) {
		if ( $comment instanceof WP_Comment ) {
			$comment = $comment->comment_ID;
		}
		// get author URL...
		return get_comment_meta( $comment, 'semantic_linkbacks_author_url', true );
	}

	public static function get_post_type( $comment ) {
		if ( 'post' === get_post_type( $comment->comment_post_ID ) ) {	
			if ( ! current_theme_supports( 'post-formats' ) ) {
				$post_format = 'standard';
			} else {
				$post_format = get_post_format( $comment->comment_post_ID );
				// add "standard" as default
				if ( ! $post_format || ! in_array( $post_format, array_keys( self::get_post_format_strings() ), true ) ) {
					$post_format = 'standard';
				}
			}
			$post_formatstrings = self::get_post_format_strings();
			$post_type          = $post_formatstrings[ $post_format ];
		} else {
			// If this isn't a post then use the singular name of the post type converted to lowercase
			$post_type_object = get_post_type_object( get_post_type( $comment->comment_post_ID ) );
			$post_type = $post_type_object->singular_name;
			if ( $comment->comment_post_ID === get_option( 'webmention_home_mentions', 0 ) ) {
				// If this is the homepage use the site name
				$post_type = get_bloginfo( 'name' );
			}
		}
		return apply_filters( 'semantic_linkbacks_post_type', $post_type, $comment->comment_post_ID );
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

		$semantic_linkbacks_type = self::get_type( $comment );

		// only change text for "real" comments (replys)
		if ( ! $semantic_linkbacks_type ||
			'' !== $comment->comment_type ||
			'reply' !== $semantic_linkbacks_type ) {
			return $text;
		}

		$url  = self::get_url( $comment );
		$host = wp_parse_url( $url, PHP_URL_HOST );

		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );
		// note that WordPress's sanitization strips the class="u-url". sigh. :/ also,
		// <cite> is one of the few elements that make it through the sanitization and
		// is still uncommon enough that we can use it for styling.
		$cite = apply_filters( 'semantic_linkbacks_cite', '<p><cite><a class="u-url" href="%1s">via %2s</a></cite></p>' );
		return $text . sprintf( $cite, $url, $host );
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

		$semantic_linkbacks_type = self::get_type( $comment );

		// only change text for pingbacks/trackbacks/webmentions
		if ( '' === $comment->comment_type ||
			! $semantic_linkbacks_type ||
			'reply' === $semantic_linkbacks_type ) {
			return $text;
		}

		// check semantic linkback type
		if ( ! in_array( $semantic_linkbacks_type, array_keys( self::get_comment_type_strings() ), true ) ) {
			$semantic_linkbacks_type = 'mention';
		}

		$post_type = self::get_post_type( $comment );

		// get all the excerpts
		$comment_type_excerpts = self::get_comment_type_excerpts();

		$url = self::get_url( $comment );

		// parse host
		$host = wp_parse_url( $url, PHP_URL_HOST );
		// strip leading www, if any
		$host = preg_replace( '/^www\./', '', $host );

		// generate output. use full content if it's small enough, otherwise use excerpt.
		$text_len = mb_strlen( html_entity_decode( $text, ENT_QUOTES ) );
		if ( ! ( 'mention' === $semantic_linkbacks_type && $text_len <= MAX_INLINE_MENTION_LENGTH ) ) {
			$text = sprintf( $comment_type_excerpts[ $semantic_linkbacks_type ], get_comment_author_link( $comment->comment_ID ), $post_type, $url, $host );
		}
		return apply_filters( 'semantic_linkbacks_excerpt', $text );
	}

	/**
	 * Function to retrieve Avatar URL if stored in meta
	 *
	 *
	 * @param int|WP_Comment $comment
	 *
	 * @return string $url
	 */
	public static function get_avatar_url( $comment ) {
		if ( is_numeric( $comment ) ) {
			$comment = get_comment( $comment );
		}
		return get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_avatar', true );
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

		$type = self::get_type( $id_or_email );
		$type = explode( ':', $type );

		if ( is_array( $type ) ) {
			$type = $type[0];
		}

		$option = 'semantic_linkbacks_facepile_' . $type;

		if ( get_option( $option, false ) && 'blank' === $args['default'] ) {
			$args['default'] = 'mm';
		}

		// check if comment has an avatar
		$avatar = self::get_avatar_url( $id_or_email->comment_ID );

		if ( $avatar ) {
			if ( ! isset( $args['class'] ) || ! is_array( $args['class'] ) ) {
				$args['class'] = array( 'u-photo' );
			} else {
				$args['class'][] = 'u-photo';
				$args['class']   = array_unique( $args['class'] );
			}
			$args['url']     = $avatar;
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
		$semantic_linkbacks_canonical = self::get_canonical_url( $comment );

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
		$author_url = self::get_author_url( $comment );

		if ( $author_url ) {
			return $author_url;
		}

		return $url;
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
			'mention'         => array( 'u-mention' ),

			'reply'           => array( 'u-comment' ),
			'repost'          => array( 'u-repost' ),
			'like'            => array( 'u-like' ),
			'favorite'        => array( 'u-favorite' ),
			'tag'             => array( 'u-tag' ),
			'bookmark'        => array( 'u-bookmark' ),
			'rsvp:yes'        => array( 'u-rsvp' ),
			'rsvp:no'         => array( 'u-rsvp' ),
			'rsvp:maybe'      => array( 'u-rsvp' ),
			'rsvp:interested' => array( 'u-rsvp' ),
			'invited'         => array( 'u-invitee' ),
		);

		$semantic_linkbacks_type = self::get_type( $comment );

		// check the comment type
		if ( $semantic_linkbacks_type && isset( $class_mapping[ $semantic_linkbacks_type ] ) ) {
			$classes = array_merge( $classes, $class_mapping[ $semantic_linkbacks_type ] );
			$classes = array_unique( $classes );
		}
		$classes[] = 'h-cite';
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
