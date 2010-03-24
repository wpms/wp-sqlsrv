<?php
require_once( 'admin.php' );
$title = __( 'WordPress MU &rsaquo; Admin &rsaquo; Global Terms' );
$parent_file = 'wpmu-admin.php';

// handle forms
switch( $_GET[ 'action' ] ) {
	case "fixblog":
		check_admin_referer( 'fixblog' );
		if ( isset( $_POST[ 'id' ] ) && $_POST[ 'id' ] != '' ) {
			fix_blog_terms( (int)$_POST[ 'id' ] );
			wp_redirect( 'wpmu-terms.php?updated=termsfixed' );
			die();
		}
	break;
}

include('admin-header.php');

if ( is_site_admin() == false ) {
    wp_die( __( 'You do not have permission to access this page.' ) );
}

if ( isset( $_GET[ 'updated' ] ) ) {
	switch( $_GET[ 'updated' ] ) {
		case "termsfixed":
			?><div id="message" class="updated fade"><p><?php _e( 'Terms Updated.' ) ?></p></div><?php
		break;
	}
}
?>
<div class="wrap">
<h2><?php _e( 'Global Terms' ); ?></h2>
<p><?php _e( 'WordPress MU uses a global taxonomy table to keep track of all the tags and categories used on all the blogs. Occasionally this information can become out of sync with the local blog taxonomy tables.' ); ?></p>
<p><?php _e( 'This problem is often caused by adding a blog using MySQL directly rather than going through the WordPress importer. It can also happen when a plugin manipulates the terms tables directly without going through the WordPress API.' ); ?></p>
<p><?php _e( 'Please make a backup of the terms, term_taxonomy and term_relationships tables before running this.' ); ?></p>
<?php
switch( $_GET[ 'action' ] ) {
	default:
		echo '<form method="post" action="wpmu-terms.php?action=fixblog">';
		wp_nonce_field( "fixblog" );
		echo '<p>' . __( 'Enter a blog_id to fix the categories and terms of that blog.' ) . '</p>';
		echo '<p><strong>' . __( 'Blog id:' ) . '</strong> <input type="text" name="id" value="" /></p>';
		echo '<p><input type="checkbox" name="renameterms" value="1" /> ' . __( 'Rename terms <sup>*</sup>' ) . '</p>';
		echo '<input type="submit" value="Fix Terms" />';
		echo '</form>';
		echo '<p>' . __( '<sup>*</sup> WordPress can have terms (tags or categories) with names and slugs that do not match. WordPress MU forces the slug to be a sanitized version of the name. To preserve blog URLs this page can rename the name to match the slug. It will replace underscores with spaces and the first letter will be upper case.' ) . '</p>';
	break;
}

function fix_single_blog_term( $term ) {
	global $wpdb;
	$new_term = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $term->slug ) );
	if ( !$new_term ) {
		// term not found in global table, create it!
		global_terms( $term->term_id );
		$new_term = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $term->slug ) );
	}
	if ( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->terms} WHERE term_id = %d AND slug = %s", $new_term->cat_ID, $new_term->category_nicename ) ) ) {
		return true; // term_id is ok
	} elseif ( $existing_term = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->terms} WHERE term_id = %d", $new_term->term_id ) ) ) {
		// recurse to fix the existing term that's in the way
		fix_single_blog_term( $existing_term ); 
	}
	// fix the term!
	global_terms( $term->term_id );
	return true;
}

function fix_blog_terms( $id = 0 ) {
	global $wpdb;

	if ( $id == 0 ) {
		$id = $wpdb->blogid;
	} else {
		switch_to_blog( $id );
	}
	
	$maxterm = $wpdb->get_var( "SELECT max(cat_ID) FROM {$wpdb->sitecategories}" );
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->terms}" );
	foreach( $rows as $row ) {
		if ( isset( $_POST[ 'renameterms' ] ) && sanitize_title( $row->name ) != $row->slug ) {
			if ( ! $name = $wpdb->get_var( $wpdb->prepare( "SELECT cat_name FROM {$wpdb->sitecategories} WHERE category_nicename = %s", $row->slug ) ) ) {
				$name = str_replace( '_', ' ', ucfirst( $row->slug ) );
			}
			$wpdb->update( $wpdb->terms, array( 'name' => $name ), array('term_id' => $row->term_id) );
		}
		if ( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->sitecategories} WHERE cat_ID = %d AND category_nicename = %s", $row->term_id, $row->slug ) ) )
			continue;
		$term_id = $row->term_id + mt_rand( $maxterm+100, $maxterm+4000 );
		if( get_option( 'default_category' ) == $row->term_id )
			update_option( 'default_category', $term_id );

		$wpdb->update( $wpdb->terms, array( 'term_id' => $term_id ), array( 'term_id' => $row->term_id ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'term_id' => $term_id ), array( 'term_id' => $row->term_id ) );
		$wpdb->update( $wpdb->term_taxonomy, array( 'parent' => $term_id ), array( 'parent' => $row->term_id ) );

		clean_term_cache( $row->term_id );
	}
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->terms}" );
	foreach( $rows as $row ) {
		if ( null == $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->sitecategories} WHERE cat_ID = %d AND category_nicename = %s", $row->term_id, $row->slug ) ) ) {
			fix_single_blog_term( $row );
			clean_term_cache( $row->term_id );
		}
	}
	restore_current_blog();
}
?>
</div>
<?php include('./admin-footer.php'); ?>
