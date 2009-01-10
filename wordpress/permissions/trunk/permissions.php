<?php
/*
Plugin Name: DiSo Permissions
Description: Provides a permissions framework for other DiSo components.
Version: 0.1
Author: DiSo Development Team
Author URI: http://diso-project.org/
*/

if( !function_exists( 'normalize_uri' ) ) {
  function normalize_uri ($url) {
		  $url = trim( $url );

		  @$parts = parse_url( $url );
		  $scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : null;

		  if( !$scheme )
		  {
				$url = 'http://' . $url;
				@$parts = parse_url( $url );
		  }

		  $path = isset( $parts['path'] ) ? $parts['path'] : null;

		  if( !$path )
				$url .= '/';

		  return $url;
  }
}//end if


/**
 * Check if the current logged-in user contains any of the specified XFN 
 * relationships with this blog.
 *
 * @return boolean true if the current user has any of the specified relatinoships
 */
function diso_user_is($taxonomies) {
	static $ids, $bookmarks;

	if (empty($taxonomies)) { return true; } 
	if (!is_array($taxonomies)) { $taxonomies = array($taxonomies); }

	$user = wp_get_current_user();

	if ($user->user_level > 9) return true;

	if (!$ids) {
		$ids = apply_filters('diso_current_user_urls', array());
		if (empty($ids)) return false;

		$ids = diso_get_sgapi_urls($ids);
		$bookmarks = get_bookmarks();
	}

	if (empty($ids)) return false;

	foreach($ids as $id) {
		foreach($bookmarks as $bookmark) {
			if(normalize_uri($bookmark->link_url) == normalize_uri($id)) {

				// check link rel tags
				$rels = explode(' ',$bookmark->link_rel);
				foreach($taxonomies as $val) {
					if(in_array($val,$rels)) {
						return true;
					}
				}

				// check link categories
				$cats = wp_get_object_terms($bookmark->link_id, 'link_category');
				foreach($taxonomies as $val) {
					if(in_array($val->name,$cats)) {
						return true;
					}
				}
			}
		}
	}

	return false;
}


/**
 * Get current user URLs from OpenID and Facebook plugins.  These should be moved to their respective plugins in the future.
 */
function diso_current_user_urls($urls) {
	global $wpdb;
	$user = wp_get_current_user();
	
	if(function_exists('is_user_openid') && is_user_openid()) {
		$urls = array_merge($urls, $wpdb->get_results("SELECT url FROM {$wpdb->prefix}openid_identities WHERE user_id=".$user->ID));
	} 
	
	if(function_exists('is_user_facebook') && is_user_facebook()) {
		if(function_exists('facebook_from_user'))
			$urls = array_merge($urls, array(facebook_from_user()));
	}

	return $urls;
}
add_filter('diso_current_user_urls', 'diso_current_user_urls');


/**
 * Use Google's Social Graph API to get all equivalent URLs for the specified URLs.
 */
function diso_get_sgapi_urls($urls) {

	$new = array();

	if (!empty($urls)) { 
		require_once dirname(__FILE__).'/sgapi.php';
		$sga = new SocialGraphApi(array('edgesout'=>0,'edgesin'=>0,'followme'=>1,'sgn'=>0));

		foreach($urls as $url) {
			if(is_object($url)) $url = $url->url;
			if(!$url) continue;
			$data = $sga->get($url);
			if(!$data || !count($data)) continue;
			$new = array_merge($new, array_keys($data['nodes']));
		}
	}

	return $new;
}

function register_diso_permission_field($label, $field) {
	$fields = get_option('diso_permission_fields');
	if(!is_array($fields)) $fields = array();
	$fields[$label] = $field;
	update_option('diso_permission_fields', $fields);
}//end function register_diso_permission_field

function diso_permissions_taxonomies() {
	$taxonomies = array(
		'Yourself' => 'me',
		'Friends' => 'friend',
		'Contacts' => 'contact',
		'Acquaintances' => 'acquaintance',
		'People You\'ve Met' => 'met',
		'Co-workers' => 'co-worker',
		'Colleagues' => 'colleague',
		'Co-residents' => 'co-resident',
		'Neighbors' => 'neighbor',
		'Your Children' => 'child',
		'Your Parents' => 'parent',
		'Your Siblings' => 'sibling',
		'Your Spouse' => 'spouse',
		'Your Family' => 'kin',
		'Muses' => 'muse',
		'Crushes' => 'crush',
		'Dates' => 'date',
		'Sweetheart' => 'sweetheart'
	);
	$terms = get_terms('link_category');
	foreach($terms as $term)
		$taxonomies[$term->name] = $term->name;
	return $taxonomies;
}

function diso_permissions_page() {
	global $userdata;

	get_currentuserinfo();

	$taxonomies = diso_permissions_taxonomies();

	if(count($_POST['permissions_level'])) {//if saving
		//$permissions = $userdata->profile_permissions;
		$permissions = array();
		foreach($_POST['permissions_level'] as $field => $level) {
			if($level == 'custom') {
				foreach($_POST['permissions'] as $key => $val)
					$permissions[$key] = array_unique(array_merge(array_keys($val), array('me')));
			} else {
				switch($level) {
					case 'any':
						$permissions[$field] = array_values($taxonomies);
						break;
					case 'family':
						$permissions[$field] = array('me', 'kin', 'child', 'parent', 'sibling', 'spouse');
						break;
					case 'family,friends':
						$permissions[$field] = array('me', 'kin', 'child', 'parent', 'sibling', 'spouse', 'friend', 'muse', 'date', 'sweetheart');
						break;
					case 'public':
						$permissions[$field] = array();
						break;
				}//end switch
			}//end if permissions_level
		}//end foreach
		update_usermeta($userdata->ID, 'profile_permissions', $permissions);
		update_usermeta($userdata->ID, 'profile_permissions_level', $_POST['permissions_level']);
		$userdata->profile_permissions = $permissions;
		$userdata->profile_permissions_level = $_POST['permissions_level'];
	}//end if saving

	$permission_fields = apply_filters('diso_permission_fields', array());

	echo '<div class="wrap">';
	echo '<h2>Change Permissions</h2>';
	echo '<b>Field &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; People who can view</b>';
	echo '<form method="post" action="">';

	usort($permission_fields, create_function('$a,$b', 'return ($a["order"] == $b["order"] ? 0 : ($a["order"] > $b["order"] ? 1 : -1));'));
	foreach($permission_fields as $set) {
		echo '<h3>'.$set['name'].'</h3>';
		foreach ($set['fields'] as $field => $label) {

			if($label == '-') {
				echo '<hr style="marign:2em;clear:both;" />'; 
				continue;
			}

			echo '<div style="margin-bottom:1em;clear:both;">';
			echo ' <label style="float:left;width:10em;" for="'.$field.'">'.$label.':</label> ';
			echo '	<select id="'.$field.'-basic" name="permissions_level['.$field.']" onchange="if(this.value == \'custom\') { document.getElementById(\''.$field.'-custom\').style.display = \'block\';  } else { document.getElementById(\''.$field.'-custom\').style.display = \'none\'; }">';
			echo '		<option '.($userdata->profile_permissions_level[$field] == 'public' ? 'selected="selected" ' : '').'value="public">Public</option>';
			echo '		<option '.($userdata->profile_permissions_level[$field] == 'any' ? 'selected="selected" ' : '').'value="any">Any Contact</option>';
			echo '		<option '.($userdata->profile_permissions_level[$field] == 'family' ? 'selected="selected" ' : '').'value="family">Family</option>';
			echo '		<option '.($userdata->profile_permissions_level[$field] == 'family,friends' ? 'selected="selected" ' : '').'value="family,friends">Family and Friends</option>';
			echo '		<option '.($userdata->profile_permissions_level[$field] == 'custom' ? 'selected="selected" ' : '').'value="custom">Custom</option>';
			echo '	</select>';
			echo '	<div id="'.$field.'-custom" '.($userdata->profile_permissions_level[$field] != 'custom' ? 'style="display:none;" ' : '' ).'>';
			$c = 0;
			echo '<div style="float:left;clear:left;width:10em;">&nbsp;</div>';
			foreach($taxonomies as $ilabel => $term) {
				echo '<div style="float:left;width:11em;">';
				echo '<input type="checkbox" name="permissions['.htmlentities($field).']['.htmlentities($term).']"'.(@in_array($term, $userdata->profile_permissions[$field]) ? ' checked="checked"' : '').' />&nbsp;'.$ilabel.'</div>';
				$c++;
				if($c > 4) {
					$c = 0;
					echo '<div style="float:left;clear:left;width:10em;">&nbsp;</div>';
				}
			}//end
			echo '	</div>';
			echo '</div>';
		}//end foreach fields
	}
	echo ' <input style="clear:both;margin-top:1em;" type="submit" value="Save &raquo;" /> ';
	echo '</form>';

	echo '</div>';
}//end function diso_permissions_page

function diso_permissions_tab($s) {
	add_submenu_page('profile.php', 'Permissions', 'Permissions', 'read', __FILE__, 'diso_permissions_page');
	return $s;
}//end function

/*
get_currentuserinfo();
if($user_level > 9)
 */
	add_action('admin_menu', 'diso_permissions_tab');

?>
