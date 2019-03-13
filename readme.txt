=== Semantic-Linkbacks ===
Contributors: pfefferle, dshanske
Donate link: https://notiz.blog/donate/
Tags: webmention, pingback, trackback, linkback, microformats, comments, indieweb
Requires at least: 4.8.2
Requires PHP: 5.4
Tested up to: 5.1.1
Stable tag: 3.9.2
License: MIT
License URI: http://opensource.org/licenses/MIT

Richer Comments and Linkbacks for WordPress!

== Description ==

Generates richer WordPress comments from linkbacks such as [Webmention](https://wordpress.org/plugins/webmention) or classic linkback protocols like Trackback or Pingback.

The limited display for trackbacks and linkbacks is replaced by a clean full sentence, such as "Bob mentioned this article on bob.com." If Bob's site uses markup that the plugin can interpret, it may add his profile picture or other parts of his page to display as a full comment. It will optionally show collections of profile pictures, known as Facepiles, instead of the full setences.

Semantic Linkbacks uses [Microformats 2](http://microformats.org/wiki/microformats2) to get information about the linked post and it is highly extensible to also add support for other forms of markup.

== Privacy and Data Collection ==

This plugin collects data from sites that send webmentions for the purpose of displaying richer comments on a site. This data is under the control of the site owner. It is the personal responsibility of that individual or individuals to remove any information at the request of the original content creator. Over time, we will add additional tools to assist in doing so.

== Frequently Asked Questions ==

= Do I need to mark up my site? =

Most modern WordPress themes support the older Microformats standard, which means the plugin should be able to get basic information from to enhance linkbacks. The plugin is most useful with webmention support(separate plugin) and sites/themes that support Microformats 2.

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
* Listen: <http://indieweb.org/listen>
* Watch: <http://indieweb.org/watch>
* Read: <http://indieweb.org/read>
* Follow: <http://indieweb.org/follow>
* Classic "Mentions": <http://indieweb.org/mentions>

= How do I extend this plugin? =

See [Extensions](https://indieweb.org/Semantic_Linkbacks#Extensions)

= How do I add this into my plugin? =

The plugin will automatically enhance webmentions, trackbacks, and pingbacks with an avatar and additional context. It will also automatically add a facepile instead of individual comments, but this feature can either be turned off by an aware theme or under Discussion in your Settings.

= Why do some [emoji reactions](https://indieweb.org/reacji) not show up? =

Some emoji characters in webmentions you might receive, e.g. Facebook reactions from [Bridgy](https://brid.gy/), take more than two bytes to encode. (In technical terms, these Unicode characters are [above the Basic Multilingual Plane](https://en.wikipedia.org/wiki/Plane_(Unicode)).) To handle them, you need MySQL 5.5.3 or higher, and your database and tables need to use the [`utf8mb4` charset](https://dev.mysql.com/doc/refman/5.7/en/charset-mysql.html). [Usually WordPress does this automatically](https://make.wordpress.org/core/2015/04/02/the-utf8mb4-upgrade/), but not always.

First, [follow these instructions](https://wordpress.stackexchange.com/questions/195046/relaunch-4-2-utf8mb4-databse-upgrade/244992#244992) to switch your MySQL database to `utf8mb4`. Then, make sure `DB_CHARSET` and `DB_COLLATE` in your `wp-config.php` are either unset, set to the blank string, or set to these values:

    define('DB_CHARSET', 'utf8mb4');
    define('DB_COLLATE', 'utf8mb4_general_ci');

= Who made the logos? =

The Webmention and Pingback logos are made by [Aaron Parecki](http://aaronparecki.com) and the Microformats logo is made by [Dan Cederholm](http://simplebits.com/work/microformats/).

= Why are you providing avatars? =

The plugin attempts to store the URL to an actual profile image on the source site. The default avatar set by WordPress is only used if there is no such image found.

Even the WordPress default avatars are served by querying Gravatar.com which serves the file. Gravatar works by you providing an email address which it uses to match the image.
The majority of linkbacks enhanced by this plugin do not have email addresses therefore we know that gravatar will not have anything on file.

If there is no email address it will serve the local avatar. If there is an email, it will go out to gravatar.com and see if they
have a gravatar on file. If there is it will store the gravatar URL, otherwise it will store the URL for the local avatar if set.

The plugin uses a locally cached version of the mystery icon normally provided by WordPress and Gravatar.

== Changelog ==

Project actively developed on Github at [pfefferle/wordpress-semantic-linkbacks](https://github.com/pfefferle/wordpress-semantic-linkbacks). Please file support issues there.

= 3.9.2 =
* Only load MF2 parser inside parsing function to ensure bundled version is loaded
* Update development dependencies

= 3.9.1 =

* Quick fix facepile problem id comments are closed

= 3.9.0 =

* Support rel-alternate (mf2-json)
* New php-mf2 version

= 3.8.1 =

* Add follow post as type
* Add warning to settings page if php-mbstring not installed
* Return false in Emoji function if php-mbstring not installed
* Add approve link to emails sent

= 3.8.0 =

* Add locally hosted copy of the mystery man icon and serve it if there is no gravatar
* Redo settings and settings page
* Settings page to merge with Webmentions page if webmentions loaded

= 3.7.7 =

* Add read type
* Capture read-status if available
* Change string as previous tense was off

= 3.7.6 =

* Update Parser to version 0.4.3
* Introduce watch and listen properties

= 3.7.5 =

* fixed Reacji UI ((#154)[https://github.com/pfefferle/wordpress-semantic-linkbacks/issues/154])

= 3.7.4 =

* Replace `rsvp-invite` property which is not in use with `invite` property and add unit tests
* Enhance post type returns to include post, page, and sitename
* Add basic person tagging support

= 3.7.3 =

* Replace tracking with interested property as noted on https://indieweb.org/rsvp
* Remove `h-as` properties
* Remove hard-coded microformats2 properties from facepile and move them to being generated from comment_class
* Remove unused properties
* Introduce type argument in list_linkbacks to generate unique ideas for each list of linkbacks without having to specify them using style and li-class
* Whitelist property swarm-coins, used by [OwnYourSwarm](https://ownyourswarm.p3k.io/docs#coins) and display it if using built-in comment handler.

= 3.7.2 =

* Bugfix: "Normal comments" hidden in comment-section (https://github.com/pfefferle/wordpress-semantic-linkbacks/issues/140)

= 3.7.1 =

* Fixed reacjis and facepiles

= 3.7.0 =

* Add settings to enable each type independently in the Facepile
* Optionally render mentions as normal comments again
* Support Reacji...aka single-emoji reactions
* Bump minimum PHP to 5.4 due emoji detector library dependency issues
* Overlay emoji on individual avatars in reactions facepile
* Offer mf2 compatible template for comments
* Fix semantic_linkbacks_cite filter as was previously filtering the entire comment text
* Switch semantic_links_cite filter to filtering the format for the citation instead of the prepared citation
* Count correct text length for unicode characters
* Facepile Template improvements
* Allow new comment template to be overridden by filter or theme declaring microformats2 support
* Code standards compliance changes
* Improved testing for PHP versions 5.4 and up to ensure compatibility
* Remove direct calls to comment meta in favor of helper functions to ensure future proofing

= 3.6.0 =

* Only show the first 8 avatars in a facepile by default. If there are more, include a clickable ellipsis to show the rest. Customizable via the `FACEPILE_FOLD_LIMIT` constant.
* Link facepile avatars to user profile/home page, not response post
* Always show avatar images with correct aspect ratio

= 3.5.1 =

* Bugfix release

= 3.5.0 =

* Add Facepile code
* Add setting to disable automatic facepile include
* Add filter to allow themes to disable the setting and the feature if they facepile themselves
* Add PHP requirement to readme file

= 3.4.1 =

* Abstract out linkback retrieval functions to allow for easier changes in future
* Fix retrieval issue
* Remove merge and compatibility function creating double slashing due update in 4.7.1
* Replace blacklist for properties with whitelist for select properties
* Update avatar function to not override if user_id is set on assumption local overrides remote

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
* Ryan Barrett ([@snarfed](https://github.com/snarfed)) for his feedback and pull requests
* Barnaby Walters ([@barnabywalters](https://github.com/barnabywalters)) for his awesome [mf2 parser](https://github.com/indieweb/php-mf2)
* David Shanske ([@dshanske](https://github.com/dshanske)) for his feedback and a lot of pull requests
* ([@acegiak](https://github.com/acegiak)) for the initial plugin

== Installation ==

1. Upload the `webmention`-folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the *Plugins* menu in WordPress
3. ...and that's it :)
