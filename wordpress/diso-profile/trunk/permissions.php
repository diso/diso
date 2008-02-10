<?php

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

function user_is($taxonomies) {//is the current user associated with the given XFN values on the contact list?
	static $ids, $bookmarks;
	if(!$taxonomies || (is_array($taxonomies) && (!count($taxonomies) || !$taxonomies[0]))) return true;
	if(is_string($taxonomies)) $taxonomies = array($taxonomies);
	if(!$ids) {
		if(function_exists('is_user_openid') && is_user_openid()) {
			global $wpdb, $userdata;//this really should go in wp-openid
			get_currentuserinfo();
			$ids = $wpdb->get_results("SELECT url FROM {$wpdb->prefix}openid_identities WHERE user_id=".$userdata->ID);
		} else if(function_exists('is_user_facebook') && is_user_facebook()) {
			if(function_exists('facebook_from_user'))
				$ids = array(facebook_from_user());
		}//end if-elses
		if(!$ids || !count($ids)) return false;
		require_once dirname(__FILE__).'/sgapi.php';
		$sga = new SocialGraphApi(array('edgesout'=>0,'edgesin'=>0,'followme'=>1,'sgn'=>0));
		$ids2 = array();
		foreach($ids as $id) {//sgapi
			if(is_object($id)) $id = $id->url;
			if(!$id) continue;
			$data = $sga->get($id);
			if(!$data || !count($data)) continue;
			$ids2 = array_merge($ids2, array_keys($data['nodes']));
		}//end foreach
		$ids = $ids2; unset($ids2);
		$bookmarks = get_bookmarks();
	}//end if ! ids
	if(!$ids || !count($ids)) return false;
	foreach($ids as $id) {
		foreach($bookmarks as $bookmark) {
			if(normalize_uri($bookmark->link_url) == normalize_uri($id)) {
				$rels = explode(' ',$bookmark->link_rel);
				foreach($taxonomies as $val)
					if(in_array($val,$rels))
						return true;
				$cats = wp_get_object_terms($bookmark->link_id, 'link_category');
				foreach($taxonomies as $val)
					if(in_array($val->name,$cats))
						return true;
			}//end if link matches	
		}//end foreach bookmarks
	}//end foreach
	return false;
}//end function user_is

function diso_permissions_page() {
	global $userdata;

	get_currentuserinfo();

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
	if(count($_POST['permissions_level'])) {//if saving
		//$permissions = $userdata->profile_permissions;
		$permissions = array();
		foreach($_POST['permissions_level'] as $field => $level) {
			if($level == 'custom') {
				foreach($_POST['permissions'] as $key => $val)
					$permissions[$key] = array_keys($val);
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

	$fields = array(
		'First Name' => 'given-name',
		'Middle Name(s)' => 'additional-name',
		'Last Name' => 'family-name',
		'Nickname' => 'nickname',
		'E-mail Address' => 'email',
		'Website(s)' => 'urls',
		'AIM' => 'aim',
		'Y!IM' => 'yim',
		'Jabber' => 'jabber',
		'About Me' => 'note',
		'Photo' => 'photo',
		'Organization' => 'org',
		'Street Address' => 'street-address',
		'City' => 'locality',
		'Province/State' => 'region',
		'Postal Code' => 'postal-code',
		'Country' => 'country-name',
		'Telephone Number' => 'tel',
	);

	echo '<div class="wrap">';
	echo '<h2>Change Profile Permissions</h2>';
	echo '<b>Field &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; People who can view</b>';
	echo '<form method="post" action="">';
	foreach($fields as $label => $field) {
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
	echo ' <input style="clear:both;margin-top:1em;" type="submit" value="Save &raquo;" /> ';
	echo '</form>';
	echo '</div>';
}//end function diso_permissions_page

function diso_permissions_tab($s) {
	add_submenu_page('profile.php', 'Permissions', 'Permissions', 'read', __FILE__, 'diso_permissions_page');
	return $s;
}//end function
add_action('admin_menu', 'diso_permissions_tab');

?>
