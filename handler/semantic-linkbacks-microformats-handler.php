<?php
if ( ! class_exists( 'Mf2\Parser' ) ) {
	require_once 'Mf2/Parser.php';
}

use Mf2\Parser;

add_action( 'init', array( 'SemanticLinkbacksPlugin_MicroformatsHandler', 'init' ) );

/**
 * provides a microformats handler for the semantic linkbacks
 * WordPress plugin
 *
 * @author Matthias Pfefferle
 */
class SemanticLinkbacksPlugin_MicroformatsHandler {
	/**
	 * initialize the plugin, registering WordPess hooks.
	 */
	public static function init() {
		add_filter( 'semantic_linkbacks_commentdata', array( 'SemanticLinkbacksPlugin_MicroformatsHandler', 'generate_commentdata' ), 1, 4 );
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
		 * mentions
		 * @link http://indiewebcamp.com/mentions
		 */
		//$class_mapper["mention"] = "mention";

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
		$rel_mapper['reply-of']	= 'reply';

		return apply_filters( 'semantic_linkbacks_microformats_rel_mapper', $rel_mapper );
	}

	/**
	 * generate the comment data from the microformatted content
	 *
	 * @param WP_Comment $commentdata the comment object
	 * @param string $target the target url
	 * @param string $html the parsed html
	 */
	public static function generate_commentdata( $commentdata, $target, $html ) {
		global $wpdb;

		// add source
		$source = $commentdata['comment_author_url'];

		// parse source html
		$parser = new Parser( $html, $source );
		$mf_array = $parser->parse( true );

		// get all 'relevant' entries
		$entries = self::get_entries( $mf_array );

		if ( empty( $entries ) ) {
			return array();
		}

		// get the entry of interest
		$entry = self::get_representative_entry( $entries, $target );

		if ( empty( $entry ) ) {
			return array();
		}

		// the entry properties
		$properties = $entry['properties'];

		// try to find some content
		// @link http://indiewebcamp.com/comments-presentation
		if ( self::check_mf_attr( 'summary', $properties ) ) {
			$commentdata['comment_content'] = wp_slash( $properties['summary'][0] );
		} elseif ( self::check_mf_attr( 'content', $properties ) ) {
			$commentdata['comment_content'] = wp_filter_kses( $properties['content'][0]['html'] );
		} elseif ( self::check_mf_attr( 'name', $properties ) ) {
			$commentdata['comment_content'] = wp_slash( $properties['name'][0] );
		}
		$commentdata['comment_content'] = trim( $commentdata['comment_content'] );

		// set the right date
		if ( self::check_mf_attr( 'published', $properties ) ) {
			$time = strtotime( $properties['published'][0] );
			$commentdata['comment_date'] = get_date_from_gmt( date( 'Y-m-d H:i:s', $time ), 'Y-m-d H:i:s' );
		} elseif ( self::check_mf_attr( 'updated', $properties ) ) {
			$time = strtotime( $properties['updated'][0] );
			$commentdata['comment_date'] = get_date_from_gmt( date( 'Y-m-d H:i:s', $time ), 'Y-m-d H:i:s' );
		}

		$author = null;

		// check if h-card has an author
		if ( isset( $properties['author'] ) && isset( $properties['author'][0]['properties'] ) ) {
			$author = $properties['author'][0]['properties'];
		} else {
			$author = self::get_representative_author( $mf_array, $source );
		}

		// if author is present use the informations for the comment
		if ( $author ) {
			if ( self::check_mf_attr( 'name', $author ) ) {
				$commentdata['comment_author'] = wp_slash( $author['name'][0] );
			}

			if ( self::check_mf_attr( 'email', $author ) ) {
				$commentdata['comment_author_email'] = wp_slash( $author['email'][0] );
			}

			if ( self::check_mf_attr( 'url', $author ) ) {
				$commentdata['_author_url'] = esc_url_raw( $author['url'][0] );
			}

			if ( self::check_mf_attr( 'photo', $author ) ) {
				$commentdata['_avatar'] = esc_url_raw( $author['photo'][0] );
			}
		}

		// set canonical url (u-url)
		if ( self::check_mf_attr( 'url', $properties ) ) {
			$commentdata['_canonical'] = esc_url_raw( $properties['url'][0] );
		} else {
			$commentdata['_canonical'] = esc_url_raw( $source );
		}

		// check rsvp property
		if ( self::check_mf_attr( 'rsvp', $properties ) ) {
			$commentdata['_type'] = wp_slash( 'rsvp:'.$properties['rsvp'][0] );
		} else {
			// get post type
			$commentdata['_type'] = wp_slash( self::get_entry_type( $target, $entry, $mf_array ) );
		}

		return $commentdata;
	}

	/**
	 * get all h-entry items
	 *
	 * @param array $mf_array the microformats array
	 * @param array the h-entry array
	 */
	public static function get_entries( $mf_array ) {
		$entries = array();

		// some basic checks
		if ( ! is_array( $mf_array ) ) {
			return $entries;
		}
		if ( ! isset( $mf_array['items'] ) ) {
			return $entries;
		}
		if ( 0 == count( $mf_array['items'] ) ) {
			return $entries;
		}

		// get first item
		$first_item = $mf_array['items'][0];

		// check if it is an h-feed
		if ( isset( $first_item['type'] ) &&
			in_array( 'h-feed', $first_item['type'] ) &&
			isset( $first_item['children'] ) ) {
			$mf_array['items'] = $first_item['children'];
		}

		// iterate array
		foreach ( $mf_array['items'] as $mf ) {
			if ( isset( $mf['type'] ) && in_array( 'h-entry', $mf['type'] ) ) {
				$entries[] = $mf;
			}
		}

		// return entries
		return $entries;
	}

	/**
	 * helper to find the correct author node
	 *
	 * @param array $mf_array the parsed microformats array
	 * @param string $source the source url
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
	 * helper to find the correct h-entry node
	 *
	 * @param array $mf_array the parsed microformats array
	 * @param string $target the target url
	 * @return array the h-entry node or false
	 */
	public static function get_representative_entry( $entries, $target ) {
		// iterate array
		foreach ( $entries as $entry ) {
			// check properties
			if ( isset( $entry['properties'] ) ) {
				// check properties if target urls was mentioned
				foreach ( $entry['properties'] as $key => $values ) {
					// check "normal" links
					if ( self::compare_urls( $target, $values ) ) {
						return $entry;
					}

					// check included h-* formats and their links
					foreach ( $values as $obj ) {
						// check if reply is a "cite"
						if ( isset( $obj['type'] ) && array_intersect( array( 'h-cite', 'h-entry' ), $obj['type'] ) ) {
							// check url
							if ( isset( $obj['properties'] ) && isset( $obj['properties']['url'] ) ) {
								// check target
								if ( self::compare_urls( $target, $obj['properties']['url'] ) ) {
									return $entry;
								}
							}
						}
					}
				}

				// check properties if target urls was mentioned
				foreach ( $entry['properties'] as $key => $values ) {
					// check content for the link
					if ( 'content' == $key &&
						preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $values[0]['html'], $context ) ) {
						return $entry;
					} elseif ( 'summary' == $key &&
						preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i',  $values[0], $context ) ) {
						return $entry;
					}
				}
			}
		}

		// return first h-entry
		return $entries[0];
	}

	/**
	 * check entry classes or document rels for post-type
	 *
	 * @param string $target the target url
	 * @param array $entry the represantative entry
	 * @param array $mf_array the document
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
					if ( isset( $obj['type'] ) && array_intersect( array( 'h-cite', 'h-entry' ), $obj['type'] ) ) {
						// check url
						if ( isset( $obj['properties'] ) && isset( $obj['properties']['url'] ) ) {
							// check target
							if ( self::compare_urls( $target, $obj['properties']['url'] ) ) {
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
	 * checks if $node has $key
	 *
	 * @param string $key the array key to check
	 * @param array $node the array to be checked
	 *
	 * @return boolean
	 */
	public static function check_mf_attr($key, $node) {
		if ( isset( $node[ $key ] ) && isset( $node[ $key ][0] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * compare an url with a list of urls
	 *
	 * @param string $needle the target url
	 * @param array $haystack a list of urls
	 * @param boolean $schemelesse define if the target url should be checked
	 *		with http:// and https://
	 * @return boolean
	 */
	public static function compare_urls($needle, $haystack, $schemeless = true) {
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
