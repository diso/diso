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
2. Activate the Contacts List plugin in your WordPress admin panel.
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
$link_formats = array (
  'default'=>array(
    'label'=>"Username links to blog (No OpenID)",
		// sprintf args: $contact_rel, $contact_blog_link, $contact_fn 
    'format'=>"<li class='vcard'><a class='url fn' rel='%s'  href='%s'>%s</a></li>",
		'preview'=> "&lt;li class='vcard'>&lt;a class='url fn' rel='aquaintance'  href='http://example.com/blog' title='Example Blog'>Example Username&lt;/a>&lt;/li>"
  ),
	'username_link_only'=>array(
    'label'=>"Username links to blog or openid (if user registered via OpenID)",
		// sprintf args: $openid_class, $contact_rel, $openid_uri_or_blog_link, $contact_fn   
    'format'=>"<li class='vcard'><a class='url fn %s' rel='%s' href='%s '>%s</a></li>",
		'preview'=> "&lt;li class='vcard'>&lt;a class='url fn openid' rel='aquaintance'  href='http://username.example.com' title='Example OpenID'>Example Username&lt;/a>&lt;/li>"
  ),
	'username_openid_blogname_bloglink'=>array(
    'label'=>"Username links to openid if user registered via OpenID, blogname links to their blog",
		// sprintf args: $openid_class, $contact_rel, $openid_uri, $contact_fn, $blog_link, $contact_rel,  $blog_name
    'format'=>"<li class='vcard'>|<a class='url fn %s' rel='%s' href='%s'>%s</a>" . 
						  "|<a class='url' href='%s' rel='%s'>%s</a>|</li>",
		'preview'=> "&lt;li class='vcard'>&lt;a class='url fn openid' rel='aquaintance'  href='http://username.example.com' title='Example OpenID'>Example Username&lt;/a> &mdash &lt;a class='url' href='http://example.com/blog' rel='aqcuaintance'>Example Blog&lt;/a>&lt;/li>"
  ),
);

if  ( class_exists('WordpressOpenIDLogic') ) {
	$has_wp_openid = true;
} else {
	$has_wp_openid = false;
}

$check_openid = get_option("cl_check_openid") || 'Y';
$link_format = get_option("cl_link_format");
$link_format = $link_format=='' ? 'default' : $link_format;

/* ========= admin ========= */
function cl_add_pages() {
	// Add a new submenu under Options:
	add_options_page('Contacts List Options', 'Contacts List', 8, __FILE__, 'cl_options_page');
}

function cl_options_page () {
	global $link_formats, $check_openid, $link_format;
	// variables for the field and option names 
  // Read in existing option value from database
	$hidden_field_name = 'cl_submit_hidden';

  $opt_check_openid_val 	= get_option( 'cl_check_openid' );
	$field_check_openid = 'cl_check_openid';
	$opt_link_format_val  	= get_option( 'cl_link_format' );
	$field_link_format 	= 'cl_link_format';
	
	if( $_POST[ $hidden_field_name ] == 'Y' ) {
      // Read their posted value
      $check_openid = $_POST[ $field_check_openid ];
			$link_format = $_POST[ $field_link_format ];

      // Save the posted value in the database
      update_option( 'cl_check_openid', $check_openid );
      update_option( 'cl_link_format', $link_format );

      // Put an options updated message on the screen
			?>
			<div class="updated"><p><strong>Options saved.</strong></p></div>
			<?php
  }
	
	// Now display the options editing screen
  echo '<div class="wrap">';
	
	// header
  echo "<h2>Contacts List Plugin Options</h2>";
	
	// options form
  
  ?>

	<form name="form1" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
	<input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">

	<p>
		Lookup Users with OpenID:
		<select name="cl_check_openid">
			<option value="Y"<?php if ($opt_val=='Y') echo " selected" ?>>Yes</option>
			<option value="N"<?php if ($opt_val=='N') echo " selected" ?>>No</option>
		</select>
  </p>
	<p>
		Contact Link Format:
		<table id="contact-list-formats">
		<?php
		foreach ($link_formats as $fid=>$format_ary) {
			?>
				<tr>
					<td width="3" valign="top"><input<?php if($link_format==$fid) echo ' checked=\'checked\'' ?> type='radio' id='<?php echo $fid ?>' 
															 				name="cl_link_format" value='<?php echo $fid ?>' /></td>
					<td valign="top">
						<label  for="<?php echo $fid ?>"><?php echo $format_ary['label']?></label>
						<p><code><?php echo $format_ary['preview'] ?></code></p>
					</td>
				</tr>
			<?php
		}
		?>
		</table>
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
	global $wpdb, $has_wp_openid, $check_openid, $link_formats, $link_format;
	if (DEBUG) print "<pre>link_format: $link_format</pre>";
	
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
		if (DEBUG) print "<pre>check_openid: $check_openid, has_openid: $has_openid, has_wp_openid: $has_wp_openid</pre>";
		
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
		
		// render the link
		if (DEBUG) "<pre>contact_rel: $contact_rel, contact_fn: $contact_fn</pre>";
		if (empty($contact_rel) or empty($contact_fn)) {
			continue; // skip ahead to next record
	  }	elseif (!($check_openid=='Y') or ($link_format=='default')) {
				$output .= "\t\t" . sprintf($link_formats[$link_format]['format'], $contact_rel, $contact_blog_link, $contact_fn);
		} else {
			$openid_class = $has_openid ? 'openid' : '';
			$openid_uri_or_blog_link = $has_openid ? $openid_uri : $the_link;
			if (DEBUG) {
				print "<pre>has_openid: $has_openid; openid_class: $openid_class; openid_uri_or_blog_link: $openid_uri_or_blog_link; </pre>";
			}
			switch ($link_format) {
				case 'username_link_only':
					// Username links to blog or openid (if user registered via OpenID)
					
					// sprintf args: $openid_class, $contact_fn, $openid_uri_or_blog_link, $contact_rel
					$link = "\t\t" . sprintf($link_formats[$link_format]['format'], 
							$openid_class, $contact_rel, $openid_uri_or_blog_link, $contact_fn);
					$output .= $link;
					break;
				case 'username_openid_blogname_bloglink':
					// Username links to openid if user registered via OpenID, blogname links to their blog
					// sprintf args: $openid_class, $contact_rel, $openid_uri, $contact_fn, $blog_link, $contact_rel, $blog_name 
					$fmtary = split("\|",$link_formats[$link_format]['format']);
					/*
					0 - <li>
					1 - openid <a>
					2 - blog <a>
					3 - </li>
					*/
					if ($has_openid) {
						$name = sprintf($fmtary[1], $openid_class, $contact_rel, $openid_uri, $contact_fn);
					} else {
						$name = $contact_fn;
					}
					if ($contact_blog_name != '') {
						$fmt = $fmtary[0] . $name . " &mdash; " . $fmtary[2] . $fmtary[3];
						$link = "\t\t" . sprintf($fmt, $the_link, $contact_rel, $contact_blog_name);
						$output .= $link;

					} else {
						$output .= $name;
						
					}
					break;
				default:
					break;
			}
		}
		/*
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
			} elseif (!empty($contact_blog_name)) {
			  $output .= " &mdash; <a class='url' href='$the_link' rel='$contact_rel'>$contact_blog_name</a>";
			}
			
		  $output .= "\r\n";
			$output .= "\t</li>\r\n";
	  }
		*/
		
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
		
	function widget_cl($args) {
		extract($args);
		$defaults = array();
		$options = (array) get_option('widget_cl');
		$m=array();
		//print "<!--" . print_r($args, true) . "-->";
		if (empty($before_widget))
			$before_widget='<div class="widget widget_cl">';
		
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
	
	register_sidebar_widget(array('Microformatted Blogroll', 'widgets'), 'widget_cl');
	
}

add_action('widgets_init', 'widget_cl_init');