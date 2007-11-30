<?php
/*
Plugin Name: WP Microformatted Blogroll
Plugin URI: http://factorycity.net/projects/wp-microformatted-blogroll/
Description:  Output microformatted blogroll links on a static page.
Version: 0.5
Author: Chris Messina and Steve Ivy
Author URI: http://factoryjoe.com/
*/
?>
<?php
/*

INSTRUCTIONS
------------
1. Upload this file into your wp-content/plugins directory.
2. Activate the WP Microformatted Blogroll plugin in your WordPress admin panel.
3. Create a new static page.
4. Add <!--xfnpage--> to the static page content where you want the links
to appear.

Enjoy!

*/
?>
<?php

define('DEBUG',false);

if  ( class_exists('WordpressOpenIDLogic') ) {
	$has_wp_openid = true;
} else {
	$has_wp_openid = false;
}

/*
  For comparing URLs
*/
function normalize_uri ($uri) {
	if (substr($uri,0,7)=='http://')
		$uri = substr($uri,7);
	if (substr($uri,-1)=='/')
    $uri = substr($uri,0,-1);
	return $uri;
}

/*
Try to match a user in the DB with a blogroll user

$data['first']
$data['last']
$data['uri']
*/
function get_user_by_uri_and_name ($data) {
  global $wpdb;
  
	//print "<pre>LOOKING FOR:";
	//print_r ($data);
	//print "</pre>";

  $uri      = normalize_uri($data['uri']);
  $sql      = "SELECT id FROM ". $wpdb->users ." WHERE user_url LIKE '%$uri%'";
  $results  = $wpdb->get_results($sql);
  
	//print "<pre>RESULTS:";
	//print_r ($sql);
	//print_r ($results);
	//print "</pre>";

	if (!$results) {
		if (strpos($uri,'/')) {
			$uri = substr($uri,0,(strpos($uri,'/'))); // chop any path and try just the domain next
			//print "<pre>$uri</pre>";
			$sql = "SELECT id FROM ". $wpdb->users . " WHERE user_url LIKE '%$uri%'";

	  	$results = $wpdb->get_results($sql);
		}
		if (!$results) {
			return;
  	}
	}
  
  foreach ($results as $row) {
    //print_r($row);
    //print_r(get_usermeta($row->id)); exit;
    $usermeta = get_usermeta($row->id);
    
    //print "<pre>FOUND:";
		//print_r ($usermeta);
		//print "</pre>";

	  if ($data['first_name']==get_usermeta($row->id,'first_name') && $data['last_name']==get_usermeta($row->id,'last_name')) {
      return get_userdata($row->id);
    }
  }
}

function xfn_page_callback($matches) {
	global $wpdb, $has_wp_openid;
	$output = '';
	
	global $wpdb;
	$sql = "SELECT link_url, link_name, link_rel, link_description, link_notes
		FROM $wpdb->links
		WHERE link_visible = 'Y'
		ORDER BY link_name" ;

	$results = $wpdb->get_results($sql);
	if (!$results) {
		return;
	}
	
	$output .= "\n <ul class=\"xoxo\">";

	foreach ($results as $row) {
			
		$the_link           = wp_specialchars($row->link_url);
		
		$contact_rel        = $row->link_rel;
		$contact_blog_name  = $row->link_description;
		$contact_notes      = $row->link_notes;
		
		// get user
		$data               = array();
		$nb                 = split(' ',$row->link_name);
		$data['first_name'] = $nb[0];
		$data['last_name']  = $nb[1];
		$data['uri']        = $row->link_url;
		
		$a_user             = get_user_by_uri_and_name($data);
		
		if (DEBUG) print "<pre>A USER:" . print_r ($a_user, true) . "</pre>";
		
		$has_openid = false;
		if (null !== $a_user && get_usermeta($a_user->ID, 'registered_with_openid'))
		  $has_openid = true;
		
		$openid_uri='';
		
		if($has_openid && $has_wp_openid) {
			// get openid url
			// this only works if wpopenid is installed, but i don't know yet 
			//    how to check for the plugin
			$sql = "SELECT uurl_id, url	FROM ".$wpdb->prefix."openid_identities WHERE user_id = '$a_user->ID'";
		
			if (DEBUG) print "<pre>" . print_r ($sql, true) . "</pre>";
		
			$oid_results = $wpdb->get_results($sql);
			if ($oid_results) {
				
			}
		
			if (DEBUG) "<pre>" . print_r ($oid_results, true) . "</pre>";
			
			$openid_uri = $oid_results[0]->url;
		}
		
		$contact_fn = wp_specialchars($row->link_name, ENT_QUOTES) ;    

		if (empty($contact_rel) or empty($contact_fn)) {
			continue; // skip ahead to next record
	  } else {
			$output .= "\t<li class='vcard'>\r\n";
  		if ($has_openid) {
  			$output .= "\t\t<a class='url fn openid' rel='$contact_rel'  href='$openid_uri'>$contact_fn</a>";
      } else {
  			$output .= "\t\t<a class='url fn' rel='$contact_rel' href='$the_link'>$contact_fn</a>";
      }
      
      if (!empty($contact_blog_name)) {
			  $output .= "&mdash; <a class='url' href='$the_link'>$contact_blog_name</a>";
			}
			
		  $output .= "\r\n";
			$output .= "\t</li>\r\n";
	  }

		$output .= "\n";

	}
	
	$output .= "\n</ul>\n";

	return $output;
}

function xfn_page($content)
{
	$content = preg_replace_callback('|<!--xfnpage-->|i', 'xfn_page_callback', $content);
	return $content;
}

add_filter('the_content', 'xfn_page');

?>
