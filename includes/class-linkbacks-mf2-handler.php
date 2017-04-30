<?php
if ( ! class_exists( 'Mf2\Parser' ) ) {
	require_once 'Mf2/Parser.php';
}

use Mf2\Parser;

/**
 * provides a microformats handler for the semantic linkbacks
 * WordPress plugin
 *
 * @author Matthias Pfefferle
 */
class Linkbacks_MF2_Handler {
	/**
	 * initialize the plugin, registering WordPess hooks.
	 */
	public static function init() {
		add_filter( 'semantic_linkbacks_commentdata', array( 'Linkbacks_MF2_Handler', 'generate_commentdata' ), 1 );
	}

	/**
	 * all supported url types
	 *
	 * @return array
	 */
	public static function get_class_mapper() {
		$class_mapper = array();

		/*
		 * replies
		 * @link http://indiewebcamp.com/replies
		 */
		$class_mapper['in-reply-to'] = 'reply';
		$class_mapper['reply'] = 'reply';
		$class_mapper['reply-of'] = 'reply';

		/*
		 * repost
		 * @link http://indiewebcamp.com/repost
		 */
		$class_mapper['repost'] = 'repost';
		$class_mapper['repost-of'] = 'repost';

		/*
		 * likes
		 * @link http://indiewebcamp.com/likes
		 */
		$class_mapper['like'] = 'like';
		$class_mapper['like-of'] = 'like';

		/*
		 * favorite
		 * @link http://indiewebcamp.com/favorite
		 */
		$class_mapper['favorite'] = 'favorite';
		$class_mapper['favorite-of'] = 'favorite';

		/*
		 * bookmark
		 * @link http://indiewebcamp.com/bookmark
		 */
		$class_mapper['bookmark'] = 'bookmark';
		$class_mapper['bookmark-of'] = 'bookmark';

		/*
		 * rsvp
		 * @link http://indiewebcamp.com/rsvp
		 */
		$class_mapper['rsvp'] = 'rsvp';

		/*
		 * tag
		 * @link http://indiewebcamp.com/tag
		 */
		$class_mapper['tag-of'] = 'tag';

		return apply_filters( 'semantic_linkbacks_microformats_class_mapper', $class_mapper );
	}

	/**
	 * all supported url types
	 *
	 * @return array
	 */
	public static function get_rel_mapper() {
		$rel_mapper = array();

		/*
		 * replies
		 * @link http://indiewebcamp.com/in-reply-to
		 */
		$rel_mapper['in-reply-to'] = 'reply';
		$rel_mapper['reply-of'] = 'reply';

		return apply_filters( 'semantic_linkbacks_microformats_rel_mapper', $rel_mapper );
	}

	/**
	 * generate the comment data from the microformatted content
	 *
	 * @param WP_Comment $commentdata the comment object
	 *
	 * @return array
	 */
	public static function generate_commentdata( $commentdata ) {
		global $wpdb;

		// Use new webmention source meta key.
		if ( array_key_exists( 'webmention_source_url', $commentdata['comment_meta'] ) ) {
			$source = $commentdata['comment_meta']['webmention_source_url'];
		} // Fallback to comment author url.
		else {
			$source = $commentdata['comment_author_url'];
		}

		// parse source html
		$parser = new Parser( $commentdata['remote_source_original'], $source );
		$mf_array = $parser->parse( true );
		// get all 'relevant' entries
		$commentdata['remote_source_mf2'] = $item = self::get_representative_item( $mf_array, $source );

		if ( ! $item ) {
			// If the system could not find any types then declare it a mention and give up
			$commentdata['comment_meta']['semantic_linkbacks_type'] = wp_slash( 'mention' );
			return $commentdata;
		}
		$commentdata['remote_source_properties'] = $properties = array_filter( self::flatten_microformats( $item ) );

		// set the right date
		if ( array_key_exists( 'published', $properties ) ) {
			$commentdata['comment_date'] = self::convert_time( $properties['published'] );
		} elseif ( array_key_exists( 'updated', $properties ) ) {
			$commentdata['comment_date'] = self::convert_time( $properties['updated'] );
		}

		// set canonical url (u-url)
		if ( array_key_exists( 'url', $properties ) ) {
			$commentdata['comment_meta']['semantic_linkbacks_canonical'] = $properties['url'];
		}

		// try to find some content
		// @link http://indiewebcamp.com/comments-presentation
		if ( array_key_exists( 'summary', $properties ) ) {
			$commentdata['comment_content'] = wp_slash( $properties['summary'] );
		} elseif ( array_key_exists( 'content', $properties ) ) {
			$commentdata['comment_content'] = wp_filter_kses( $properties['content']['html'] );
		} elseif ( array_key_exists( 'name', $properties ) ) {
			$commentdata['comment_content'] = wp_slash( $properties['name'] );
		}
		$commentdata['comment_content'] = trim( $commentdata['comment_content'] );

		$author = null;

		// check if h-card has an author
		if ( isset( $properties['author'] ) ) {
			$author = $properties['author'];
		} else {
			$author = self::get_representative_author( $mf_array, $source );
		}

		// if author is present use the informations for the comment
		if ( $author ) {
			if ( ! is_array( $author ) ) {
				$comment_data['comment_author'] = wp_slash( $author );
			} else {
				if ( array_key_exists( 'name', $author ) ) {
					$commentdata['comment_author'] = $author['name'];
				}
				if ( array_key_exists( 'email', $author ) ) {
					$commentdata['comment_author_email'] = $author['email'];
				}
				if ( array_key_exists( 'url', $author ) ) {
					$commentdata['comment_meta']['semantic_linkbacks_author_url'] = $author['url'];
				}
				if ( array_key_exists( 'photo', $author ) ) {
					$commentdata['comment_meta']['semantic_linkbacks_avatar'] = $author['photo'];
				}
			}
		}

		// check rsvp property
		if ( array_key_exists( 'rsvp', $properties ) ) {
			$commentdata['comment_meta']['semantic_linkbacks_type'] = 'rsvp:' . $properties['rsvp'];
		} else {
			// get post type
			$commentdata['comment_meta']['semantic_linkbacks_type'] = self::get_entry_type( $commentdata['target'], $item, $mf_array );
		}
		$blacklist = array();
		// $blacklist = array( 'name', 'content', 'summary', 'published', 'updated', 'type', 'url' );
		foreach ( $properties as $key => $value ) {
			if ( ! in_array( $key, $blacklist ) ) {
				$commentdata['comment_meta'][ 'mf2_' . $key ] = $value;
			}
		}

		$commentdata['comment_meta'] = array_filter( $commentdata['comment_meta'] );
		return $commentdata;

	}

	public static function convert_time( $time ) {
		$time = strtotime( $time );
		return get_date_from_gmt( date( 'Y-m-d H:i:s', $time ), 'Y-m-d H:i:s' );
	}

	public static function get_property( $key, $properties ) {
		if ( isset( $properties[ $key ] ) && isset( $properties[ $key ][0] ) ) {
			$properties[ $key ] = array_unique( $properties[ $key ] );
			if ( 1 === count( $properties[ $key ] ) ) {
				return $properties[ $key ][0];
			}
			return $properties[ $key ];
		}
		return null;
	}

	/**
	 * Is string a URL.
		 *
	 * @param array $string
	 * @return bool
	 */
	public static function is_url( $string ) {
		if ( ! is_string( $string ) ) {
			return false;
		}
		return preg_match( '/^https?:\/\/.+\..+$/', $string );
	}

	// Accepted h types
	public static function is_h( $string ) {
		return in_array( $string, array( 'h-cite', 'h-entry', 'h-feed', 'h-product', 'h-event', 'h-review', 'h-recipe' ) );
	}

	public static function flatten_microformats( $item ) {
		$flat = array();
		if ( 1 === count( $item ) ) {
			$item = $item[0];
		}
		if ( array_key_exists( 'type', $item ) ) {
			// If there are multiple types strip out everything but the standard one.
			if ( 1 < count( $item['type'] ) ) {
				$item['type'] = array_filter( $item['type'], array( 'Linkbacks_MF2_Handler', 'is_h' ) );
			}
			$flat['type'] = $item['type'][0];
		}
		if ( array_key_exists( 'properties', $item ) ) {
			$properties = $item['properties'];
			foreach ( $properties as $key => $value ) {
				$flat[ $key ] = self::get_property( $key, $properties );
				if ( 1 < count( $flat[ $key ] ) ) {
					$flat[ $key ] = self::flatten_microformats( $flat[ $key ] );
				}
			}
		} else {
			$flat = $item;
		}
		foreach ( $flat as $key => $value ) {
			// Sanitize all URL properties
			if ( self::is_url( $value ) ) {
				$flat[ $key ] = esc_url_raw( $value );
			}
		}
		return array_filter( $flat );
	}

	/**
	 * helper to find the correct author node
	 *
	 * @param array $mf_array the parsed microformats array
	 * @param string $source the source url
	 *
	 * @return array|null the h-card node or null
	 */
	public static function get_representative_author( $mf_array, $source ) {
		foreach ( $mf_array['items'] as $mf ) {
			if ( isset( $mf['type'] ) ) {
				if ( in_array( 'h-card', $mf['type'] ) ) {
					// check domain
					if ( isset( $mf['properties'] ) && isset( $mf['properties']['url'] ) ) {
						foreach ( $mf['properties']['url'] as $url ) {
							if ( parse_url( $url, PHP_URL_HOST ) == parse_url( $source, PHP_URL_HOST ) ) {
								return $mf['properties'];
								break;
							}
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * helper to find the representative item
	 *
	 * @param array $mf_array the parsed microformats array
	 * @param string $target the target url
	 *
	 * @return array representative item or false if none
	 */
	public static function get_representative_item( $mf_array, $target ) {
		// some basic checks
		if ( ! is_array( $mf_array ) ) {
			return false;
		}
		if ( ! isset( $mf_array['items'] ) ) {
			return false;
		}
		$items = $mf_array['items'];
		if ( 0 == count( $items ) ) {
			return false;
		}

		if ( 1 == count( $items ) ) {
			// return first item
			return $items[0];
		}

		// Else look for the top level item that matches the URL
		foreach ( $items as $item ) {
			// check properties
			if ( array_key_exists( 'url', $item['properties'] ) ) {
				$urls = $item['properties']['url'];
				if ( self::compare_urls( $target, $urls ) ) {
					if ( ! in_array( 'h-feed', $item['type'] ) ) {
						return $item;
					}
				}
			}
		}
		// Check for an h-card matching rel=author or the author URL of any h-* on the page and return the h-* object if so
		if ( isset( $mf_array['rels']['author'] ) ) {
			foreach ( $items as $card ) {
				if ( in_array( 'h-card', $card['type'] ) && array_key_exists( 'url', $card['properties'] ) ) {
					$urls = $card['properties']['url'];
					if ( count( array_intersect( $urls, $mf_array['rels']['author'] ) ) > 0 ) {
						// There is an author h-card on this page
						// Now look for the first h-* object other than an h-card and use that as the object
						foreach ( $items as $item ) {
							if ( ! in_array( 'h-card', $item['type'] ) ) {
								return $item;
							}
						}
					}
				}
			}
		}
		// Not Sure What This Is
		return false;
	}

	/**
	 * check entry classes or document rels for post-type
	 *
	 * @param string $target the target url
	 * @param array $entry the represantative entry
	 * @param array $mf_array the document
	 *
	 * @return string the post-type
	 */
	public static function get_entry_type( $target, $entry, $mf_array = array() ) {
		$classes = self::get_class_mapper();

		// check properties for target-url
		foreach ( $entry['properties'] as $key => $values ) {
			// check u-* params
			if ( in_array( $key, array_keys( $classes ) ) ) {
				// check "normal" links
				if ( self::compare_urls( $target, $values ) ) {
					return $classes[ $key ];
				}

				// iterate in-reply-tos
				foreach ( $values as $obj ) {
					// check if reply is a "cite" or "entry"
					if ( isset( $obj['type'] ) && in_array( $obj['type'], array( 'h-cite', 'h-entry' ) ) ) {
						// check url
						if ( isset( $obj['url'] ) ) {
							// check target
							if ( self::compare_urls( $target, $obj['url'] ) ) {
								return $classes[ $key ];
							}
						}
					}
				}
			}
		}

		// check if site has any rels
		if ( ! isset( $mf_array['rels'] ) ) {
			return 'mention';
		}

		$rels = self::get_rel_mapper();

		// check rels for target-url
		foreach ( $mf_array['rels'] as $key => $values ) {
			// check rel params
			if ( in_array( $key, array_keys( $rels ) ) ) {
				foreach ( $values as $value ) {
					if ( $value == $target ) {
						return $rels[ $key ];
					}
				}
			}
		}

		return 'mention';
	}

	/**
	 * compare an url with a list of urls
	 *
	 * @param string $needle the target url
	 * @param array $haystack a list of urls
	 * @param boolean $schemelesse define if the target url should be checked with http:// and https://
	 *
	 * @return boolean
	 */
	public static function compare_urls( $needle, $haystack, $schemeless = true ) {
		if ( ! self::is_url( $needle ) || ! $haystack ) {
			return false;
		}
		if ( ! is_array( $haystack ) && is_url( $haystack ) ) {
			$haystack = array( $haystack );
		}
		if ( true === $schemeless ) {
			// remove url-scheme
			$schemeless_target = preg_replace( '/^https?:\/\//i', '', $needle );

			// add both urls to the needle
			$needle = array( 'http://' . $schemeless_target, 'https://' . $schemeless_target );
		} else {
			// make $needle an array
			$needle = array( $needle );
		}

		// compare both arrays
		return array_intersect( $needle, $haystack );
	}
}
