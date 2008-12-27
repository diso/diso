<?php

/*
 * This hooks into the Wordpress default avatar logic, and inserts
 * avatars detected from hCards / set in the proflie where appropriate.
 */
function ext_profile_avatar($gravatar, $id_or_email, $size) {
	if(is_numeric($id_or_email)) {
		$user = get_userdata((int)$id_or_email);
		if($user && $user->photo)
			return '<img style="max-width:'.$size.'px;max-height:'.$size.'px;" class="avatar avatar-'.$size.'" src="'.htmlspecialchars($user->photo).'" alt="" />';	
	} elseif(is_object($id_or_email)) {
		if(!empty($id_or_email->user_id)) {
			$user = get_userdata((int)$id_or_email->user_id);
		if($user && $user->photo)
			return '<img style="max-width:'.$size.'px;max-height:'.$size.'px;" class="avatar avatar-'.$size.'" src="'.htmlspecialchars($user->photo).'" alt="" />';	
		} elseif(!empty($id_or_email->comment_author_url)) {
			// We could do something smart and detect an hCard or the like on thoir homepage
			// This is slow, though, and should only be done once and cached, likely when the comment is posted
			// If the user logged in with OpenID we got their hCard avatar anyway
		}
	}
	return $gravatar;
}

add_filter('get_avatar', 'ext_profile_avatar', 10, 3);

?>
