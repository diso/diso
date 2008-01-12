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

function is_($xfn) {//is the current user associated with the given XFN values on the contact list?
	if(!$xfn || (is_array($xfn) && (!count($xfn) || !$xfn[0]))) return true;
	if(is_string($xfn)) $xfn = array($xfn);
	if(function_exists('is_user_openid') && is_user_openid()) {
		global $wpdb,$userdata;//this really should go in wp-openid
		get_currentuserinfo();
		$ids = $wpdb->get_results("SELECT url FROM {$wpdb->prefix}openid_identities WHERE user_id=".$userdata->ID);
	} else if(function_exists('is_user_facebook') && is_user_facebook()) {
		if(function_exists('facebook_from_user'))
			$ids = array(facebook_from_user());
	}//end if-elses
	if(!$ids || !count($ids)) return false;
	$bookmarks = get_bookmarks();
	foreach($ids as $id) {
		if(is_object($id)) $id = $id->url;
		foreach($bookmarks as $bookmark) {
			if(normalize_uri($bookmark->link_url) == normalize_uri($id)) {
				$rels = explode(' ',$bookmark->link_rel);
				foreach($xfn as $val)
					if(in_array($val,$rels))
						return true;
				return false;
			}//end if link matches	
		}//end foreach bookmarks
	}//end foreach
	return false;
}//end function is_

function diso_permissions_page() {
	global $userdata;

	get_currentuserinfo();
	if(count($_POST['permissions'])) {//if saving
		$permissions = $userdata->profile_permissions;
		foreach($_POST['permissions'] as $key => $val)
			$permissions[$key] = explode(',',$val);
		update_usermeta($userdata->ID, 'profile_permissions', $permissions);
		$userdata->profile_permissions = $permissions;
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
	echo '<b>Field &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; XFN Values that can see (blank for public)</b>';
	echo '<form method="post" action="">';
	foreach($fields as $label => $field) {
		echo '<div>';
		echo ' <label style="float:left;width:10em;" for="'.$field.'">'.$label.':</label> ';
		echo ' <input type="text" name="permissions['.$field.']" value="'.@implode(',',$userdata->profile_permissions[$field]).'" /> ';
		echo '</div>';
	}//end foreach fields
	echo ' <input type="submit" value="Save &raquo;" /> ';
	echo '</form>';
	echo '</div>';
}//end function diso_permissions_page

function diso_permissions_tab($s) {
	add_submenu_page('profile.php', 'Permissions', 'Permissions', 1, __FILE__, 'diso_permissions_page');
	return $s;
}//end function
add_action('admin_menu', 'diso_permissions_tab');

?>
