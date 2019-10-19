<?php
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
		 * @link http://indieweb.org/replies
		 */
		$class_mapper['in-reply-to'] = 'reply';
		$class_mapper['reply']       = 'reply';
		$class_mapper['reply-of']    = 'reply';

		/*
		 * repost
		 * @link http://indieweb.org/repost
		 */
		$class_mapper['repost']    = 'repost';
		$class_mapper['repost-of'] = 'repost';

		/*
		 * likes
		 * @link http://indieweb.org/likes
		 */
		$class_mapper['like']    = 'like';
		$class_mapper['like-of'] = 'like';

		/*
		 * favorite
		 * @link http://indieweb.org/favorite
		 */
		$class_mapper['favorite']    = 'favorite';
		$class_mapper['favorite-of'] = 'favorite';

		/*
		 * bookmark
		 * @link http://indieweb.org/bookmark
		 */
		$class_mapper['bookmark']    = 'bookmark';
		$class_mapper['bookmark-of'] = 'bookmark';

		/*
		 * rsvp
		 * @link http://indieweb.org/rsvp
		 */
		$class_mapper['rsvp'] = 'rsvp';
		/*
		 * invite
		 * @link https://indieweb.org/invitation
		 */
		$class_mapper['invitee'] = 'invite';

		/*
		 * tag
		 * @link http://indieweb.org/tag
		 */
		$class_mapper['tag-of']   = 'tag';
		$class_mapper['category'] = 'tag';

		/*
		 * read
		 * @link http://indieweb.org/read
		 */
		$class_mapper['read-of'] = 'read';
		$class_mapper['read']    = 'read';

		/*
		 * listen
		 * @link http://indieweb.org/listen
		 */
		$class_mapper['listen-of'] = 'listen';
		$class_mapper['listen']    = 'listen';

		/*
		 * watch
		 * @link http://indieweb.org/watch
		 */
		$class_mapper['watch-of'] = 'watch';
		$class_mapper['watch']    = 'watch';

		/*
		 * follow
		 * @link http://indieweb.org/follow
		 */
		$class_mapper['follow-of'] = 'follow';

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
		 * @link http://indieweb.org/in-reply-to
		 */
		$rel_mapper['in-reply-to'] = 'reply';
		$rel_mapper['reply-of']    = 'reply';

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
		// Use new webmention source meta key.
		if ( array_key_exists( 'webmention_source_url', $commentdata['comment_meta'] ) ) {
			$source = $commentdata['comment_meta']['webmention_source_url'];
		} else { // Fallback to comment author url.
			$source = $commentdata['comment_author_url'];
		}
		if ( ! class_exists( 'Mf2\Parser' ) ) {
			require_once plugin_dir_path( __DIR__ ) . 'vendor/mf2/mf2/Mf2/Parser.php';
		}

		// parse source html
		$parser   = new Parser( $commentdata['remote_source_original'], $source );
		$mf_array = $parser->parse( true );

		// check for rel-alternate links
		$alternate_source = self::get_alternate_source( $mf_array );
		if ( $alternate_source ) {
			$mf_array = $alternate_source;
		}

		// get all 'relevant' entries
		$entries = self::get_entries( $mf_array );

		if ( empty( $entries ) ) {
			return array();
		}

		// get the entry of interest
		$entry = self::get_representative_entry( $entries, $commentdata['target'] );

		if ( empty( $entry ) ) {
			return array();
		}

		$commentdata['remote_source_mf2']        = $entry;
		$properties                              = array_filter( self::flatten_microformats( $entry ) );
		$commentdata['remote_source_properties'] = $properties;
		$rels                                    = $mf_array['rels'];
		$commentdata['remote_source_rels']       = $rels;

		// try to find some content
		// @link http://indieweb.org/comments-presentation
		if ( isset( $properties['summary'] ) ) {
			$commentdata['comment_content'] = wp_slash( $properties['summary'] );
		} elseif ( isset( $properties['content'] ) ) {
			$commentdata['comment_content'] = wp_filter_kses( $properties['content']['html'] );
		} elseif ( isset( $properties['name'] ) ) {
			$commentdata['comment_content'] = wp_slash( $properties['name'] );
		}
		$commentdata['comment_content'] = trim( $commentdata['comment_content'] );

		// set the right date
		if ( isset( $properties['published'] ) ) {
			$commentdata['comment_date'] = self::convert_time( $properties['published'] );
		} elseif ( isset( $properties['updated'] ) ) {
			$commentdata['comment_date'] = self::convert_time( $properties['updated'] );
		}
		$commentdata['comment_date_gmt'] = get_gmt_from_date( $commentdata['comment_date'] );

		$author = null;

		// check if h-card has an author
		if ( isset( $properties['author'] ) ) {
			$author = $properties['author'];
		} else {
			$author = self::get_representative_author( $mf_array, $source );
			$author = self::flatten_microformats( $author );
		}
		// If this is an invite than the invite should be displayed instead of the author
		if ( isset( $properties['invitee'] ) ) {
			$author = $properties['invitee'];
		}

		// if author is present use the informations for the comment
		if ( $author ) {
			if ( ! is_array( $author ) ) {
				if ( self::is_url( $author ) ) {
					$response = Linkbacks_Handler::retrieve( $author );
					if ( ! is_wp_error( $response ) ) {
						$parser               = new Parser( wp_remote_retrieve_body( $response ), $author );
						$author_array         = $parser->parse( true );
						$author               = self::flatten_microformats( self::get_representative_author( $author_array, $author ) );
						$properties['author'] = $author;
					}
				} else {
					$commentdata['comment_author'] = wp_slash( $author );
				}
			}

			if ( is_array( $author ) ) {
				if ( ! isset( $author['me'] ) ) {
					if ( isset( $mf_array['rels']['me'] ) ) {
						$author['me']               = $mf_array['rels']['me'];
						$properties['author']['me'] = $author['me'];
					}
				}
				if ( isset( $author['name'] ) ) {
					$commentdata['comment_author'] = wp_slash( self::first( $author['name'] ) );
				}

				if ( isset( $author['email'] ) ) {
					$commentdata['comment_author_email'] = wp_slash( self::first( wp_parse_url( $author['email'], PHP_URL_PATH ) ) );
				}

				if ( isset( $author['url'] ) ) {
					$commentdata['comment_meta']['semantic_linkbacks_author_url'] = self::first( $author['url'] );
				}

				if ( isset( $author['photo'] ) ) {
					$commentdata['comment_meta']['avatar'] = self::first( $author['photo'] );
				}
			}
		}

		// set canonical url (u-url)
		if ( isset( $properties['url'] ) ) {
			$commentdata['comment_meta']['semantic_linkbacks_canonical'] = self::first( $properties['url'] );
			// If  the source URL and the canonical URL are not on the same domain, set this flag in the comment data to trigger actions in the Linkbacks_Handler class
			if ( wp_parse_url( $properties['url'], PHP_URL_HOST ) !== wp_parse_url( $source, PHP_URL_HOST ) ) {
				$commentdata['proxy_mention'] = 1;
			}
		} else {
			$commentdata['comment_meta']['semantic_linkbacks_canonical'] = esc_url_raw( $source );
		}

		// If u-syndication is not set use rel syndication
		if ( array_key_exists( 'syndication', $rels ) && ! array_key_exists( 'syndication', $properties ) ) {
			$properties['syndication'] = $rels['syndication'];
		}

		// Check and parse for location property
		if ( array_key_exists( 'location', $properties ) ) {
			$location = $properties['location'];
			if ( is_array( $location ) ) {
				if ( array_key_exists( 'latitude', $location ) ) {
					$commentdata['comment_meta']['geo_latitude'] = self::first( $location['latitude'] );
				}
				if ( array_key_exists( 'longitude', $location ) ) {
					$commentdata['comment_meta']['geo_longitude'] = self::first( $location['longitude'] );
				}
				if ( array_key_exists( 'name', $location ) ) {
					$commentdata['comment_meta']['geo_address'] = self::first( $location['name'] );
				}
			} else {
				if ( substr( $location, 0, 4 ) === 'geo:' ) {
					$geo    = explode( ':', substr( urldecode( $location ), 4 ) );
					$geo    = explode( ';', $geo[0] );
					$coords = explode( ',', $geo[0] );
					$commentdata['comment_meta']['geo_latitude']  = trim( $coords[0] );
					$commentdata['comment_meta']['geo_longitude'] = trim( $coords[1] );
				} else {
					$commentdata['comment_meta']['geo_address'] = $location;
				}
			}
		}

		// check rsvp property
		if ( isset( $properties['rsvp'] ) ) {
			$commentdata['comment_meta']['semantic_linkbacks_type'] = wp_slash( 'rsvp:' . self::first( $properties['rsvp'] ) );
		} else {
			// get post type
			$commentdata['comment_meta']['semantic_linkbacks_type'] = wp_slash( self::get_entry_type( $commentdata['target'], $entry, $mf_array ) );
		}
		if ( isset( $properties['invitee'] ) ) {
			$commentdata['comment_meta']['semantic_linkbacks_type'] = wp_slash( 'invite' );
		}

		// Check for person tagging
		if ( isset( $properties['category'] ) ) {
			if ( in_array( $commentdata['target'], $properties['category'], true ) ) {
				$commentdata['category'] = array( $commentdata['target'] );
			}
		}

		$whitelist = array(
			'author',
			'location',
			'syndication',
			'uid',
			'video',
			'audio',
			'photo',
			'featured',
			'swarm-coins', // https://ownyourswarm.p3k.io/docs#coins
			'read-status', // https://indieweb.org/read
		);

		// Add in supported properties
		$whitelist = array_merge( $whitelist, array_keys( self::get_class_mapper() ) );
		$whitelist = apply_filters( 'semantic_linkbacks_mf2_props_whitelist', $whitelist );
		foreach ( $properties as $key => $value ) {
			if ( in_array( $key, $whitelist, true ) ) {
				if ( self::is_url( $value ) ) {
					$value = esc_url_raw( $value );
				}
				if ( is_string( $value ) ) {
					$value = wp_kses_post( $value );
				}
				$commentdata['comment_meta'][ 'mf2_' . $key ] = $value;
			}
		}
		$commentdata['comment_meta'] = array_filter( $commentdata['comment_meta'] );

		return $commentdata;
	}

	public static function convert_time( $time ) {
		$time = strtotime( $time );
		// If it can't read the time it will return null which will mean the comment time will be set to now.
		if ( $time ) {
			return get_date_from_gmt( date( 'Y-m-d H:i:s', $time ), 'Y-m-d H:i:s' );
		}
		return null;
	}

	public static function get_property( $key, $properties ) {
		if ( isset( $properties[ $key ] ) && isset( $properties[ $key ][0] ) ) {
			if ( is_array( $properties[ $key ] ) ) {
				$properties[ $key ] = array_unique( $properties[ $key ] );
			}
			if ( 1 === count( $properties[ $key ] ) ) {
				return $properties[ $key ][0];
			}
			return $properties[ $key ];
		}
		return null;
	}

	/**
	 * Returns the first item in $val if it's a non-empty array, otherwise $val itself.
	 */
	public static function first( $val ) {
		if ( $val && is_array( $val ) ) {
			return $val[0];
		}
		return $val;
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
		// If debugging is on just validate that URL is validly formatted
		if ( WP_DEBUG ) {
			return filter_var( $string, FILTER_VALIDATE_URL ) !== false;
		}
		// If debugging is off limit based on WordPress parameters
		return wp_http_validate_url( $string );
	}

	// Accepted h types
	public static function is_h( $string ) {
		return in_array( $string, array( 'h-cite', 'h-entry', 'h-feed', 'h-product', 'h-event', 'h-review', 'h-recipe' ), true );
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

				if ( ! is_string( $flat[ $key ] ) && 1 < count( $flat[ $key ] ) ) {
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

		// If name and URL are the same, remove name.
		if ( array_key_exists( 'name', $flat ) && array_key_exists( 'url', $flat ) ) {
			if ( $flat['name'] === $flat['url'] ) {
				unset( $flat['name'] );
			}
		}

		// Duplicate url values for a property may be caused by implied urls https://github.com/indieweb/php-mf2/issues/110
		if ( array_key_exists( 'url', $flat ) && is_array( $flat['url'] ) ) {
			$flat['url'] = $flat['url'][0];
		}
		$flat = array_filter( $flat );
		return $flat;
	}

	public static function has_alternate_url( $mf_array ) {
		return (bool) self::get_alternate_url( $mf_array );
	}

	public static function get_alternate_url( $mf_array ) {
		if ( ! array_key_exists( 'rel-urls', $mf_array ) ) {
			return false;
		}

		foreach ( $mf_array['rel-urls'] as $url => $meta ) {
			if (
				'application/mf2+json' === trim( $meta['type'] ) &&
				in_array( 'alternate', $meta['rels'], true ) &&
				filter_var( $url, FILTER_VALIDATE_URL ) !== false
			) {
				return $url;
			}
		}

		return false;
	}

	public static function get_alternate_source( $mf_array ) {
		$url = self::get_alternate_url( $mf_array );

		if ( ! $url ) {
			return false;
		}

		$user_agent = apply_filters( 'http_headers_useragent', 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' ) );
		$args       = array(
			'timeout'             => 100,
			'limit_response_size' => 153600,
			'redirection'         => 20,
			'user-agent'          => "$user_agent; Semantic-Linkbacks/Webmention read rel-alternate source",
		);

		$response = wp_safe_remote_get( $url, $args );
		// check if source is accessible
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		return json_decode( $body, true );
	}

	/**
	 * get all h-entry items
	 *
	 * @param array $mf_array the microformats array
	 * @param array the h-entry array
	 *
	 * @return array
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
		if ( 0 === count( $mf_array['items'] ) ) {
			return $entries;
		}

		// get first item
		$first_item = $mf_array['items'][0];

		// check if it is an h-feed
		if ( isset( $first_item['type'] ) &&
			in_array( 'h-feed', $first_item['type'], true ) &&
			isset( $first_item['children'] ) ) {
			$mf_array['items'] = $first_item['children'];
		}

		// iterate array
		foreach ( $mf_array['items'] as $mf ) {
			if ( isset( $mf['type'] ) && in_array( 'h-entry', $mf['type'], true ) ) {
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
	 *
	 * @return array|null the h-card node or null
	 */
	public static function get_representative_author( $mf_array, $source ) {
		foreach ( $mf_array['items'] as $mf ) {
			if ( isset( $mf['type'] ) ) {
				if ( in_array( 'h-card', $mf['type'], true ) ) {
					// check domain
					if ( isset( $mf['properties'] ) && isset( $mf['properties']['url'] ) ) {
						foreach ( $mf['properties']['url'] as $url ) {
							if ( wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( $source, PHP_URL_HOST ) ) {
								if ( isset( $mf_array['rels']['me'] ) ) {
									$mf['properties']['me'] = $mf_array['rels']['me'];
								}
								return $mf;
							}
						}
					}
				}
			}
		}

		// If there is no h-card then return rel=author and see what can be done with it
		if ( isset( $mf_array['rels']['author'] ) ) {
			return $mf_array['rels']['author'];
		}

		return null;
	}

	/**
	 * helper to find the correct h-entry node
	 *
	 * @param array $mf_array the parsed microformats array
	 * @param string $target the target url
	 *
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
					if ( 'content' === $key &&
						preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $values[0]['html'], $context ) ) {
						return $entry;
					} elseif ( 'summary' === $key &&
						preg_match_all( '/<a[^>]+?' . preg_quote( $target, '/' ) . '[^>]*>([^>]+?)<\/a>/i', $values[0], $context ) ) {
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
	 *
	 * @return string the post-type
	 */
	public static function get_entry_type( $target, $entry, $mf_array = array() ) {
		$classes = self::get_class_mapper();

		// check properties for target-url
		foreach ( $entry['properties'] as $key => $values ) {
			// check u-* params
			if ( in_array( $key, array_keys( $classes ), true ) ) {
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
			if ( in_array( $key, array_keys( $rels ), true ) ) {
				foreach ( $values as $value ) {
					if ( $value === $target ) {
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
		if ( ! self::is_url( $needle ) ) {
			return false;
		}
		if ( is_array( reset( $haystack ) ) ) {
			return false;
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
