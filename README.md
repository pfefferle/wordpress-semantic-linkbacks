# Semantic-Linkbacks

Semantic Trackbacks/Pingbacks/WebMentions for WordPress

## Description

This WordPress plugin adds a semantic layer to classic linkback protocols like Trackback, Pingback or [WebMention](https://github.com/pfefferle/wordpress-webmention) (WordPress doesn't support WebMentions by default, you have to install a plugin). It uses [Microformats 2](http://microformats.org/wiki/microformats2) to generate richer WordPress comments and it is highly extensible to also add support for RDFa or Microdata/Schema.org

## FAQ

### What are WebMentions?

[WebMention](http://indiewebcamp.com/webmention) is a modern reimplementation of Pingback using only HTTP and x-www-urlencoded content rather than XMLRPC requests. WebMention supersedes Pingback.

### What about the semantic "comment" types?

The IndieWeb community defines several types of feedback:

* Replies: <http://indiewebcamp.com/replies>
* Reposts: <http://indiewebcamp.com/repost>
* Likes: <http://indiewebcamp.com/likes>
* Favorites: <http://indiewebcamp.com/favorite>
* Classic "Mentions": <http://indiewebcamp.com/mentions>

## Changelog

Project maintined on github at [pfefferle/wordpress-semantic-linkbacks](https://github.com/pfefferle/wordpress-semantic-linkbacks).

### 2.0.1

* "via" links for indieweb "reply"s (thanks to @snarfed for the idea)
* simplified output for all other indieweb "comment" types
* better parser (thanks to voxpelly for his test-pinger)

### 2.0.0

* initial release

## Thanks to

* Pelle Wessman ([@voxpelli](https://github.com/voxpelli)) for his awesome [WebMention test-pinger](https://github.com/voxpelli/node-webmention-testpinger)
* Ryan Barrett ([@snarfed](https://github.com/snarfed)) for his feedback
* Barnaby Walters ([@barnabywalters](https://github.com/barnabywalters)) for his awesome [mf2 parser](https://github.com/indieweb/php-mf2)
* ([@acegiak](https://github.com/acegiak)) for the initial plugin