=== Semantic-Linkbacks ===
Contributors: pfefferle
Donate link: http://14101978.de
Tags: webmention, pingback, trackback, linkback, microformats, comments, indieweb
Requires at least: 2.7
Tested up to: 4.3
Stable tag: 3.2.0

Richer Comments and Linkbacks for WordPress!

== Description ==

Generates richer WordPress comments from linkbacks such as [WebMention](https://wordpress.org/plugins/webmention) or classic linkback protocols like Trackback or Pingback.

The limited display for trackbacks and linkbacks is replaced by a clean full sentence, such as "Bob mentioned this article on bob.com." If Bob's site uses markup that the plugin can interpret, it may add his profile picture or other parts of his page to display as a full comment.

Semantic Linkbacks uses [Microformats 2](http://microformats.org/wiki/microformats2) to get information about the linked post and it is highly extensible to also add support for other forms of markup.

== Frequently Asked Questions ==

= Do I need to mark up my site? =

Most modern WordPress themes support the older Microformats standard, which means the plugin should be able to get basic information from
to enhance linkbacks. The plugin is most useful with webmention support(separate plugin) and sites that support Microformats 2.

= Why WebMentions? =

[WebMention](http://indiewebcamp.com/webmention) is a modern reimplementation of Pingback using only HTTP and x-www-urlencoded content rather than XMLRPC requests. WebMention supersedes Pingback and is simpler to implement.

= What about the semantic "comment" types? =

The IndieWeb community defines several types of feedback:

* Replies: <http://indiewebcamp.com/replies>
* Reposts: <http://indiewebcamp.com/repost>
* Likes: <http://indiewebcamp.com/likes>
* Favorites: <http://indiewebcamp.com/favorite>
* RSVPs: <http://indiewebcamp.com/rsvp>
* Tagging: <http://indiewebcamp.com/tag>
* Classic "Mentions": <http://indiewebcamp.com/mentions>

= How do I extend this plugin? =

See [Extensions](https://indiewebcamp.com/Semantic_Linkbacks#Extensions)

= Who made the logos? =

The WebMention and Pingback logos are made by [Aaron Parecki](http://aaronparecki.com) and the Microformats logo is made by [Dan Cederholm](http://simplebits.com/work/microformats/)

== Changelog ==

Project actively developed on Github at [pfefferle/wordpress-semantic-linkbacks](https://github.com/pfefferle/wordpress-semantic-linkbacks).

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
