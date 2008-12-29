<?php

add_filter('get_avatar', 'ext_profile_avatar', 10, 3);

/*
 * This hooks into the Wordpress default avatar logic, and inserts
 * avatars detected from hCards / set in the proflie where appropriate.
 *
 * @param string $avatar current user avatar
 * @param int|string|object $id_or_email A user ID,  email address, or comment object
 * @param int $size Size of the avatar image
 * @return new user avatar
 */
function ext_profile_avatar($avatar, $id_or_email, $size) {
	if(is_numeric($id_or_email)) {
		$user = get_userdata($id_or_email);
	} else if (is_object($id_or_email)) {
		$user = get_userdata($id_or_email->user_id);
	} else if (!empty($id_or_email->comment_author_url)) {
		// We could do something smart and detect an hCard or the like on thoir homepage
		// This is slow, though, and should only be done once and cached, likely when the comment is posted
		// If the user logged in with OpenID we got their hCard avatar anyway
	}

	if($user && @$user->photo) {
		$avatar = '<img style="max-width:'.$size.'px;max-height:'.$size.'px;" class="avatar avatar-'.$size.'" src="'.clean_url($user->photo).'" alt="" />';	
	}

	return $avatar;
}

?>
