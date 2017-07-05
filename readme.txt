=== Semantic-Linkbacks ===
Contributors: pfefferle, dshanske
Donate link: http://14101978.de
Tags: webmention, pingback, trackback, linkback, microformats, comments, indieweb
Requires at least: 4.7.1
Tested up to: 4.8
Stable tag: 3.4.0
License: MIT
License URI: http://opensource.org/licenses/MIT

Richer Comments and Linkbacks for WordPress!

== Description ==

Generates richer WordPress comments from linkbacks such as [Webmention](https://wordpress.org/plugins/webmention) or classic linkback protocols like Trackback or Pingback.

The limited display for trackbacks and linkbacks is replaced by a clean full sentence, such as "Bob mentioned this article on bob.com." If Bob's site uses markup that the plugin can interpret, it may add his profile picture or other parts of his page to display as a full comment.

Semantic Linkbacks uses [Microformats 2](http://microformats.org/wiki/microformats2) to get information about the linked post and it is highly extensible to also add support for other forms of markup.

== Frequently Asked Questions ==

= Do I need to mark up my site? =

Most modern WordPress themes support the older Microformats standard, which means the plugin should be able to get basic information from
to enhance linkbacks. The plugin is most useful with webmention support(separate plugin) and sites/themes that support Microformats 2.

= Why Webmentions? =

[Webmention](http://indiewebcamp.com/webmention) is a modern reimplementation of Pingback and is now a W3C Recommendation.

= What about the semantic "comment" types? =

The IndieWeb community defines several types of feedback:

* Replies: <http://indieweb.org/replies>
* Reposts: <http://indieweb.org/repost>
* Likes: <http://indieweb.org/likes>
* Favorites: <http://indieweb.org/favorite>
* RSVPs: <http://indieweb.org/rsvp>
* Tagging: <http://indieweb.org/tag>
* Classic "Mentions": <http://indieweb.org/mentions>

= How do I extend this plugin? =

See [Extensions](https://indieweb.org/Semantic_Linkbacks#Extensions)

= Who made the logos? =

The Webmention and Pingback logos are made by [Aaron Parecki](http://aaronparecki.com) and the Microformats logo is made by [Dan Cederholm](http://simplebits.com/work/microformats/)

== Changelog ==

Project actively developed on Github at [pfefferle/wordpress-semantic-linkbacks](https://github.com/pfefferle/wordpress-semantic-linkbacks). Please file support issues there.

= 3.4.0 =

* Fix Tests and Error in Authorship
* Update Parser
* Switch to looser restrictions if WP_DEBUG is enabled and stricter ones otherwise
* Enhance Author Properties to allow for retrieving remote h-card
* Store mf2 properties
* Store location in WordPress Geodata
* Use rel-syndication if not u-syndication
* Support new webmention source meta key

= 3.3.1 =

* fixed https://github.com/pfefferle/wordpress-semantic-linkbacks/issues/68

= 3.3.0 =

* Due to changes in WordPress 4.4 through 4.7 and version 3.0.0 of the Webmentions plugin this plugin can act on the retrieved remote source
rather than rerequesting this information.
* Major enhancement work is done in preprocessing now rather than post-processing
* Refactoring
* Render full mention content if short enough. Introduce MAX_INLINE_MENTION_LENGTH which defaults to 300 characters to implement same.
* Fix text domain

= 3.2.1 =

* updated hooks/filters

= 3.2.0 =

* changed hook from `<linkback>_post` to `comment_post` (thanks to @dshanske)
* used the WordPress Coding Standard
* small code improvements

= 3.1.0 =
* I18n support
* German translation
* some small changes and bugfixes

= 3.0.5 =

* quick fix to prevent crash if Mf2 lib is used by a second plugin

= 3.0.4 =

* added counter functions for comments by type (props to David Shanske)
* some bugfixes

= 3.0.3 =

* some small tweaks
* added custom comment classes based on the linkback-type (props to David Shanske for the idea)

= 3.0.2 =

* added support for threaded comments

= 3.0.1 =

* fixed bug in comments section

= 3.0.0 =

* nicer integration with trackbacks, linkbacks and webmentions
* cleanup

= 2.0.1 =

* "via" links for indieweb "reply"s (thanks to @snarfed for the idea)
* simplified output for all other indieweb "comment" types
* better parser (thanks to voxpelly for his test-pinger)
* now ready to use in a bundle

= 2.0.0 =

* initial release

== Thanks to ==

* Pelle Wessman ([@voxpelli](https://github.com/voxpelli)) for his awesome [WebMention test-pinger](https://github.com/voxpelli/node-webmention-testpinger)
* Ryan Barrett ([@snarfed](https://github.com/snarfed)) for his feedback
* Barnaby Walters ([@barnabywalters](https://github.com/barnabywalters)) for his awesome [mf2 parser](https://github.com/indieweb/php-mf2)
* David Shanske ([@dshanske](https://github.com/dshanske)) for his feedback and a lot of pull requests
* ([@acegiak](https://github.com/acegiak)) for the initial plugin

== Installation ==

1. Upload the `webmention`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the *Plugins* menu in WordPress
3. ...and that's it :)
