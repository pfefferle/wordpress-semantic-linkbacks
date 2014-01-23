<?php
/*
 Plugin Name: Semantic-Linkbacks
 Plugin URI: https://github.com/acegiak/Semantic-Linkbacks
 Description: Semantic Linkbacks for webmentions, trackbacks and pingbacks
 Author: pfefferle & acegiak
 Author URI: http://notizblog.org/
 Version: 2.0.1-dev
*/

require_once "semantic-linkbacks-microformats-handler.php";

add_action('init', array( 'SemanticLinkbacksPlugin', 'init' ));

/**
 * semantic linkbacks class
 *
 * @author Matthias Pfefferle
 */
class SemanticLinkbacksPlugin {
  /**
   * Initialize the plugin, registering WordPess hooks.
   */
  public static function init() {
    // hook into linkback functions to add more semantics
    add_action('pingback_post', array( 'SemanticLinkbacksPlugin', 'linkback_fix' ));
    add_action('trackback_post', array( 'SemanticLinkbacksPlugin', 'linkback_fix' ));
    add_action('webmention_post', array( 'SemanticLinkbacksPlugin', 'linkback_fix' ));

    add_filter('comment_text', array( 'SemanticLinkbacksPlugin', 'comment_text_add_cite'), 11, 3);
    add_filter('comment_text', array( 'SemanticLinkbacksPlugin', 'comment_text_excerpt'), 12, 3);
    add_filter('get_comment_link', array( 'SemanticLinkbacksPlugin', 'get_comment_link' ), 99, 3);
  }

  /**
   * nicer semantic linkbacks
   *
   * @param int $comment_ID the comment id
   */
  public static function linkback_fix($comment_ID) {
    // return if comment_ID is empty
    if (!$comment_ID) {
      return $comment_ID;
    }

    // check if it is a valid comment
    $commentdata = get_comment($comment_ID, ARRAY_A);

    // check if there is any comment-data
    if (!$commentdata) {
      return $comment_ID;
    }

    // source
    $source = esc_url_raw($commentdata['comment_author_url']);

    // check if there is already a matching comment
    if ( $comments = get_comments( array('meta_key' => 'semantic_linkbacks_source', 'meta_value' => $source) ) ) {
      $comment = $comments[0];

      if ($comment_ID != $comment->comment_ID) {
        wp_delete_comment($commentdata['comment_ID'], true);

        $commentdata['comment_ID'] = $comment->comment_ID;
        $commentdata['comment_approved'] = $comment->comment_approved;
      } else {
        $commentdata['comment_ID'] = $comment_ID;
      }
    }

    // check if post exists
    $post = get_post($commentdata['comment_post_ID'], ARRAY_A);

    if (!$post) {
      return $comment_ID;
    }

    // get remote html
    $target = get_permalink( $post['ID'] );
    $response = wp_remote_get( esc_url_raw(html_entity_decode($source)) );

    // handle errors
    if ( is_wp_error( $response ) ) {
      return $comment_ID;
    }

    // get HTML code of source url
    $html = wp_remote_retrieve_body( $response );

    // adds a hook to enable some other semantic handlers for example schema.org
    $commentdata = apply_filters("semantic_linkbacks_commentdata", $commentdata, $target, $html);

    if (empty($commentdata)) {
      return $comment_ID;
    }

    // check if there is a parent comment
    if ( !isset($commentdata['comment_parent']) && $query = parse_url($target, PHP_URL_QUERY) ) {
      parse_str($query);
      if (isset($replytocom) && get_comment($replytocom)) {
        $commentdata['comment_parent'] = $replytocom;
      }
    }

    // update comment
    wp_update_comment($commentdata);

    return $comment_ID;
  }

	/**
	 * returns an array of comment type verbs to their translated and pretty display versions
	 *
	 * @return array The array of translated post format names.
	 */
  public static function get_comment_type_verbs() {
    $strings = array(
      'mention'  => _x( 'mentioned', 'semantic_linkbacks' ), // Special case. any value that evals to false will be considered standard

      'reply'    => _x( 'replied',   'semantic_linkbacks' ),
      'repost'   => _x( 'reposted',  'semantic_linkbacks' ),
      'like'     => _x( 'liked',     'semantic_linkbacks' ),
      'favorite' => _x( 'favorited', 'semantic_linkbacks' ),
    );
    return $strings;
	}

	/**
	 * returns an array of comment type slugs to their translated and pretty display versions
	 *
	 * @return array The array of translated comment type names.
	 */
  public static function get_comment_type_strings() {
    $strings = array(
      'mention'  => _x( 'Mention',   'semantic_linkbacks' ), // Special case. any value that evals to false will be considered standard

      'reply'    => _x( 'Reply',     'semantic_linkbacks' ),
      'repost'   => _x( 'Repost',    'semantic_linkbacks' ),
      'like'     => _x( 'Like',      'semantic_linkbacks' ),
      'favorite' => _x( 'Favorite',  'semantic_linkbacks' ),
    );
    return $strings;
	}

  /**
   * add cite to "reply"s
   *
   * @param string $text the comment text
   * @param WP_Comment $comment the comment object
   * @param array $args a list of arguments
   * @return string the filtered comment text
   */
  public static function comment_text_add_cite($text, $comment = null, $args = array()) {
    // only change text for pinbacks/trackbacks/webmentions
    if (!$comment) {
      return $text;
    }

    // thanks to @snarfed for the idea
    if ($comment->comment_type == "" && $canonical = get_comment_meta($comment->comment_ID, "semantic_linkbacks_canonical", true)) {
      $host = parse_url($canonical, PHP_URL_HOST);

      // strip leading www, if any
      $host = preg_replace("/^www\./", "", $host);
      // note that WordPress's sanitization strips the class="u-url". sigh. :/ also,
      // <cite> is one of the few elements that make it through the sanitization and
      // is still uncommon enough that we can use it for styling.
      $text .= '<p><small>&mdash;&nbsp;<cite><a class="u-url" href="' . $canonical . '">via ' . $host .
                      '</a></cite></small></p>';
    }

    return apply_filters("semantic_linkbacks_cite", $text);
  }

  /**
   * generate excerpt for all types except "reply"
   *
   * @param string $text the comment text
   * @param WP_Comment $comment the comment object
   * @param array $args a list of arguments
   * @return string the filtered comment text
   */
  public static function comment_text_excerpt($text, $comment = null, $args = array()) {
    // only change text for pinbacks/trackbacks/webmentions
    if (!$comment || $comment->comment_type == "") {
      return $text;
    }

    // check comment type
    if ($comment_type = get_comment_meta($comment->comment_ID, "semantic_linkbacks_type", true)) {
      $post_format = get_post_format($comment->comment_post_ID);

      // replace "standard" with "Article"
      if (!$post_format || $post_format == "standard") {
        $post_format = "Article";
      } else {
        $post_formatstrings = get_post_format_strings();
        // get the "nice" name
        $post_format = $post_formatstrings[$post_format];
      }

      // generate the verb, for example "mentioned" or "liked"
      $comment_type_verbs = self::get_comment_type_verbs();
      $comment_type = $comment_type_verbs[$comment_type];

      // get URL canonical url...
      $url = get_comment_meta($comment->comment_ID, "semantic_linkbacks_canonical", true);
      // ...or fall back to source
      if (!$url) {
        $url = get_comment_meta($comment->comment_ID, "semantic_linkbacks_source", true);
      }

      // parse host
      $host = parse_url($url, PHP_URL_HOST);
      // strip leading www, if any
      $host = preg_replace("/^www\./", "", $host);

      // generate output
      $text = get_comment_author_link($comment->comment_ID) . ' ' . $comment_type . ' your ' . $post_format . ' on  <a href="'.$url.'">' . $host . '</a>';
    }

    return apply_filters("semantic_linkbacks_excerpt", $text);
  }

  /**
   * replace comment url with canonical url
   *
   * @param string $link the link url
   * @param obj $comment the comment object
   * @param array $args a list of arguments to generate the final link tag
   * @return string the webmention source or the original comment link
   */
  public static function get_comment_link($link, $comment, $args) {
    if ( $canonical = get_comment_meta($comment->comment_ID, 'semantic_linkbacks_canonical', true) ) {
      return $canonical;
    }

    return $link;
  }
}