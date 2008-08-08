<?php
/*
Plugin Name: DiSo Profile
Plugin URI: http://singpolyma.net/plugins/hcard-import/
Description: Detect and import hCard data on new user, extended data for user profiles, easy hCard generation
Version: 0.2
Author: Stephen Paul Weber
Author URI: http://singpolyma.net/
*/

//Licensed under an MIT-style licence

require_once dirname(__FILE__).'/recent-visitors.php';
require_once dirname(__FILE__).'/permissions.php';

$hkit;
function diso_profile_hcard_from_url($url) {
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
				if($card['uid'] == $userdata->user_url) { $phcard = $card; break; }
				if(!is_array($card['url']) && $card['url'] == $url) { $phcard = $card; break; }
				if(is_array($card['url']) && in_array($url,$card['url'])) { $phcard = $card; break; }
			}//end foreach all
			if(!$phcard) $phcard = $hcard['all'][0];
		}//end if hcard all
	}//end if-else preferred
	return array('hcard' => $phcard, 'xml' => $hcard['xml']);
}//end function diso_profile_hcard_from_url

function diso_profile_hcard_import($userid,$override=false) {
	$userdata = get_userdata($userid);

	//GET HCARD
	$data = diso_profile_hcard_from_url($userdata->user_url);
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
			$data = diso_profile_hcard_from_url($tag['href']);
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
}//end function diso_profile_hcard_import
add_action('user_register', 'diso_profile_hcard_import');

function diso_profile_extend() {
	global $profileuser;
	$userdata = $profileuser;
	//PHOTO
	echo '<fieldset>';
	echo '	<legend>Photo</legend>';
	if($userdata->photo)
		echo '	<img src="'.$userdata->photo.'" alt="Avatar" class="photo" />';
	else
		echo '	You currently have no photo set.';
	echo '	<br /><label for="photo">Enter a URL to change your photo:</label><br /> <input type="text" id="photo" name="hcard[photo]" value="'.htmlentities($userdata->photo).'" onchange="preview_hcard();" />';
	echo '</fieldset>';

	//ORGANIZATION AND ADDRESS
	$fieldset = array(
			'Miscellaneous' => array(
				'org' => 'Organization',
			),
			'Address' => array(
				'streetaddress' => 'Street Address',
				'locality' => 'City',
				'region' => 'Province/State',
				'postalcode' => 'Postal Code',
				'countryname' => 'Country',
				'tel' => 'Telephone Number',
			),
		);
	foreach($fieldset as $legend => $fields) {
		echo '<fieldset>';
		echo '	<legend>'.$legend.'</legend>';
		if($legend == 'Miscellaneous') {
			echo '	<label for="additional-name">Middle Name(s)</label> <input type="text" id="additional-name" name="additional-name" value="'.$userdata->n['additional-name'].'" onkeyup="preview_hcard();" />';
			if(!count($userdata->urls)) $userdata->urls = array($userdata->user_url);
			if(!is_array($userdata->urls) || !count($userdata->urls)) $userdata->urls = array($userdata->user_url);
			echo '	<label for="urls">Website(s) (one per line)</label> <textarea id="urls" name="urls" onkeyup="preview_hcard();">'.htmlentities(implode("\n",$userdata->urls)).'</textarea>';
		}//end if Miscellaneous
		foreach($fields as $key => $label)
			echo '	<label for="'.$key.'">'.$label.'</label> <input type="text" id="'.$key.'" name="hcard['.$key.']" value="'.$userdata->$key.'" onkeyup="preview_hcard();" />';
		echo '</fieldset>';
	}//end foreach fieldset
?>
	<fieldset style="clear:both;width:90%;">
		<legend>Preview</legend>
		<p>(This is how your profile looks to people allowed to see all the information.)</p>
		<div id="hcard-preview"></div>
		<script type="text/javascript">
		//<![CDATA[
			function preview_hcard() {
				var template = '<div class="vcard diso diso-profile">';
				if(document.getElementById('photo').value) template += '<img class="photo" alt="photo" src="'+document.getElementById('photo').value+'" />\n';
				template += '<h2 class="fn">'+document.getElementById('display_name').value+'</h2>';
				if( document.getElementById('first_name').value || document.getElementById('additional-name').value || document.getElementById('last_name').value ) {
					if(document.getElementById('url').value)
						template += '<a class="url uid" rel="me" href="'+document.getElementById('url').value+'">';
					else
						template += '<span class="n">';
					if(document.getElementById('last_name').value) template += '<span class="family-name">'+document.getElementById('last_name').value+'</span>,\n';
					if(document.getElementById('first_name').value) template += '<span class="given-name">'+document.getElementById('first_name').value+'</span>\n';
					if(document.getElementById('additional-name').value) template += '<span class="additional-name">'+document.getElementById('additional-name').value+'</span>\n';
					if(document.getElementById('url').value)
						template += '</a>';
					else
						template += '</span>';
				}//end if name
				if(document.getElementById('nickname').value) template += '"<span class="nickname">'+document.getElementById('nickname').value+'</span>"\n';
				if(document.getElementById('org').value) template += '(<span class="org">'+document.getElementById('org').value+'</span>)\n';
				if(document.getElementById('description').value) template += '<p class="note">'+document.getElementById('description').value+'</p>\n';
				
				template += '<h3>Contact Information</h3>';
				template += '<dl class="contact">';
				if(document.getElementById('urls').value) {
					var urls = document.getElementById('urls').value.split(/[\s]+/);
					template += '<dt>On the web:</dt> <dd> <ul>';
					for(var i in urls)
						template += '<li><a class="url" rel="me" href="'+urls[i]+'">'+urls[i]+'</a></li>';
					template += '</ul> </dd>\n';
				}//end if urls
				if(document.getElementById('aim').value) template += '<dt>AIM:</dt> <dd><a class="url" href="aim:goim?screenname='+document.getElementById('aim').value+'">'+document.getElementById('aim').value+'</a></dd>\n';
				if(document.getElementById('yim').value) template += '<dt>Y!IM:</dt> <dd><a class="url" href="ymsgr:sendIM?'+document.getElementById('yim').value+'">'+document.getElementById('yim').value+'</a></dd>\n';
				if(document.getElementById('jabber').value) template += '<dt>Jabber:</dt> <dd><a class="url" href="xmpp:'+document.getElementById('jabber').value+'">'+document.getElementById('jabber').value+'</a></dd>\n';
				if(document.getElementById('email').value) template += '<dt>Email:</dt> <dd><a class="email" href="mailto:'+document.getElementById('email').value+'">'+document.getElementById('email').value+'</a></dd>\n';
				if(document.getElementById('tel').value) template += '<dt>Telephone:</dt> <dd class="tel">'+document.getElementById('tel').value+'</dd>\n';
				if( document.getElementById('streetaddress').value || document.getElementById('locality').value || document.getElementById('region').value || document.getElementById('postalcode').value || document.getElementById('countryname').value ) {
					template += '<dt>Current Address:</dt> <dd class="adr">';
					if(document.getElementById('streetaddress').value) template += '<div class="street-address">'+document.getElementById('streetaddress').value+'</div>\n';
					if(document.getElementById('locality').value) template += '<span class="locality">'+document.getElementById('locality').value+'</span>,\n';
					if(document.getElementById('region').value) template += '<span class="region">'+document.getElementById('region').value+'</span>\n';
					if(document.getElementById('postalcode').value) template += '<div class="postal-code">'+document.getElementById('postalcode').value+'</div>\n';
					if(document.getElementById('countryname').value) template += '<div class="country-name">'+document.getElementById('countryname').value+'</div>\n';
					template += '</dd>';
				}//end if adr
				template += '</dl>';
				template += '</div>';

				document.getElementById('hcard-preview').innerHTML = template;
			}//end preview_hcard
			preview_hcard();
			if(typeof(document.getElementById('first_name').addEventListener) == 'function') {
				document.getElementById('first_name').addEventListener('keyup',preview_hcard,false);
				document.getElementById('last_name').addEventListener('keyup',preview_hcard,false);
				document.getElementById('nickname').addEventListener('keyup',preview_hcard,false);
				document.getElementById('display_name').addEventListener('change',preview_hcard,false);
				document.getElementById('url').addEventListener('keyup',preview_hcard,false);
				document.getElementById('aim').addEventListener('keyup',preview_hcard,false);
				document.getElementById('yim').addEventListener('keyup',preview_hcard,false);
				document.getElementById('jabber').addEventListener('keyup',preview_hcard,false);
				document.getElementById('description').addEventListener('keyup',preview_hcard,false);
				document.getElementById('email').addEventListener('keyup',preview_hcard,false);
			}//end if addEventListener
		//]]>
		</script>
	</fieldset>
<?php

}//end function diso_profile_extend
add_action('show_user_profile', 'diso_profile_extend');
add_action('edit_user_profile', 'diso_profile_extend');

function diso_profile_extend_top() {
	global $profileuser;
	echo '<input type="submit" name="do_manual_hcard" value="Import hCard &raquo;" />';
}//end finction diso_profile_extend_top
add_action('profile_personal_options', 'diso_profile_extend_top');

function diso_profile_extend_save($userid) {
	if(isset($_POST['do_manual_hcard'])) {
		diso_profile_hcard_import($userid, true);
	} else {
		$userdata = get_userdata($userid);
		$n = $userdata->n ? $userdata->n : array();
		$n['given-name'] = $_POST['first_name'];
		$n['additional-name'] = $_POST['additional-name'];
		$n['family-name'] = $_POST['last_name'];
		update_usermeta($userdata->ID, 'n', $n);
		$urls = preg_split('/[\s]+/',$_POST['urls']);
		update_usermeta($userdata->ID, 'urls', $urls);
		if (is_array($_POST['hcard'])) {
			foreach($_POST['hcard'] as $key => $val)
				update_usermeta($userdata->ID, $key, $val);
		}
		update_usermeta($userdata->ID, 'user_url', $_POST['url']);
		update_usermeta($userdata->ID, 'display_name', $_POST['display_name']);
	}//end else
}//end function diso_profile_extend_save
add_action('profile_update', 'diso_profile_extend_save');

function diso_profile($userid='', $echo=true) {
	$time = microtime(true);
	if(!$userid) {//get administrator
		global $wpdb;
		$userid = $wpdb->get_var("SELECT user_id FROM $wpdb->usermeta WHERE meta_key='wp_user_level' AND meta_value='10'");
	}//end if ! userid
	if(is_numeric($userid))
		$userdata = get_userdata($userid);
	else
		$userdata = get_userdatabylogin($userid);
	$template = '<div class="vcard diso diso-profile">';
	if($userdata->photo && diso_user_is($userdata->profile_permissions['photo'])) $template .= '<img class="photo" alt="photo" src="'.htmlentities($userdata->photo).'" />'."\n";
	$template .= '<h2 class="fn">'.htmlentities($userdata->display_name).'</h2>';
	if( $userdata->first_name || $userdata->additional-name || $userdata->last_name ) {
		if($userdata->user_url)
			$template .= '<a class="url uid" rel="me" href="'.htmlentities($userdata->user_url).'">';
		else
			$template .= '<span class="n">';
		if($userdata->last_name && diso_user_is($userdata->profile_permissions['family-name'])) $template .= '<span class="family-name">'.htmlentities($userdata->last_name).'</span>,'."\n";
		if($userdata->first_name && diso_user_is($userdata->profile_permissions['given-name'])) $template .= '<span class="given-name">'.htmlentities($userdata->first_name).'</span>'."\n";
		if($userdata->n['additional-name'] && diso_user_is($userdata->profile_permissions['additional-name'])) $template .= '<span class="additional-name">'.htmlentities($userdata->n['additional-name']).'</span>'."\n";
		if($userdata->user_url)
			$template .= '</a>';
		else
			$template .= '</span>';
	}//end if name
	if($userdata->nickname && diso_user_is($userdata->profile_permissions['nickname'])) $template .= '"<span class="nickname">'.htmlentities($userdata->nickname).'</span>"'."\n";
	if($userdata->org && diso_user_is($userdata->profile_permissions['org'])) $template .= '(<span class="org">'.htmlentities($userdata->org).'</span>)'."\n";
	if($userdata->description && diso_user_is($userdata->profile_permissions['note'])) $template .= '<p class="note">'.htmlentities($userdata->description).'</p>'."\n";

	$template .= '<h3>Contact Information</h3>';
	$template .= '<dl class="contact">';
	if(!count($userdata->urls)) $userdata->urls = array($userdata->user_url);
	if(count($userdata->urls) && diso_user_is($userdata->profile_permissions['urls'])) {
		$template .= '<dt>On the web:</dt> <dd> <ul>';
		foreach($userdata->urls as $url)
			$template .= '<li><a class="url" rel="me" href="'.htmlentities($url).'">'.htmlentities($url).'</a></li>';
		$template .= '</ul> </dd>'."\n";
	}//end if urls
	if($userdata->aim && diso_user_is($userdata->profile_permissions['aim'])) $template .= '<dt>AIM:</dt> <dd><a class="url" href="aim:goim?screenname='.htmlentities($userdata->aim).'">'.htmlentities($userdata->aim).'</a></dd>'."\n";
	if($userdata->yim && diso_user_is($userdata->profile_permissions['yim'])) $template .= '<dt>Y!IM:</dt> <dd><a class="url" href="ymsgr:sendIM?'.htmlentities($userdata->yim).'">'.htmlentities($userdata->yim).'</a></dd>'."\n";
	if($userdata->jabber && diso_user_is($userdata->profile_permissions['jabber'])) $template .= '<dt>Jabber:</dt> <dd><a class="url" href="xmpp:'.htmlentities($userdata->jabber).'">'.htmlentities($userdata->jabber).'</a></dd>'."\n";
	if($userdata->user_email && diso_user_is($userdata->profile_permissions['email'])) $template .= '<dt>Email:</dt> <dd><a class="email" href="mailto:'.htmlentities($userdata->user_email).'">'.htmlentities($userdata->user_email).'</a></dd>'."\n";
	if($userdata->tel && diso_user_is($userdata->profile_permissions['tel'])) $template .= '<dt>Telephone:</dt> <dd class="tel">'.htmlentities($userdata->tel).'</dd>'."\n";
	if( ($userdata->streetaddress || $userdata->locality || $userdata->region || $userdata->postalcode || $userdata->countryname)  &&
	 (diso_user_is($userdata->profile_permissions['street-address']) || diso_user_is($userdata->profile_permissions['locality']) || diso_user_is($userdata->profile_permissions['region']) || diso_user_is($userdata->profile_permissions['postal-code']) || diso_user_is($userdata->profile_permissions['country-name']) ) ) {
		$template .= '<dt>Current Address:</dt> <dd class="adr">';
		if($userdata->streetaddress && diso_user_is($userdata->profile_permissions['street-address'])) $template .= '<div class="street-address">'.htmlentities($userdata->streetaddress).'</div>'."\n";
		if($userdata->locality && diso_user_is($userdata->profile_permissions['locality'])) $template .= '<span class="locality">'.htmlentities($userdata->locality).'</span>,'."\n";
		if($userdata->region && diso_user_is($userdata->profile_permissions['region'])) $template .= '<span class="region">'.htmlentities($userdata->region).'</span>'."\n";
		if($userdata->postalcode && diso_user_is($userdata->profile_permissions['postal-code'])) $template .= '<div class="postal-code">'.htmlentities($userdata->postalcode).'</div>'."\n";
		if($userdata->countryname && diso_user_is($userdata->profile_permissions['country-name'])) $template .= '<div class="country-name">'.htmlentities($userdata->countryname).'</div>'."\n";
		$template .= '</dd>';
	}//end if adr
	$template .= '</dl>';
	$template .= '</div>';
	if($echo) {echo $template; echo '<!-- diso-profile time : '.(microtime(true)-$time).' seconds -->';}
	return $template;
}//end function diso_profile

function diso_profile_head() {
	echo '		<link rel="stylesheet" type="text/css" href="'.get_bloginfo('wpurl').'/wp-content/plugins/wp-diso-profile/profile.css" />'."\n";
}//end function diso_profile_head
add_action('wp_head', 'diso_profile_head');
add_action('admin_head', 'diso_profile_head');

function diso_profile_parse_page_token($content) {
	if(preg_match('/<!--diso_profile[\(]*(.*?)[\)]*-->/',$content,$matches)) {
		$parameter1 = $matches[1];
		$content = preg_replace('/<!--diso_profile(.*?)-->/',diso_profile($parameter1,false), $content);
	}//end if match
	return $content;
}//end function diso_profile_parse_page_token
add_filter('the_content', 'diso_profile_parse_page_token');

?>
