<?php
/*
 Plugin Name: Semantic-Linkbacks
 Plugin URI: https://github.com/pfefferle/wordpress-semantic-linkbacks
 Description: Semantic Linkbacks for WebMentions, Trackbacks and Pingbacks
 Author: pfefferle & acegiak
 Author URI: http://notizblog.org/
 Version: 3.1.0
 Text Domain: semantic_linkbacks
*/

if (!class_exists("SemanticLinkbacksPlugin")) :

// check if php version is >= 5.3
// version is required by the mf2 parser
function semantic_linkbacks_activation() {
  if (version_compare(phpversion(), 5.3, '<')) {
    die("The minimum PHP version required for this plugin is 5.3");
  }
}
register_activation_hook(__FILE__, 'semantic_linkbacks_activation');

// run plugin only if php version is >= 5.3
if (version_compare(phpversion(), 5.3, '>=')) {
  require_once "semantic-linkbacks-microformats-handler.php";
  add_action('init', array('SemanticLinkbacksPlugin', 'init'));
}

/**
 * semantic linkbacks class
 *
 * @author Matthias Pfefferle
 */
class SemanticLinkbacksPlugin {
  /**
   * initialize the plugin, registering WordPess hooks.
   */
  public static function init() {
    if (did_action('plugins_loaded')) {
      self::plugin_textdomain();
    } else {
      add_action('plugins_loaded', array('SemanticLinkbacksPlugin', 'plugin_textdomain'), 99);
    }

    // hook into linkback functions to add more semantics
    add_action('pingback_post', array('SemanticLinkbacksPlugin', 'linkback_fix'));
    add_action('trackback_post', array('SemanticLinkbacksPlugin', 'linkback_fix'));
    add_action('webmention_post', array('SemanticLinkbacksPlugin', 'linkback_fix'));
    add_filter('pre_get_avatar_data', array('SemanticLinkbacksPlugin', 'pre_get_avatar_data'), 11, 5);
    // To extend or to override the default behavior, just use the `comment_text` filter with a lower
    // priority (so that it's called after this one) or remove the filters completely in
    // your code: `remove_filter('comment_text', array('SemanticLinkbacksPlugin', 'comment_text_add_cite'), 11);`
    add_filter('comment_text', array('SemanticLinkbacksPlugin', 'comment_text_add_cite'), 11, 3);
    add_filter('comment_text', array('SemanticLinkbacksPlugin', 'comment_text_excerpt'), 12, 3);

    add_filter('get_comment_link', array('SemanticLinkbacksPlugin', 'get_comment_link'), 99, 3);
    add_filter('get_comment_author_url', array('SemanticLinkbacksPlugin', 'get_comment_author_url'), 99, 3);
    add_filter('get_avatar_comment_types', array('SemanticLinkbacksPlugin', 'get_avatar_comment_types'));
    add_filter('comment_class', array('SemanticLinkbacksPlugin', 'comment_class'), 10, 4);
  }

  /**
   * Load language files
   */
  public static function plugin_textdomain() {
    // Note to self, the third argument must not be hardcoded, to account for relocated folders.
    load_plugin_textdomain('semantic_linkbacks', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
    if ($comments = get_comments(array('meta_key' => 'semantic_linkbacks_source', 'meta_value' => htmlentities($source)))) {
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

    // generate target
    $target = get_permalink($post['ID']);

    // add replytocom if present
    if (isset($commentdata['comment_parent']) && !empty($commentdata['comment_parent'])) {
      $target = add_query_arg(array("replytocom" => $commentdata['comment_parent']), $target);
    }

    // get remote html
    $response = wp_remote_get(esc_url_raw(html_entity_decode($source)), array('timeout' => 100));

    // handle errors
    if (is_wp_error($response)) {
      return $comment_ID;
    }

    // get HTML code of source url
    $html = wp_remote_retrieve_body($response);

    // add source url as comment-meta
    update_comment_meta($commentdata["comment_ID"], "semantic_linkbacks_source", esc_url_raw($commentdata["comment_author_url"]), true);

    // adds a hook to enable some other semantic handlers for example schema.org
    $commentdata = apply_filters("semantic_linkbacks_commentdata", $commentdata, $target, $html);

    // check if comment-data is empty
    if (empty($commentdata)) {
      return $comment_ID;
    }

    // remove "webmention" comment-type if $type is "reply"
    if (isset($commentdata["_type"]) && in_array($commentdata["_type"], apply_filters("semantic_linkbacks_comment_types", array("reply")))) {
      global $wpdb;
      $wpdb->update($wpdb->comments, array('comment_type' => ''), array('comment_ID' => $commentdata["comment_ID"]));

      $commentdata["comment_type"] = "";
    }

    // save custom comment properties as comment-metas
    foreach ($commentdata as $key => $value) {
      if (strpos($key, "_") === 0) {
        update_comment_meta($commentdata["comment_ID"], "semantic_linkbacks$key", $value, true);
        unset($commentdata[$key]);
      }
    }

    // disable flood control
    remove_filter("check_comment_flood", "check_comment_flood_db", 10, 3);

    // update comment
    wp_update_comment($commentdata);

    // re-add flood control
    add_filter("check_comment_flood", "check_comment_flood_db", 10, 3);

    return $comment_ID;
  }

  /**
   * returns an array of comment type excerpts to their translated and pretty display versions
   *
   * @return array The array of translated post format excerpts.
   */
  public static function get_comment_type_excerpts() {
    $strings = array(
      'mention'       => __('%1$s mentioned %2$s on <a href="%3$s">%4$s</a>',   'semantic_linkbacks'), // Special case. any value that evals to false will be considered standard

      'reply'         => __('%1$s replied to %2$s on <a href="%3$s">%4$s</a>',  'semantic_linkbacks'),
      'repost'        => __('%1$s reposted %2$s on <a href="%3$s">%4$s</a>',    'semantic_linkbacks'),
      'like'          => __('%1$s liked %2$s on <a href="%3$s">%4$s</a>',       'semantic_linkbacks'),
      'favorite'      => __('%1$s favorited %2$s on <a href="%3$s">%4$s</a>',   'semantic_linkbacks'),
      'tagged'        => __('%1$s tagged %2$s on <a href="%3$s">%4$s</a>',      'semantic_linkbacks'),
      'rsvp:yes'      => __('%1$s is <strong>attending</strong>',               'semantic_linkbacks'),
      'rsvp:no'       => __('%1$s is <strong>not attending</strong>',           'semantic_linkbacks'),
      'rsvp:maybe'    => __('Maybe %1$s will be <strong>attending</strong>',    'semantic_linkbacks'),
      'rsvp:invited'  => __('%1$s is <strong>invited</strong>',                 'semantic_linkbacks'),
      'rsvp:tracking' => __('%1$s <strong>tracks</strong> this event',          'semantic_linkbacks')
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
      'mention'       => __('Mention',   'semantic_linkbacks'), // Special case. any value that evals to false will be considered standard

      'reply'         => __('Reply',     'semantic_linkbacks'),
      'repost'        => __('Repost',    'semantic_linkbacks'),
      'like'          => __('Like',      'semantic_linkbacks'),
      'favorite'      => __('Favorite',  'semantic_linkbacks'),
      'tag'           => __('Tag',       'semantic_linkbacks'),
      'rsvp:yes'      => __('RSVP',      'semantic_linkbacks'),
      'rsvp:no'       => __('RSVP',      'semantic_linkbacks'),
      'rsvp:invited'  => __('RSVP',      'semantic_linkbacks'),
      'rsvp:maybe'    => __('RSVP',      'semantic_linkbacks'),
      'rsvp:tracking' => __('RSVP',      'semantic_linkbacks')
    );

    return $strings;
  }

  /**
  * returns an array of post formats (with articlex)
  *
  * @return array The array of translated comment type names.
  */
  public static function get_post_format_strings() {
    $strings = array(
      'standard' => __('this Article',  'semantic_linkbacks'), // Special case. any value that evals to false will be considered standard

      'aside'    => __('this Aside',    'semantic_linkbacks'),
      'chat'     => __('this Chat',     'semantic_linkbacks'),
      'gallery'  => __('this Gallery',  'semantic_linkbacks'),
      'link'     => __('this Link',     'semantic_linkbacks'),
      'image'    => __('this Image',    'semantic_linkbacks'),
      'quote'    => __('this Quote',    'semantic_linkbacks'),
      'status'   => __('this Status',   'semantic_linkbacks'),
      'video'    => __('this Video',    'semantic_linkbacks'),
      'audio'    => __('this Audio',    'semantic_linkbacks'),
    );

    return $strings;
  }

  /**
   * add cite to "reply"s
   *
   * thanks to @snarfed for the idea
   *
   * @param string $text the comment text
   * @param WP_Comment $comment the comment object
   * @param array $args a list of arguments
   * @return string the filtered comment text
   */
  public static function comment_text_add_cite($text, $comment = null, $args = array()) {
    $semantic_linkbacks_type = get_comment_meta($comment->comment_ID, "semantic_linkbacks_type", true);

    // only change text for "real" comments (replys)
    if (!$comment ||
        !$semantic_linkbacks_type ||
        $comment->comment_type != "" ||
        $semantic_linkbacks_type != "reply") {
      return $text;
    }

    // get URL canonical url...
    $semantic_linkbacks_canonical = get_comment_meta($comment->comment_ID, "semantic_linkbacks_canonical", true);
    // ...or fall back to source
    if (!$semantic_linkbacks_canonical) {
      $semantic_linkbacks_canonical = get_comment_meta($comment->comment_ID, "semantic_linkbacks_source", true);
    }

    $host = parse_url($semantic_linkbacks_canonical, PHP_URL_HOST);

    // strip leading www, if any
    $host = preg_replace("/^www\./", "", $host);
    // note that WordPress's sanitization strips the class="u-url". sigh. :/ also,
    // <cite> is one of the few elements that make it through the sanitization and
    // is still uncommon enough that we can use it for styling.
    $text .= '<p><small>&mdash;&nbsp;<cite><a class="u-url" href="' . $semantic_linkbacks_canonical . '">via ' . $host . '</a></cite></small></p>';

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
    $semantic_linkbacks_type = get_comment_meta($comment->comment_ID, "semantic_linkbacks_type", true);

    // only change text for pinbacks/trackbacks/webmentions
    if (!$comment ||
        $comment->comment_type == "" ||
        $semantic_linkbacks_type == "reply") {
      return $text;
    }

    // check semantic linkback type
    if (!in_array($semantic_linkbacks_type, array_keys(self::get_comment_type_strings()))) {
      $semantic_linkbacks_type = "mention";
    }

    $post_format = get_post_format($comment->comment_post_ID);

    // replace "standard" with "Article"
    if (!$post_format || !in_array($post_format, array_keys(self::get_post_format_strings()))) {
      $post_format = "standard";
    }

    $post_formatstrings = self::get_post_format_strings();

    // get all the excerpts
    $comment_type_excerpts = self::get_comment_type_excerpts();

    // get URL canonical url...
    $semantic_linkbacks_canonical = get_comment_meta($comment->comment_ID, "semantic_linkbacks_canonical", true);
    // ...or fall back to source
    if (!$semantic_linkbacks_canonical) {
      $semantic_linkbacks_canonical = get_comment_meta($comment->comment_ID, "semantic_linkbacks_source", true);
    }

    // parse host
    $host = parse_url($semantic_linkbacks_canonical, PHP_URL_HOST);
    // strip leading www, if any
    $host = preg_replace("/^www\./", "", $host);

    // generate output
    $text = sprintf($comment_type_excerpts[$semantic_linkbacks_type], get_comment_author_link($comment->comment_ID), $post_formatstrings[$post_format], $semantic_linkbacks_canonical, $host);

    return apply_filters("semantic_linkbacks_excerpt", $text);
  }

  /**
   * replaces the default avatar with the WebMention uf2 photo
   *
   * @param array $args Arguments passed to get_avatar_data(), after processing.
   * @param int|string|object $id_or_email A user ID, email address, or comment object
   * @return array $args
   */
  public static function pre_get_avatar_data($args, $id_or_email) {
    if(!isset($args['class'])) {
      $args['class'] = array('u-photo');
    } else {
      $args['class'][] = 'u-photo';
    }

    if (!is_object($id_or_email) ||
        !isset($id_or_email->comment_type) ||
        !get_comment_meta($id_or_email->comment_ID, 'semantic_linkbacks_avatar', true)) {
      return $args;
    }

    // check if comment has an avatar
    $avatar = get_comment_meta($id_or_email->comment_ID, 'semantic_linkbacks_avatar', true);

    if ($avatar) {
      $args['url'] = $avatar;
      $args['class'][] = 'avatar-semantic-linkbacks';
    }

    return $args;
  }

  /**
   * replace comment url with canonical url
   *
   * @param string $link the link url
   * @param obj $comment the comment object
   * @param array $args a list of arguments to generate the final link tag
   * @return string the linkback source or the original comment link
   */
  public static function get_comment_link($link, $comment, $args) {
    $semantic_linkbacks_canonical = get_comment_meta($comment->comment_ID, 'semantic_linkbacks_canonical', true);

    if (is_singular() && $semantic_linkbacks_canonical) {
      return $semantic_linkbacks_canonical;
    }

    return $link;
  }

  /**
   * replace comment url with author url
   *
   * @param string $link the author url
   * @return string the replaced/parsed author url or the original comment link
   */
  public static function get_comment_author_url($link) {
    global $comment;

    if ($author_url = get_comment_meta($comment->comment_ID, 'semantic_linkbacks_author_url', true)) {
      return $author_url;
    }

    return $link;
  }

  /**
   * add comment classes from `semantic_linkbacks_type`s
   *
   * @return array the extended comment classes as array
   */
  public static function comment_class($classes, $class, $comment_id, $post_id) {
    // get comment
    $comment = get_comment($comment_id);

    // "comment type to class" mapper
    $class_mapping = array(
      'mention'       => 'u-mention',

      'reply'         => 'p-reply',
      'repost'        => 'u-repost',
      'like'          => 'u-like',
      'favorite'      => 'u-favorite',
      'tag'           => 'u-tag',
      'rsvp:yes'      => 'u-rsvp',
      'rsvp:no'       => 'u-rsvp',
      'rsvp:maybe'    => 'u-rsvp',
      'rsvp:invited'  => 'u-rsvp',
      'rsvp:tracking' => 'u-rsvp'
    );

    $semantic_linkbacks_type = get_comment_meta($comment->comment_ID, 'semantic_linkbacks_type', true);
    // check the comment type
    if ($semantic_linkbacks_type && isset($class_mapping[$semantic_linkbacks_type])) {
      $classes[] = $class_mapping[$semantic_linkbacks_type];
      $classes = array_unique($classes);
    }

    return $classes;
  }

  /**
   * show avatars also on trackbacks and pingbacks
   *
   * @param array $types list of avatar enabled comment types
   *
   * @return array show avatars also on trackbacks and pingbacks
   */
  public static function get_avatar_comment_types($types) {
    $types[] = 'pingback';
    $types[] = 'trackback';
    $types[] = 'webmention';

    return $types;
  }
}

/**
 * get a Count of Linkbacks by Type
 *
 * @param string $type the comment type
 * @param int $post_id the id of the post
 *
 * @return the number of matching linkbacks
 */
function get_linkbacks_number($type = null, $post_id = 0) {
  $post = get_post($post_id);

  $args = array(
    'post_id' => $post->ID,
    'count'   => true,
    'status'  => 'approve'
  );

  if ($type) { // use type if set
    $args['meta_query'] = array(array('key' => 'semantic_linkbacks_type', 'value' => $type));
  } else { // check only if type exists
    $args['meta_query'] = array(array('key' => 'semantic_linkbacks_type', 'compare' => 'EXISTS'));
  }

  $comments = get_comments($args);
  if ($comments) { return $comments; }
  else { return 0; }
}

/**
 * returns comments of linkback type
 *
 * @param string $type the comment type
 * @param int $post_id the id of the post
 *
 * @return the matching linkback "comments"
 */
function get_linkbacks($type = null, $post_id = 0) {
  $post = get_post($post_id);
  $args = array(
    'post_id' => $post->ID,
    'status'  => 'approve'
  );

  if ($type) { // use type if set
    $args['meta_query'] = array(array('key' => 'semantic_linkbacks_type', 'value' => $type));
  } else { // check only if type exists
    $args['meta_query'] = array(array('key' => 'semantic_linkbacks_type', 'compare' => 'EXISTS'));
  }

  return get_comments($args);
}

endif;
