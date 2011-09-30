<?php
/*
Plugin Name: APC Object Cache
Description: APC backend for the WP Object Cache.
Version: 2.0.2
URI: http://txfx.net/wordpress-plugins/apc/
Author: Mark Jaquith
Author URI: http://coveredwebservices.com/

Install this file to wp-content/object-cache.php

Based on Ryan Boren's Memcached object cache backend
http://wordpress.org/extend/plugins/memcached/

*/

if ( !function_exists( 'apc_fetch' ) ) {
	wp_die( 'You do not have APC installed, so you cannot use the APC object cache backend. Please remove the <code>object-cache.php</code> file from your content directory.' );
} elseif ( version_compare( '5.2', phpversion(), '>' ) ) {
	wp_die( 'The APC object cache backend requires PHP 5.2 or higher. You are running ' . phpversion() . '. Please remove the <code>object-cache.php</code> file from your content directory.' );
}

// Users with setups where multiple installs share a common wp-config.php can use this
// to guarantee uniqueness for the keys generated by this object cache
if ( !defined( 'WP_APC_KEY_SALT' ) )
	define( 'WP_APC_KEY_SALT', 'wp' );

function &apc_get_cache() {
	global $wp_object_cache;
	return $wp_object_cache;
}

function wp_cache_add( $key, $data, $flag = '', $expire = 0 ) {
	return apc_get_cache()->add( $key, $data, $flag, $expire );
}

function wp_cache_incr( $key, $n = 1, $flag = '' ) {
	return apc_get_cache()->incr2( $key, $n, $flag );
}

function wp_cache_decr( $key, $n = 1, $flag = '' ) {
	return apc_get_cache()->decr( $key, $n, $flag );
}

function wp_cache_close() {
	return true;
}

function wp_cache_delete( $id, $flag = '' ) {
	return apc_get_cache()->delete( $id, $flag );
}

function wp_cache_flush() {
	return apc_get_cache()->flush();
}

function wp_cache_get( $id, $flag = '' ) {
	return apc_get_cache()->get( $id, $flag );
}

function wp_cache_init() {
	global $wp_object_cache;
	$wp_object_cache = new APC_Object_Cache();
}

function wp_cache_replace( $key, $data, $flag = '', $expire = 0 ) {
	return apc_get_cache()->replace( $key, $data, $flag, $expire );
}

function wp_cache_set( $key, $data, $flag = '', $expire = 0 ) {
	if ( defined('WP_INSTALLING') == false )
		return apc_get_cache()->set( $key, $data, $flag, $expire );
	else
		return apc_get_cache()->delete( $key, $flag );
}

function wp_cache_add_global_groups( $groups ) {
	apc_get_cache()->add_global_groups( $groups );
}

function wp_cache_add_non_persistent_groups( $groups ) {
	apc_get_cache()->add_non_persistent_groups( $groups );
}

class WP_Object_Cache {
	var $global_groups = array( 'users', 'userlogins', 'usermeta', 'site-options', 'site-lookup', 'blog-lookup', 'blog-details', 'rss' );
	var $no_mc_groups = array( 'comment', 'counts' );
	var $autoload_groups = array( 'options' );
	var $cache = array();
	var $stats = array();
	var $group_ops = array();
	var $cache_enabled = true;
	var $default_expiration = 0;
	var $debug = false;
	var $abspath = '';

	function add( $id, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $id, $group );

		if ( in_array( $group, $this->no_mc_groups ) ) {
			$this->cache[$key] = $data;
			return true;
		}

		$expire = ( $expire == 0 ) ? $this->default_expiration : $expire;
		if ( is_array( $data ) )
			$store_data = new ArrayObject( $data );
		else
			$store_data =& $data;
		$result = apc_add( $key, $store_data, $expire );
		@ ++$this->stats['add'];
		$this->group_ops[$group][] = "add $id";

		$this->cache[$key] = $data;
		return $result;
	}

	function add_global_groups( $groups ) {
		if ( !is_array( $groups ) )
			$groups = (array) $groups;

		$this->global_groups = array_merge( $this->global_groups, $groups );
		$this->global_groups = array_unique( $this->global_groups );
	}

	function add_non_persistent_groups( $groups ) {
		if ( !is_array( $groups ) )
			$groups = (array) $groups;

		$this->no_mc_groups = array_merge( $this->no_mc_groups, $groups );
		$this->no_mc_groups = array_unique( $this->no_mc_groups );
	}

	// This is named incr2 because Batcache looks for incr
	// We will define that in a class extension if it is available (APC 3.1.1 or higher)
	function incr2( $id, $n, $group ) {
		$key = $this->key( $id, $group );
		if ( function_exists( 'apc_inc' ) )
			return apc_inc( $key, $n );
		else
			return false;
	}

	function decr( $id, $n, $group ) {
		$key = $this->key( $id, $group );
		if ( function_exists( 'apc_dec' ) )
			return apc_dec( $id, $n );
		else
			return false;
	}

	function close() {
		return true;
	}

	function delete( $id, $group = 'default' ) {
		$key = $this->key( $id, $group );

		if ( in_array( $group, $this->no_mc_groups ) ) {
			unset( $this->cache[$key] );
			return true;
		}

		$result = apc_delete( $key );

		@ ++$this->stats['delete'];
		$this->group_ops[$group][] = "delete $id";

		unset( $this->cache[$key] );

		return $result; 
	}

	function flush() {
		return apc_clear_cache( 'user' );
	}

	function get( $id, $group = 'default' ) {
		$key = $this->key( $id, $group );

		if ( isset( $this->cache[$key] ) )
			$value = $this->cache[$key];
		elseif ( in_array( $group, $this->no_mc_groups ) )
			$value = false;
		else {
			$value = apc_fetch( $key );
			if ( is_object( $value ) && 'ArrayObject' == get_class( $value ) )
				$value = $value->getArrayCopy();
		}

		@ ++$this->stats['get'];
		$this->group_ops[$group][] = "get $id";

		if ( NULL === $value )
			$value = false;

		$this->cache[$key] = $value;

		if ( 'checkthedatabaseplease' === $value )
			$value = false;

		return $value;
	}

	function key( $key, $group ) {
		global $blog_id;

		if ( empty( $group ) )
			$group = 'default';

		if ( false !== array_search( $group, $this->global_groups ) )
			$prefix = '';
		else
			$prefix = $blog_id . ':';

		return WP_APC_KEY_SALT . ':' . $this->abspath . ":$prefix$group:$key";
	}

	function replace( $id, $data, $group = 'default', $expire = 0 ) {
		return $this->set( $id, $data, $group, $expire );
	}

	function set( $id, $data, $group = 'default', $expire = 0 ) {
		$key = $this->key( $id, $group );
		if ( isset( $this->cache[$key] ) && ('checkthedatabaseplease' === $this->cache[$key] ) )
			return false;
		$this->cache[$key] = $data;

		if ( in_array( $group, $this->no_mc_groups ) )
			return true;

		$expire = ( $expire == 0 ) ? $this->default_expiration : $expire;
		if ( is_array( $data ) )
			$data = new ArrayObject( $data );
		$result = apc_store( $key, $data, $expire );

		return $result;
	}

	function colorize_debug_line( $line ) {
		$colors = array(
			'get' => 'green',
			'set' => 'purple',
			'add' => 'blue',
			'delete' => 'red'
		);

		$cmd = substr( $line, 0, strpos( $line, ' ' ) );

		$cmd2 = "<span style='color:{$colors[$cmd]}'>$cmd</span>";

		return $cmd2 . substr( $line, strlen( $cmd ) ) . "\n";
	}

	function stats() {
		echo "<p>\n";
		foreach ( $this->stats as $stat => $n ) {
			echo "<strong>$stat</strong> $n";
			echo "<br/>\n";
		}
		echo "</p>\n";
		echo "<h3>APC:</h3>";
		foreach ( $this->group_ops as $group => $ops ) {
			if ( !isset( $_GET['debug_queries'] ) && 500 < count( $ops ) ) { 
				$ops = array_slice( $ops, 0, 500 ); 
				echo "<big>Too many to show! <a href='" . add_query_arg( 'debug_queries', 'true' ) . "'>Show them anyway</a>.</big>\n";
			} 
			echo "<h4>$group commands</h4>";
			echo "<pre>\n";
			$lines = array();
			foreach ( $ops as $op ) {
				$lines[] = $this->colorize_debug_line($op); 
			}
			print_r($lines);
			echo "</pre>\n";
		}
		if ( $this->debug ) {
			$apc_info = apc_cache_info();
			echo "<p>";
			echo "<strong>Cache Hits:</strong> {$apc_info['num_hits']}<br/>\n";
			echo "<strong>Cache Misses:</strong> {$apc_info['num_misses']}\n";
			echo "</p>\n";
		}
	}

	function WP_Object_Cache() {
		$this->abspath = md5( ABSPATH );
	}
}

if ( function_exists( 'apc_inc' ) ) {
	class APC_Object_Cache extends WP_Object_Cache {
		function incr( $id, $n, $group ) {
			return parent::incr2( $id, $n, $group );
		}
	}
} else {
	class APC_Object_Cache extends WP_Object_Cache {
		// Blank
	}
}
