<?php
/*
Plugin Name: Extended Profile
Plugin URI: http://wordpress.org/extend/plugins/extended-profile/
Description: Detect and import hCard data on new user, extended data for user profiles, easy hCard generation.
Version: trunk
Author: DiSo Development Team
Author URI: http://diso-project.org/
*/

require_once dirname(__FILE__).'/widget.php';
require_once dirname(__FILE__).'/permissions.php';
require_once dirname(__FILE__).'/avatar.php';

$hkit;

// register actions
add_action('user_register', 'ext_profile_hcard_import');
add_action('init', 'ext_profile_style');
add_action('wp_head', 'wp_print_styles', 9); // for pre-2.7
add_action('admin_init', 'ext_profile_admin');
add_shortcode('profile', 'ext_profile_shortcode');

// register profile actions
add_action('extended_profile', 'extended_profile_photo', 0);
add_action('extended_profile', 'extended_profile_name', 2);
add_action('extended_profile', 'extended_profile_nickname', 4);
add_action('extended_profile', 'extended_profile_org', 6);
add_action('extended_profile', 'extended_profile_note', 8);
add_action('extended_profile', 'extended_profile_contact', 10, 2);

// default filters
$filters = array('additional_name', 'org', 'street_address', 'locality', 'region', 'country_name', 'postal_code', 'tel');
foreach ( $filters as $filter ) {
	$filter = 'pre_ext_profile_' . $filter;
	add_filter($filter, 'strip_tags');
	add_filter($filter, 'trim');
	add_filter($filter, 'wp_filter_kses');
	add_filter($filter, 'wp_specialchars', 30);
}

$filters = array('photo', 'url');
foreach ( $filters as $filter ) {
	$filter = 'pre_ext_profile_' . $filter;
	add_filter($filter, 'strip_tags');
	add_filter($filter, 'trim');
	add_filter($filter, 'sanitize_url');
	add_filter($filter, 'wp_filter_kses');
}

// provide SREG attributes for OpenID plugin
add_filter('openid_server_sreg_country', 'ext_profile_openid_sreg_country', 10, 2);
add_filter('openid_server_sreg_postcode', 'ext_profile_openid_sreg_postcode', 10, 2);

register_activation_hook('extended-profile/extended-profile.php', 'ext_profile_activate');
if ( function_exists('register_uninstall_hook') ) {
	register_uninstall_hook('extended-profile/extended-profile.php', 'ext_profile_uninstall');
}


/**
 * Get the microformatted profile for the specified user.
 *
 * @param mixed $userid username or ID of user to get profile for.  If not 
 *                      specified, queried author will be used.
 * @param bool $echo if true, the profile will be echo()'ed out
 * @param bool $actionstream_aware should the profile exclude actionstream service URLs
 * @return string microformatted profile
 * @access public
 * @since 0.6
 */
function extended_profile($userid=null, $echo=true, $actionstream_aware=false) {
	$profile = get_extended_profile($userid, $actionstream_aware);
	if ($echo) echo $profile;
	return $profile;
}

/**
 * Activate plugin.
 */
function ext_profile_activate() {
	ext_profile_migrate_widget_data();
	ext_profile_migrate_profile_data();
}


/**
 * Uninstall plugin.
 */
function ext_profile_uninstall() {
	delete_option('widget_user_profile');
}


/**
 * Include stylesheet for displaying profiles.
 */
function ext_profile_style() {
	wp_enqueue_style('ext-profile', plugins_url('extended-profile/profile.css'));
}


/**
 * Register WordPress admin hooks.
 */
function ext_profile_admin() {
	add_action('profile_update', 'ext_profile_update');
	add_action('profile_personal_options', 'ext_profile_personal_options');
	add_action('show_user_profile', 'ext_profile_fields');
	add_action('edit_user_profile', 'ext_profile_fields');

	add_action('load-profile.php', 'ext_profile_admin_js');
	add_action('load-user-edit.php', 'ext_profile_admin_js');
	add_action('admin_head-profile.php', 'ext_profile_style');
	add_action('admin_head-user-edit.php', 'ext_profile_style');
}


/**
 * Load the javascript necessary for the WordPress profile page.
 */
function ext_profile_admin_js() {
	add_thickbox();
	wp_enqueue_script('ext-profile', plugins_url('extended-profile/preview.js'), array('thickbox'));
}

/**
 * Handle the 'profile' shortcode.
 *
 * @param array $attr attributes passed to the shortcode
 * @param string $content content passed to the shortcode.  This should 
 *                        include the name or ID of the user to print the 
 *                        profile for.
 * @return string microformatted profile
 */
function ext_profile_shortcode($attr, $content) {
	return get_extended_profile($content);
}


/**
 * Fetch hCard for the specified URL.
 *
 * @param string $url URL to get hCard from
 * @return array array containing the hCard object (key: 'hcard') as well as the raw XML (key: 'xml')
 */
function ext_profile_hcard_from_url($url) {
	global $hkit;
	require_once dirname(__FILE__).'/hkit.class.php';
	if(function_exists('tidy_clean_repair'))
		$page = wp_remote_fopen($url);
	else
		$page = wp_remote_fopen('http://cgi.w3.org/cgi-bin/tidy?forceXML=on&docAddr='.urlencode($url));
	if(function_exists('tidy_clean_repair'))
		$page = tidy_clear_repair($page);
	$page = str_replace('&nbsp;','&#160;',$page);
	if(!$hkit) $hkit = new hKit;
	@$hcard = $hkit->getByString('hcard', $page);
	if(count($hcard['preferred'])) {
		$phcard = $hcard['preferred'][0];
	} else {
		if($hcard['all']) {
			foreach($hcard['all'] as $card) {
				if($card['uid'] == $url) { $phcard = $card; break; }
				if(!is_array($card['url']) && $card['url'] == $url) { $phcard = $card; break; }
				if(is_array($card['url']) && in_array($url,$card['url'])) { $phcard = $card; break; }
			}//end foreach all
			if(!$phcard) $phcard = $hcard['all'][0];
		}//end if hcard all
	}//end if-else preferred
	return array('hcard' => $phcard, 'xml' => $hcard['xml']);
}


/**
 * Fetch the hCard from the URL specified in the user's profile.  Use the 
 * hCard profile data to update the user's local WordPress profile.
 *
 * @param int $userid ID of user to update
 * @param bool $override should local attributes be overwritten with data from 
 *                       hCard.  If false, only empty fields in the local 
 *                       profile will be updated from the hCard.
 */
function ext_profile_hcard_import($userid, $override=false) {
	$userdata = get_userdata($userid);

	//GET HCARD
	$data = ext_profile_hcard_from_url($userdata->user_url);
	if((!$data['hcard'] || !count($data['hcard'])) && $data['xml']) {//if no hcard, follow rel=me
		$relme = $data['xml']->xpath("//*[contains(concat(' ',normalize-space(@rel),' '),' me ')]");
		foreach($relme as $tag) {
			if(substr($tag['href'],0,4) != 'http') {
				$domain = explode('/',$url);
				$domain = $domain[2];
				if(substr($tag['href'],0,1) == '/')
					$tag['href'] = 'http://'.$domain.$tag['href'];
				else
					$tag['href'] = dirname($url).'/'.$tag['href'];
			}//end if not http
			$data = ext_profile_hcard_from_url($tag['href']);
			if($data['hcard'] && count($data['hcard'])) break;
		}//end foreach
	}//end if ! hcard

	$phcard = $data['hcard'];

	if(substr($phcard['photo'],0,3) == '://') {//photo relative URL
		$photo = substr($phcard['photo'],3);
		$domain = explode('/',$userdata->user_url);
		$domain = $domain[2];
		if($photo{0} == '/')
			$photo = 'http://'.$domain.$photo;
		else
			$photo = dirname($userdata->user_url).'/'.$photo;
		$phcard['photo'] = $photo;
	}//end if photo{0-3} == ://

	//IMPORT INTO PROFILE
	/* MAP KNOWN VALUES */
	if(!is_array($phcard['n'])) $phcard['n'] = array();
	if($phcard['fn']) update_usermeta($userid, 'display_name', $phcard['fn']);
	if($phcard['nickname']) update_usermeta($userid, 'nickname', $phcard['nickname']);
	if(($override || !$userdata->first_name) && $phcard['n']['given-name']) update_usermeta($userid, 'first_name', $phcard['n']['given-name']);
	if(($override || !$userdata->additional_name) && $phcard['n']['additional-name']) update_usermeta($userid, 'additional_name', $phcard['n']['additional-name']);
	if(($override || !$userdata->last_name) && $phcard['n']['family-name']) update_usermeta($userid, 'last_name', $phcard['n']['family-name']);
	if(($override || !$userdata->description) && $phcard['note']) update_usermeta($userid, 'description', $phcard['note']);
	if(($override || !$userdata->user_email) && $phcard['email']) update_usermeta($userid, 'user_email', $phcard['email']);
	/* MAP ALL OTHER HCARD VALUES TO THEMSELVES */
	$phcard['urls'] = $phcard['url']; unset($phcard['url']);
	foreach($phcard as $key => $val)
		if($key && $val && ($override || !$userdata->$key)) update_usermeta($userid, $key, $val);
}


/**
 * Extend the WordPress profile page to include the additional fields.
 */
function ext_profile_fields() {
	global $profileuser;
	$userdata = $profileuser;

	//PHOTO
	echo '<h3>Photo</h3>';
	echo '<table class="form-table">';
		echo '	<tr><th><label for="photo">Photo URL</label></th><td>'; 
	if($userdata->photo)
		echo'<a href="' . clean_url($userdata->photo) . '"><img src="' . clean_url($userdata->photo) . '" alt="Avatar" class="photo" style="float: right; max-height: 200px" /></a>';
	echo '<input type="text" id="photo" name="hcard[photo]" value="' . attribute_escape($userdata->photo) . '" onchange="preview_hcard();" />';
	echo '</td></tr></table>';

	//ORGANIZATION AND ADDRESS
	$fieldset = array(
			'Miscellaneous' => array(
				'additional_name' => 'Middle Name(s)',
				'org' => 'Organization',
			),
			'Address' => array(
				'street_address' => 'Street Address',
				'locality' => 'City',
				'region' => 'Province/State',
				'postal_code' => 'Postal Code',
				'country_name' => 'Country',
				'tel' => 'Telephone Number',
			),
		);
	foreach($fieldset as $legend => $fields) {
		echo '<h3>'.$legend.'</h3>';
		echo '<table class="form-table">';
		if($legend == 'Miscellaneous') {
			$urls = is_array($userdata->additional_urls) ? $userdata->additional_urls : array();
			echo '	<tr><th><label for="additional_urls">Additional Website(s)<br />(one per line)</label></th> <td><textarea id="additional_urls" name="additional_urls">';
			foreach ($urls as $url) {
				echo clean_url($url) . "\n";
			}
			echo '</textarea></td></tr>';
		}//end if Miscellaneous
		foreach($fields as $key => $label)
			echo '	<tr><th><label for="'.$key.'">'.$label.'</label></th> <td><input type="text" id="'.$key.'" name="hcard['.$key.']" value="'.@$userdata->$key.'" /></td></tr>';
		echo '</table>';
	}//end foreach fieldset
?>

	<div id="profile_preview" style="display: none">
		<h1>Profile Preview</h1>
		<p>This is how your profile looks to people allowed to see all the information.</p>
		<hr />
		<div id="hcard-preview"></div>
	</div>

	<p><a id="profile_preview_link" href="#">Preview Profile</a></p>
	<a id="profile_thickbox" href="#TB_inline?height=600&width=800&inlineId=profile_preview" class="thickbox"></a>

	<script type="text/javascript">
		jQuery(function() {
			jQuery("#hcard_link").click(function() {
				jQuery("#do_manual_hcard").val("1");
				jQuery("input[type=submit]").click();
			});

			jQuery('#profile_preview_link').click(function() {
				preview_hcard();
				jQuery('#profile_thickbox').click();
				return false;
			});
		});

	</script>
<?php
}


/**
 * Include a link at the top of the WordPress profile page to import hCard data.
 */
function ext_profile_personal_options() {
	echo '<p><input type="hidden" id="do_manual_hcard" name="do_manual_hcard" /><a href="#" id="hcard_link">Import hCard</a></p>';
}


/**
 * Save extended profile attributes.
 *
 * @param int $userid ID of user
 * @uses apply_filters() Calls 'pre_ext_profile_$name' before saving each profile attribute.
 */
function ext_profile_update($userid) {
	if($_POST['do_manual_hcard']) {
		ext_profile_hcard_import($userid, true);
	} else {
		$additional_urls = preg_split('/[\s]+/',$_POST['additional_urls']);
		$urls = array();
		foreach ($additional_urls as $u) {
			$urls[] = apply_filters('pre_ext_profile_url', $u);
		}
		update_usermeta($userid, 'additional_urls', array_filter($urls));

		if (is_array($_POST['hcard'])) {
			foreach($_POST['hcard'] as $key => $value) {
				$value = apply_filters("pre_ext_profile_$key", $value);
				update_usermeta($userid, $key, $value);
			}
		}
	}
}


/**
 * Get the microformatted profile for the specified user.
 *
 * @param mixed $userid username or ID of user to get profile for.  If not 
 *                      specified, the queried author will be used.
 * @param bool $echo should profile be echo()'ed
 * @param bool $actionstream_aware should profile exclude actionstream URLs
 * @return string microformatted profile
 * @uses do_action() Calls 'extended_profile' to build the user profile
 * @uses apply_filters() Calls 'post_extended_profile' after building the entire profile, but before returning it.
 * @access private
 */
function get_extended_profile($userid, $actionstream_aware=false) {

	// ensure plugin doesn't break in the absence of the permissions plugin
	if (!function_exists('diso_user_is')) { function diso_user_is() { return true; } }

	// Use queried author if userid not provided
	if (empty($userid) && is_author()) {
		global $wp_query;
		$user = $wp_query->get_queried_object();
		$userid = $user->ID;
	}

	// Get user data 
	if(is_numeric($userid))
		$userdata = get_userdata($userid);
	else
		$userdata = get_userdatabylogin($userid);
	if (!$userdata) return;

	$time = microtime(true);

	// Build profile
	ob_start();
	do_action('extended_profile', $userdata->ID, $actionstream_aware);
	$profile = ob_get_contents();
	ob_end_clean();

	$profile = '<div class="vcard hcard-profile">' . $profile . '</div>';
	$profile = apply_filters('post_extended_profile', $profile, $userdata->ID);

	if (defined('WP_DEBUG') && WP_DEBUG) error_log('extended-profile build time = ' . (microtime(true) - $time) . ' seconds');
	return $profile;
}


/**
 * Print the microformatted photo for the user.
 *
 * @param int $userid ID of user to get profile information for.
 * @uses apply_filters() Calls 'extended_profile_photo' after generating the photo markup
 * @access private
 */
function extended_profile_photo($userid) {
	$userdata = get_userdata($userid);

	if (@$userdata->photo && diso_user_is(@$userdata->profile_permissions['photo'])) {
		$photo = '<img class="photo" alt="photo" src="' . clean_url($userdata->photo) . '" />';
		$photo = apply_filters('extended_profile_photo', $photo, $userdata->ID);
		if ($photo) echo $photo . "\n";
	}
}


/**
 * Print the microformatted name for the user.
 *
 * @param int $userid ID of user to get profile information for.
 * @uses apply_filters() Calls 'extended_profile_{field}' after generating the
 *                       markup for each of the following individual fields 
 *                       (last_name, first_name, additional_name, fn)
 * @uses apply_filters() Calls 'extended_profile_{field}' after generating the
 *                       markup for each of the following combined fields 
 *                       (n, name)
 * @access private
 */
function extended_profile_name($userid) {
	$userdata = get_userdata($userid);

	// N (individual name components)
	$n = '';

	if (@$userdata->last_name && diso_user_is(@$userdata->profile_permissions['family-name'])) {
		$last_name = '<span class="family-name">' . $userdata->last_name . '</span>';
		$last_name = apply_filters('extended_profile_last_name', $last_name, $userdata->ID);
		if ($last_name) $n .= $last_name . ', ';
	}

	if (@$userdata->first_name && diso_user_is(@$userdata->profile_permissions['given-name'])) {
		$first_name = '<span class="given-name">' . $userdata->first_name . '</span>';
		$first_name = apply_filters('extended_profile_first_name', $first_name, $userdata->ID);
		if ($first_name) $n .= $first_name . ' ';
	}

	if (@$userdata->additional_name && diso_user_is(@$userdata->profile_permissions['additional-name'])) {
		$additional_name = '<span class="additional-name">' . $userdata->additional_name . '</span>';
		$additional_name = apply_filters('extended_profile_additional_name', $additional_name, $userdata->ID);
		if ($additional_name) $n .= $additional_name;
	}

	if ($n) $n = '<span class="n">' . $n . '</span>';
	$n = apply_filters('extended_profile_n', $n, $userdata->ID);
	
	// FN (formatted name)
	$fn = $userdata->display_name;

	if (!@$additional_name) {
		// if display_name is equal to the un-marked-up name concatenation, then just use the marked-up concatenation
		// checking for the trimmed concatenation covers the use case where the user profile only has one name
		if ($fn == trim(strip_tags($first_name . ' ' . $last_name))) {
			$fn = trim($first_name . ' ' . $last_name);
			$n = '';
		} else if ($fn == strip_tags($userdata->last_name . ' ' . $userdata->first_name)) {
			$fn = $last_name . ' ' . $first_name;
			$n = '';
		}
	}

	$fn = '<h2 class="fn">' . $fn . '</h2>';
	$fn = apply_filters('extended_profile_fn', $fn, $userdata->ID);

	$name = '';
	if ($fn) $name .= $fn;
	if ($n) $name .= $n;
	$name = apply_filters('extended_profile_name', $name, $userdata->ID);

	if ($name) echo $name . "\n";
}


/**
 * Print the microformatted nickname for the user.
 *
 * @param int $userid ID of user to get profile information for.
 * @uses apply_filters() Calls 'extended_profile_nickname' after generating the nickname markup
 * @access private
 */
function extended_profile_nickname($userid) {
	$userdata = get_userdata($userid);

	if (@$userdata->nickname && diso_user_is(@$userdata->profile_permissions['nickname'])) {
		$nickname = '<span class="nickname">' . $userdata->nickname . '</span>';
		$nickname = apply_filters('extended_profile_nickname', $nickname, $userdata->ID);
		if ($nickname) echo $nickname . "\n";
	}
}


/**
 * Print the microformatted org for the user.
 *
 * @param int $userid ID of user to get profile information for.
 * @uses apply_filters() Calls 'extended_profile_org' after generating the org markup
 * @access private
 */
function extended_profile_org($userid) {
	$userdata = get_userdata($userid);

	if (@$userdata->org && diso_user_is(@$userdata->profile_permissions['org'])) {
		$org = '<span class="org">' . $userdata->org . '</span>';
		$org = apply_filters('extended_profile_org', $org, $userdata->ID);
		if ($org) echo $org . "\n";
	}
}


/**
 * Print the microformatted note for the user.
 *
 * @param int $userid ID of user to get profile information for.
 * @uses apply_filters() Calls 'extended_profile_note' after generating the note markup
 * @access private
 */
function extended_profile_note($userid) {
	$userdata = get_userdata($userid);

	if (@$userdata->description && diso_user_is(@$userdata->profile_permissions['note'])) {
		$note = '<p class="note">' . $userdata->description . '</p>';
		$note = apply_filters('extended_profile_note', $note, $userdata->ID);
		if ($note) echo $note . "\n";
	}
}


/**
 * Print the microformatted contact information for the user.
 *
 * @param int $userid ID of user to get profile information for.
 * @uses apply_filters() Calls 'extended_profile_{field}' after generating the
 *                       markup for each of the following individual fields 
 *                       (urls, aim, yim, jabber, email, tel)
 *                       locality, region, postal_code, country_name
 * @uses apply_filters() Calls 'extended_profile_{field}' after generating the
 *                       markup for each of the following combined fields 
 *                       (adr, contact)
 * @access private
 */
function extended_profile_contact($userid, $actionstream_aware) {
	$userdata = get_userdata($userid);

	$contact = '';

	// URLs
	$user_urls = array_merge(array($userdata->user_url), @$userdata->additional_urls);
	$user_urls = array_unique(array_filter($user_urls));
	if (count($user_urls) && diso_user_is(@$userdata->profile_permissions['urls'])) {
		if ($actionstream_aware && function_exists('actionstream_services')) {
			$actionstream = actionstream_services($userdata->ID, true);
		}

		$urls = '';
		foreach($user_urls as $url) {
			// treat primary website special
			if ($url == $userdata->user_url) {
				$urls .= '<li><a class="url uid" rel="me" href="' . clean_url($url) . '">' . ext_profile_display_url($url) . '</a></li>';
				continue;
			}

			if($actionstream_aware && in_array($url,$actionstream)) continue;

			$urls .= '<li><a class="url" rel="me" href="' . clean_url($url) . '">' . ext_profile_display_url($url) . '</a></li>';
		}

		if ($urls) {
			$urls = '<dt>On the web:</dt> <dd><ul>' . $urls . '</ul></dd>';
		}

		$urls = apply_filters('extended_profile_urls', $urls, $userdata->ID);
		if ($urls) $contact .= $urls . "\n";
	}

	// AIM
	if (@$userdata->aim && diso_user_is(@$userdata->profile_permissions['aim'])) {
		$aim = '<dt>AIM:</dt> <dd><a class="url" href="' . clean_url("aim:goim?screenname=$userdata->aim", array('aim')) . '">' . $userdata->aim . '</a></dd>';
		$aim = apply_filters('extended_profile_aim', $aim, $userdata->ID);
		if ($aim) $contact .= $aim . "\n";
	}

	// YIM
	if (@$userdata->yim && diso_user_is(@$userdata->profile_permissions['yim'])) {
		$yim = '<dt>Y!IM:</dt> <dd><a class="url" href="' . clean_url("ymsgr:sendIM?$userdata->yim", array('ymsgr')) . '">' . $userdata->yim . '</a></dd>';
		$yim = apply_filters('extended_profile_yim', $yim, $userdata->ID);
		if ($yim) $contact .= $yim . "\n";
	}

	// Jabber
	if (@$userdata->jabber && diso_user_is(@$userdata->profile_permissions['jabber'])) {
		$jabber = '<dt>Jabber:</dt> <dd><a class="url" href="' . clean_url("xmpp:$userdata->jabber", array('xmpp')) . '">' . $userdata->jabber . '</a></dd>';
		$jabber = apply_filters('extended_profile_jabber', $jabber, $userdata->ID);
		if ($jabber) $contact .= $jabber . "\n";
	}

	// Email
	if ($userdata->user_email && diso_user_is(@$userdata->profile_permissions['email'])) {
		$email = '<dt>Email:</dt> <dd><a class="email" href="' . clean_url("mailto:$userdata->user_email") . '">' . $userdata->user_email . '</a></dd>';
		$email = apply_filters('extended_profile_email', $email, $userdata->ID);
		if ($email) $contact .= $email . "\n";
	}

	// Telephone
	if (@$userdata->tel && diso_user_is(@$userdata->profile_permissions['tel'])) {
		$tel = '<dt>Telephone:</dt> <dd class="tel">' . $userdata->tel . '</dd>';
		$tel = apply_filters('extended_profile_tel', $tel, $userdata->ID);
		if ($tel) $contact .= $tel . "\n";
	}


	// Address
	$adr = '';

	if (@$userdata->street_address && diso_user_is(@$userdata->profile_permissions['street-address'])) 
		$adr .= '<div class="street-address">' . $userdata->street_address . '</div>'."\n";

	if (@$userdata->locality && diso_user_is(@$userdata->profile_permissions['locality'])) 
		$adr .= '<span class="locality">' . $userdata->locality . '</span>,'."\n";

	if (@$userdata->region && diso_user_is(@$userdata->profile_permissions['region'])) 
		$adr .= '<span class="region">' . $userdata->region . '</span>'."\n";

	if (@$userdata->postal_code && diso_user_is(@$userdata->profile_permissions['postal-code'])) 
		$adr .= '<div class="postal-code">' . $userdata->postal_code . '</div>'."\n";

	if (@$userdata->country_name && diso_user_is(@$userdata->profile_permissions['country-name'])) 
		$adr .= '<div class="country-name">' . $userdata->country_name . '</div>'."\n";

	if ($adr) {
		$adr = '<dt>Current Address:</dt> <dd class="adr">' . $adr . '</dd>';
	}
	$adr = apply_filters('extended_profile_adr', $adr, $userdata->ID);
	if ($adr) $contact .= $adr;

	// Finish up
	if ($contact) {
		$contact = '<h3>Contact Information</h3> <dl class="contact">' . $contact . '</dl>';
	}
	$contact = apply_filters('extended_profile_contact', $contact, $userdata->ID);
	if ($contact) echo $contact;
}


/**
 * Cleanup URL for display in profile.  The URL scheme is hidden, along with 
 * the preceding 'www.' portion of the hostname if present.  If the URL path 
 * includes only '/', it is hidden.
 *
 * @param string $url URL to be cleaned up for display
 * @return string the cleaned up URL
 */
function ext_profile_display_url($url) {
	$parts = parse_url($url);

	$url = preg_replace('/^www\./', '', $parts['host']);
	if ($parts['path'] != '/') $url .= $parts['path'];

	return $url;
}


/**
 * Provide value of 'country' SREG attribute.
 *
 * @param string $value current value for attribute
 * @param id $user_id ID of user to get attribute for
 * @return string new value for attribute
 */
function ext_profile_openid_sreg_country($value, $user_id) {
	$country = get_usermeta($user_id, 'country_name');
	return $country ? $country : $value;
}


/**
 * Provide value of 'postcode' SREG attribute.
 *
 * @param string $value current value for attribute
 * @param id $user_id ID of user to get attribute for
 * @return string new value for attribute
 */
function ext_profile_openid_sreg_postcode($value, $user_id) {
	$postcode = get_usermeta($user_id, 'postal_code');
	return $postcode ? $postcode : $value;
}


/**
 * Migrate old widget data to new format.
 */
function ext_profile_migrate_widget_data() {
	$migrate = false;
	$sidebars = get_option('sidebars_widgets');

	foreach ($sidebars as $name => $widgets) {
		for($i=0; $i<sizeof($widgets); $i++) {
			if ($widgets[$i] == 'diso-profile') {
				$sidebars[$name][$i] = 'user-profile';
				$migrate = true;
			}
		}
	}

	if ($migrate) {
		update_option('sidebars_widgets', $sidebars);

		$options = get_option('widget_diso_profile');
		delete_option('widget_diso_profile');

		if ($options) {
			$options['user'] = $options['userid'];
			unset($options['userid']);
			update_option('widget_user_profile', $options);
		}
	}
}


/**
 * Migrate old user data for all users.
 */
function ext_profile_migrate_profile_data() {
	$users = get_users_of_blog();

	foreach ($users as $user) {
		update_usermeta($user->user_id, 'postal_code', get_usermeta($user->user_id, 'postalcode'));
		delete_usermeta($user->user_id, 'postalcode');

		update_usermeta($user->user_id, 'street_address', get_usermeta($user->user_id, 'streetaddress'));
		delete_usermeta($user->user_id, 'streetaddress');

		update_usermeta($user->user_id, 'country_name', get_usermeta($user->user_id, 'countryname'));
		delete_usermeta($user->user_id, 'countryname');

		update_usermeta($user->user_id, 'additional_urls', get_usermeta($user->user_id, 'urls'));
		delete_usermeta($user->user_id, 'urls');

		$n = get_usermeta($user->user_id, 'n');
		update_usermeta($user->user_id, 'additional_name', @$n['additional_name']);
		delete_usermeta($user->user_id, 'n');

		delete_usermeta($user->user_id, 'fn');
		delete_usermeta($user->user_id, 'user_url');
		delete_usermeta($user->user_id, 'display_name');
	}
}
?>
