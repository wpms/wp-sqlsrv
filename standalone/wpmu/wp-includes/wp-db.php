<?php
// help maintain backwards compatibility.
if ( !defined('DB_TYPE') ) {
	define('DB_TYPE', 'mysql');
}

require_once(dirname(__FILE__) . '/wp-db/' . DB_TYPE . '/' . DB_TYPE . '.php');

if ( !isset($wpdb) ) {
	/**
	* WordPress Database Object, if it isn't set already in wp-content/db.php
	* @global object $wpdb Creates a new wpdb object based on wp-config.php Constants for the database
	* @since 0.71
	*/
	$wpdb = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
}
