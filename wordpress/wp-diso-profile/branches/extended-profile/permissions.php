<?php

/**
 * Provide profile attributes which should be included in the Permissions plugin.
 *
 * @param array $permissions existing permissions
 * @return array new permissions
 */
function ext_profile_permissions($permissions) {
	$permissions['profile'] = array(
		'name' => 'Profile Permissions',
		'order' => 1,
		'fields' => array(
			'given-name' => 'First Name',
			'additional-name' => 'Middle Name(s)',
			'family-name' => 'Last Name',
			'nickname' => 'Nickname',
			'-1' => '-',
			'urls' => 'Website(s)',
			'email' => 'E-mail Address',
			'aim' => 'AIM',
			'yim' => 'Y!IM',
			'jabber' => 'Jabber',
			'-2' => '-',
			'note' => 'About Me',
			'photo' => 'Photo',
			'org' => 'Organization',
			'-3' => '-',
			'street-address' => 'Street Address',
			'locality' => 'City',
			'region' => 'Province/State',
			'postal-code' => 'Postal Code',
			'country-name' => 'Country',
			'tel' => 'Telephone Number',
			'-4' => '-',
		)
	);

	return $permissions;
}

add_filter('diso_permission_fields', 'ext_profile_permissions');

?>
