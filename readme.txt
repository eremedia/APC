=== APC Object Cache Backend ===
Contributors: markjaquith
Donate link: http://txfx.net/wordpress-plugins/donate
Tags: APC, object cache, backend, cache, performance, speed, batcache
Requires at least: 2.9.2
Tested up to: 3.0.1
Stable tag: trunk

APC Object Cache provides a persistent memory-based backend for the WordPress object cache. APC must be available on your PHP install.

== Description ==

APC Object Cache provides a persistent memory-based backend for the WordPress object cache. APC must be available on your PHP install.

An object cache is a place for WordPress and WordPress extensions to store the results of complex operations. On subsequent loads, 
this data can be fetched from the cache, which will be must faster than dynamically generating it on every page load.

The APC Object Cache backend is also compatible with [Batcache][1], the powerful full page caching engine that runs on WordPress.com

Be sure to read the installation instructions, as this is **not** a traditional plugin, and needs to be installed in a specific location.

[1]: http://wordpress.org/extend/plugins/batcache/

== Installation ==

1. Copy `object-cache.php` to your WordPress content directory (`wp-content/` by default).
2. Done!

== Frequently Asked Questions ==

= Does this work as a backend for Batcache? =

Yes! APC supports incrementers and handles its own cleanup of expired objects, so it works just fine for Batcache.

= Does this support versions of WordPress earlier than 2.9.2? =

Probably, but I'm not going to support them, and you shouldn't still be running them!

== Changelog ==

= 2.0 =
* First version in SVN
* Updated to support increment/decrement and feature parity with the Memcached backend (except for multiget support)

= 2.0.1 =
* Fixed bugs in wp_cache_delete()

== Upgrade Notice ==

= 2.0 =
First update in four years! This should last you a while.

= 2.0.1 =
Fixed bugs regarding wp_cache_delete()