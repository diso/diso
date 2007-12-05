<?php
/*
Plugin Name: Contacts List
Plugin URI: http://diso.googlecode.com/
Description:  Output microformatted blogroll links on a static page.
Version: 0.5
Author: Chris Messina and Steve Ivy
Author URI: http://diso.googlecode.com/
*/
?>
<?php
/*

INSTRUCTIONS
------------
1. Upload this file into your wp-content/plugins directory.
2. Activate the WP Microformatted Blogroll plugin in your WordPress admin panel.
3. Create a new static page.
4. Add <!--contactspage--> to the static page content where you want the links
to appear.

Enjoy!

*/
?>
<?php

function wp_cl_styles() {
  $wp_cl_stylesheet = '<link rel="stylesheet" type="text/css" href="' . get_bloginfo('wpurl') . '/wp-content/plugins/wp-contactlist/styles/contactlist.css" />' . "\n";
	
	echo($wp_cl_stylesheet);
}

define('DEBUG',false);

if  ( class_exists('WordpressOpenIDLogic') ) {
	$has_wp_openid = true;
} else {
	$has_wp_openid = false;
}

$check_openid = get_option("contactlist_check_openid");

/* ========= admin ========= */
function cl_add_pages() {
	// Add a new submenu under Options:
	add_options_page('Contacts List Options', 'Contacts List', 8, __FILE__, 'cl_options_page');
}

function cl_options_page () {
	// variables for the field and option names 
  $opt_name = 'cl_check_openid';
  $hidden_field_name = 'cl_submit_hidden';
  $data_field_name = 'cl_check_openid';
	
	// Read in existing option value from database
  $opt_val = get_option( $opt_name );
	
	if( $_POST[ $hidden_field_name ] == 'Y' ) {
      // Read their posted value
      $opt_val = $_POST[ $data_field_name ];

      // Save the posted value in the database
      update_option( $opt_name, $opt_val );

      // Put an options updated message on the screen
			?>
			<div class="updated"><p><strong>Options saved.</strong></p></div>
			<?php
  }
	
	// Now display the options editing screen
  echo '<div class="wrap">';
	
	// header
  echo "<h2>Microformatted Blogroll Plugin Options</h2>";
	
	// options form
  
  ?>

	<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

	<p>
		Lookup Users with OpenID:
		<select name="<?php echo $data_field_name; ?>">
			<option value="Y"<?php if ($opt_val=='Y') echo " selected" ?>>Yes</option>
			<option value="N"<?php if ($opt_val=='N') echo " selected" ?>>No</option>
		</select>
  </p>
	<hr />

	<p class="submit">
	<input type="submit" name="Submit" value="Update Options" />
  </p>

	</form>
	</div>

	<?php
}

// Hook for adding admin menus
add_action('admin_menu', 'cl_add_pages');

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
function get_user_by_uri ($uri) {
  global $wpdb;
  
	//print "<pre>LOOKING FOR:";
	//print_r ($data);
	//print "</pre>";

  $uri      = normalize_uri($uri);
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
    return get_userdata($row->id);
  }
}

/* ========== the main work ========== */

function cl_generateblogroll() {
	global $wpdb, $has_wp_openid, $check_openid;
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
	if (DEBUG) print "<pre>CHECK_OPENID? ".get_option("cl_check_openid")."</pre>";
	
	$output .= "\n <ul class=\"xoxo blogroll\">\n";

	foreach ($results as $row) {
			
		$the_link           = wp_specialchars($row->link_url);
		
		$contact_rel        = $row->link_rel;
		$contact_blog_name  = $row->link_description;
		$contact_notes      = $row->link_notes;
		
		$a_user             = get_user_by_uri($the_link);
		
		if (DEBUG) print "<pre>A USER:" . print_r ($a_user, true) . "</pre>";
		
		$has_openid = false;
		if (null !== $a_user && get_usermeta($a_user->ID, 'registered_with_openid'))
		  $has_openid = true;
		
		$openid_uri='';
		
		if($check_openid=='Y' && $has_openid && $has_wp_openid) {
			// get openid url
			// this only works if wpopenid is installed, but i don't know yet 
			//    how to check for the plugin
			$sql = "SELECT uurl_id, url	FROM ".$wpdb->prefix."openid_identities WHERE user_id = '$a_user->ID'";
		
			if (DEBUG) print "<pre>" . print_r ($sql, true) . "</pre>";
		
			$oid_results = $wpdb->get_results($sql);
		
			if (DEBUG) "<pre>" . print_r ($oid_results, true) . "</pre>";
			
			$openid_uri = $oid_results[0]->url;
		}
		
		$contact_fn = wp_specialchars($row->link_name, ENT_QUOTES);    

		if (empty($contact_rel) or empty($contact_fn)) {
			continue; // skip ahead to next record
	  } elseif (!($check_openid=='Y')) {
			$output .= "\t\t<li class='vcard'><a class='url fn' rel='$contact_rel'  href='$the_link'>$contact_fn</a></li>";
		} else {
			$output .= "\t<li class='vcard'>\r\n";
  		if ($has_openid) {
  			$output .= "\t\t<a class='url fn openid' rel='$contact_rel'  href='$openid_uri'>$contact_fn</a>";
      } else {
  			$output .= "\t\t<span class='fn'>$contact_fn</span>";
      }
      if (!empty($contact_blog_name) and ($has_openid)) {
			  $output .= " &mdash; <a class='url' href='$the_link'>$contact_blog_name</a>";
			} else {
			  $output .= " &mdash; <a class='url' href='$the_link' rel='$contact_rel'>$contact_blog_name</a>";
			}
			
		  $output .= "\r\n";
			$output .= "\t</li>\r\n";
	  }

		$output .= "\n";

	}
	
	$output .= "\n</ul>\n";

	return $output;
}

function cl_page_callback($matches) {
	return cl_generateblogroll();
}

function cl_page_filter($content) {
	$content = preg_replace_callback('|<!--contactspage-->|i', 'cl_page_callback', $content);
	return $content;
}

add_action('wp_head', 'wp_cl_styles');
add_filter('the_content', 'cl_page_filter');

/*
	Template Tag 
*/
function cl_blogroll () {
	echo cl_generateblogroll();
}

/* ============== widget ============== */
function widget_cl_init() {
	
	// Check for the required API functions
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return;
		
	function widget_fl($args) {
		extract($args);
		$defaults = array();
		$options = (array) get_option('widget_fl');
		$m=array();
		//print "<!--" . print_r($args, true) . "-->";
		if (empty($before_widget))
			$before_widget='<div class="widget widget_fl">';
		
		if (empty($after_widget))
			$after_widget='</div>';
		
		foreach ( $defaults as $key => $value )
			if ( !isset($options[$key]) )
				$options[$key] = $defaults[$key];

		echo $before_widget;
		echo $before_title;
		echo (!empty($options['title'])) ? $options['title'] : "Blogroll";
		echo $after_title;
			
		echo cl_generateblogroll();
		echo $after_widget;
		
	}
	
	register_sidebar_widget(array('Microformatted Blogroll', 'widgets'), 'widget_fl');
	
}

add_action('widgets_init', 'widget_cl_init');