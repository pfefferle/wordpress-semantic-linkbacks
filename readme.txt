=== Semantic-Linkbacks ===
Contributors: pfefferle
Donate link: http://14101978.de
Tags: webmention, pingback, trackback, linkback, microformats
Requires at least: 2.7
Tested up to: 3.9.1
Stable tag: 3.0.4

Semantic Trackbacks/Pingbacks/WebMentions for WordPress!

== Description ==

This WordPress plugin adds a semantic layer to classic linkback protocols like Trackback, Pingback or [WebMention](https://github.com/pfefferle/wordpress-webmention) (WordPress doesn't support WebMentions by default, you have to install a plugin). It uses [Microformats 2](http://microformats.org/wiki/microformats2) to generate richer WordPress comments and it is highly extensible to also add support for RDFa or Microdata/Schema.org

== Frequently Asked Questions ==

= What are WebMentions? =

[WebMention](http://indiewebcamp.com/webmention) is a modern reimplementation of Pingback using only HTTP and x-www-urlencoded content rather than XMLRPC requests. WebMention supersedes Pingback.

= What about the semantic "comment" types? =

The IndieWeb community defines several types of feedback:

* Replies: <http://indiewebcamp.com/replies>
* Reposts: <http://indiewebcamp.com/repost>
* Likes: <http://indiewebcamp.com/likes>
* Favorites: <http://indiewebcamp.com/favorite>
* RSVPs: <http://indiewebcamp.com/rsvp>
* Tagging: <http://indiewebcamp.com/tag>
* Classic "Mentions": <http://indiewebcamp.com/mentions>

= How to add RDFa or Schema.org support =

If you want to write your own parser you have to hook up the `semantic_linkbacks_commentdata` filter and update the array-fields.

The comment-array is a classic [WordPress comment](http://codex.wordpress.org/get_comment#Return) with some extra-fields. Here is an example:


	Array
	(
		[comment_ID] => 433
		[comment_post_ID] => 1060
		[comment_author] => Matthias Pfefferle
		[comment_author_email] =>
		[comment_author_url] => https://notizblog.org/author/matthias-pfefferle/
		[comment_author_IP] => 127.0.0.1
		[comment_date] => 2014-01-16 11:11:26
		[comment_date_gmt] => 2014-01-23 12:12:22
		[comment_content] => Bridgy ist ein WebMention Proxy für Twitter, Facebook und Google+. Es sammelt comments, shares, likes und re-tweets und leitet sie an die entsprechenden Links weiter. Bridgy sends webmentions for comments, likes, and reshares on Facebook, Twitter, Google+, and Instagram. Bridgy notices when you post links, watches for activity on those posts, and sends them back to your site as webmentions. It also serves them as microformats2 for webmention targets to read. Großartige Idee! Wer sein eigenes Bridgy betreiben will… der Code ist Open Source!
		[comment_karma] => 0
		[comment_approved] => 0
		[comment_agent] => WebMention-Testsuite (https://github.com/voxpelli/node-webmention-testpinger)
		[comment_type] => pingback
		[comment_parent] => 0
		[user_id] => 0
		[_canonical] => https://notizblog.org/2014/01/16/bridgy-webmentions-fuer-twitter-und-facebook/
		[_type] => reply
		[_avatar] => https://secure.gravatar.com/avatar/b36983a5651df2c413e264ad4d5cc1a1?s=40&d=https%3A%2F%2Fsecure.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D40&r=G
	)

All filds beginning with `_` like:

* `$commentdata['_canonical']` for the canonical source url
* `$commentdata['_type']` for the comment type. The plugin currently supports the following types: `mention`(default), `reply`, `repost`, `like` and `favorite`
* `$commentdata['_avatar']` for the author avatar

...will be saved as comment-metas.

= Who made the logos? =

The WebMention and Pingback logos are made by [Aaron Parecki](http://aaronparecki.com) and the Microformats logo is made by [Dan Cederholm](http://simplebits.com/work/microformats/)

== Changelog ==

Project maintined on github at [pfefferle/wordpress-semantic-linkbacks](https://github.com/pfefferle/wordpress-semantic-linkbacks).

= 3.0.4 = 

* add filter to moderate/approve linkbacks
* improved README

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
* ([@acegiak](https://github.com/acegiak)) for the initial plugin

== Installation ==

1. Upload the `webmention`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the *Plugins* menu in WordPress
3. ...and that's it :)

== Upgrade Notice ==

= 2.0.0 =

This plugin doesn't support the microformts stuff mentioned in the IndieWebCamp Wiki.
To enable semantik linkbacks you have to use <https://github.com/pfefferle/wordpress-semantic-linkbacks>
