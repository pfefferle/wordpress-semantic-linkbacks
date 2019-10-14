<?php

/**
 * Semantic linkbacks Avatar Class
 *
 * @author Matthias Pfefferle
 */
class Linkbacks_Avatar_Handler {
	/**
	 * Initialize the plugin, registering WordPress hooks.
	 */
	public static function init() {
		add_filter( 'pre_get_avatar_data', array( 'Linkbacks_Avatar_Handler', 'pre_get_avatar_data' ), 11, 2 );
		add_filter( 'get_avatar_data', array( 'Linkbacks_Avatar_Handler', 'anonymous_avatar_data' ), 12, 2 );

		// All the default gravatars come from Gravatar instead of being generated locally so add a local default
		add_filter( 'avatar_defaults', array( 'Linkbacks_Avatar_Handler', 'anonymous_avatar' ) );

		add_filter( 'get_avatar_comment_types', array( 'Linkbacks_Avatar_Handler', 'get_avatar_comment_types' ) );
	}

	public static function anonymous_avatar( $avatar_defaults ) {
		$avatar_defaults['mystery'] = __( 'Mystery Person (hosted locally)', 'semantic-linkbacks' );
		return $avatar_defaults;
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

		$avatar = get_comment_meta( $comment->comment_ID, 'avatar', true );
		// Backward Compatibility for Semantic Linkbacks
		if ( ! $avatar ) {
			$avatar = get_comment_meta( $comment->comment_ID, 'semantic_linkbacks_avatar', true );
		}

		return $avatar;
	}


	/**
	 * Function to retrieve default avatar URL
	 *
	 *
	 * @param string $type Default Avatar URL
	 *
	 * @return string|boolean $url
	 */
	public static function get_default_avatar( $type = null ) {
		if ( ! $type ) {
			$type = get_option( 'avatar_default', 'mystery' );
		}
		switch ( $type ) {
			case 'mm':
			case 'mystery':
			case 'mysteryman':
				return plugin_dir_url( dirname( __FILE__ ) ) . 'img/mm.jpg';
		}
		return apply_filters( 'semantic_linkbacks_default_avatar', $type );
	}

	/**
	 * Function to check if there is a gravatar
	 *
	 *
	 * @param WP_Comment $comment
	 *
	 * @return boolean
	 */
	public static function check_gravatar( $comment ) {
		$hash     = md5( strtolower( trim( $comment->comment_author_email ) ) );
		$url      = 'https://www.gravatar.com/avatar/' . $hash . '?d=404';
		$response = wp_remote_head( $url );
		if ( is_wp_error( $response ) || 404 === wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		return true;
	}


	/**
	 * Replaces the default avatar with a locally stored default
	 *
	 * @param array             $args Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object
	 *
	 * @return array $args
	 */
	public static function anonymous_avatar_data( $args, $id_or_email ) {
		$local = apply_filters( 'semantic_linkbacks_local_avatars', array( 'mm', 'mystery', 'mysteryman' ) );
		if ( ! in_array( $args['default'], $local, true ) ) {
			return $args;
		}
		// Always override if default forced
		if ( $args['force_default'] ) {
			$args['url'] = self::get_default_avatar( $args['default'] );
			return $args;
		}
		if ( ! strpos( $args['url'], 'gravatar.com' ) ) {
			return $args;
		}
		$args['url'] = str_replace( 'd=' . $args['default'], 'd=' . self::get_default_avatar( $args['default'] ), $args['url'] );
		if ( $id_or_email instanceof WP_Comment ) {
			if ( ! empty( $id_or_email->comment_author_email ) ) {
				if ( self::check_gravatar( $id_or_email ) ) {
					update_comment_meta( $id_or_email->comment_ID, 'avatar', $args['url'] );
				} else {
					update_comment_meta( $id_or_email->comment_ID, 'avatar', self::get_default_avatar() );
					$args['url'] = self::get_default_avatar();
				}
				return $args;
			} else {
				$args['url'] = self::get_default_avatar();
			}
		}
		return $args;
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

		$allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
		if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types, true ) ) {
			$args['url'] = false;
			/** This filter is documented in wp-includes/link-template.php */
			return apply_filters( 'get_avatar_data', $args, $id_or_email );
		}

		$type = Linkbacks_Handler::get_type( $id_or_email );
		$type = explode( ':', $type );

		if ( is_array( $type ) ) {
			$type = $type[0];
		}

		$option = 'semantic_linkbacks_facepile_' . $type;

		if ( get_option( $option, false ) && 'blank' === $args['default'] ) {
			$args['default'] = 'anonymous';
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
	 * Show avatars also on trackbacks and pingbacks
	 *
	 * @param array $types list of avatar enabled comment types
	 *
	 * @return array show avatars also on trackbacks and pingbacks
	 */
	public static function get_avatar_comment_types( $types ) {
		$types[] = 'pingback';
		$types[] = 'trackback';

		return array_unique( $types );
	}
}
