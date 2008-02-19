<?php
/*
Plugin Name: OAuth
Plugin URI: http://singpolyma.net/plugins/oauth/
Description: Enables OAuth services on your Wordpress blog.
Version: 0.12
Author: Stephen Paul Weber
Author URI: http://singpolyma.net/
*/

//Licensed under the GPL

require_once dirname(__FILE__).'/common.inc.php';
require_once dirname(__FILE__).'/../../../wp-includes/pluggable.php';

function oauth_accept() {

	$services = get_option('xrds_services');
	if(!is_array($services)) $services = array();
	$services['OAuth Request Token'] = array(
									'priority' => 10,
									'Type' => 'http://oauth.net/core/1.0/endpoint/request',
									'URI' => get_bloginfo('wpurl').'/wp-content/plugins/oauth/request_token.php'
								);
	$services['OAuth Authorize Token'] = array(
									'priority' => 10,
									'Type' => 'http://oauth.net/core/1.0/endpoint/authorize',
									'URI' => get_bloginfo('wpurl').'/wp-content/plugins/oauth/authorize_token.php'
								);
	$services['OAuth Access Token'] = array(
									'priority' => 10,
									'Type' => 'http://oauth.net/core/1.0/endpoint/access',
									'URI' => get_bloginfo('wpurl').'/wp-content/plugins/oauth/access_token.php'
								);
	$services['OAuth Static Token'] = array(
									'priority' => 5,
									'Type' => 'http://oauth.net/discovery/1.0/consumer-identity/static',
									'oauth:ConsumerKey' => 'DUMMYKEY'
								);
	$services['OAuth Dynamic Token'] = array(
									'priority' => 10,
									'Type' => 'http://oauth.net/discovery/1.0/consumer-identity/dynamic',
									'URI' => get_bloginfo('wpurl').'/wp-content/plugins/oauth/new_consumer.php',
									'oauth:HttpMethod' => 'GET',
									'oauth:CustomParameters' => array('raw' => "\t\t\t".'<oauth:Parameter source="http://oauth.net/example/consumer_identity">description</oauth:Parameter>'."\n")
								);
	$services['Wordpress Comment Post'] = array(
									'priority' => 10,
									'Type' => 'http://wordpress.org/comment',
									'URI' => get_bloginfo('wpurl').'/wp-comments-post.php'
								);
	update_option('xrds_services', $services);

	$services = get_option('oauth_services');
	$services['Post Comments'] = array('wp-comments-post.php');
	update_option('oauth_services', $services);

	$store = new OAuthWordpressStore();
	$server = new OAuthServer($store);
	$sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
	$plaintext_method = new OAuthSignatureMethod_PLAINTEXT();
	$server->add_signature_method($sha1_method);
	$server->add_signature_method($plaintext_method);

	try {
		$req = OAuthRequest::from_request();
		list($consumer, $token) = $server->verify_request($req);
		$userid = $store->user_from_token($consumer->key, $token->key);
		$authed = get_usermeta($userid, 'oauth_consumers');
		$authed = $authed[$consumer->key];
		if($authed && $authed['authorized']) {
			$allowed = false;
			foreach($authed as $ends)
				if(is_array($ends))
					foreach($ends as $end)
						if(strstr($_SERVER['SCRIPT_URI'], $end))
							$allowed = true;
			if($allowed)
				set_current_user($userid);
		}//end if
	} catch (OAuthException $e) {/* We may not be doing OAuth at all.  */}

	if(strstr($_SERVER['SCRIPT_URI'],'wp-comments-post.php') && $_POST['url']) {
		$slug = array_reverse(explode('/',$_POST['url']));
		if(!$slug[0]) array_shift($slug);
		$slug = $slug[0];
		global $wpdb;
		$_POST['comment_post_ID'] = intval($wpdb->get_var("SELECT id FROM ".$wpdb->posts." WHERE post_name='".$wpdb->escape($slug)."'"));
		unset($_POST['url']);
	}//end wp-comments-post.php hack

}//end function oauth_accept
if(!$NO_oauth)
	oauth_accept();

function oauth_page() {
	global $wpdb;
	if($_POST['new_consumer']) {
		$store = new OAuthWordpressStore();
		$store->new_consumer($_POST['new_consumer']);
		echo '<div id="message" class="updated fade"><strong><p>New Consumer pair generated.</p></strong></div>';
	}//end if new consumer
	echo '<div class="wrap">';
	echo '<h2>OAuth Consumers</h2>';
	$consumers = $wpdb->get_results("SELECT description, consumer_key, secret FROM {$wpdb->prefix}oauth_consumers", ARRAY_A);
	echo '<ul>';
	foreach($consumers as $consumer) {
		echo '	<li><b>'.($consumer['description'] ? $consumer['description'] : 'Oauth Consumer').'</b>';
		echo '		<dl>';
		echo '			<dt>consumer_key</dt>';
		echo '				<dd>'.$consumer['consumer_key'].'</dd>';
		echo '			<dt>secret</dt>';
		echo '				<dd>'.$consumer['secret'].'</dd>';
		echo '		</dl>';
		echo '	</li>';
		$aconsumer = $consumer;//for test link
	}//end foreach consumers
	echo '</ul>';
	echo '<h3>Add OAuth Consumer</h3>';
	echo '<form method="post" action=""><div>';
	echo '<input type="text" name="new_consumer" />';
	echo '<input type="submit" value="Create Key/Secret Pair">';
	echo '</div></form>';
	echo '<h3>Endpoints</h3>';
	echo '<dl>';
	echo '	<dt>Request token endpoint</dt>';
	echo '		<dd>'.get_bloginfo('wpurl').'/wp-content/plugins/oauth/request_token.php</dd>';
	echo '	<dt>Authorize token endpoint</dt>';
	echo '		<dd>'.get_bloginfo('wpurl').'/wp-content/plugins/oauth/authorize_token.php</dd>';
	echo '	<dt>Access token endpoint</dt>';
	echo '		<dd>'.get_bloginfo('wpurl').'/wp-content/plugins/oauth/access_token.php</dd>';
	echo '</dl>';
	$anid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' ORDER BY post_date DESC LIMIT 1");
	echo '<a href="http://singpolyma.net/oauth/example/wp_auto_client3.php?'
.'&amp;blog='.urlencode(get_bloginfo('home').'/')
.'&amp;api_endpoint='.urlencode(get_bloginfo('wpurl').'/wp-comments-post.php')
.'&amp;post_id='.$anid
.'">Click here for a test page &raquo;</a>';
}//end function oauth_page

function oauth_tab($s) {
	add_submenu_page('options-general.php', 'OAuth', 'OAuth', 1, __FILE__, 'oauth_page');
	return $s;
}//end function
add_action('admin_menu', 'oauth_tab');

function oauth_services_render() {
	global $userdata;
	get_currentuserinfo();
	$services = get_option('oauth_services');

	if($_POST['save']) {
		$userdata->oauth_consumers = array();
		if(!$_POST['services']) $_POST['services'] = array();
		foreach($_POST['services'] as $key => $value) {
			$service = array('authorized' => true);
			foreach($services as $k => $v)
				if(in_array($k, array_keys($value)))
					$service[$k] = $v;
			$userdata->oauth_consumers[$key] = $service;
		}//end foreach services
		update_usermeta($userdata->ID, 'oauth_consumers', $userdata->oauth_consumers);
	}//end if save

	require_once dirname(__FILE__).'/OAuthWordpressStore.php';
	$store = new OAuthWordpressStore();
	echo '<div class="wrap">';
	echo '	<h2>Change Service Permissions</h2>';
	echo '	<form method="post" action="">';
	foreach($userdata->oauth_consumers as $key => $values) {
		echo '		<h3>'.$store->lookup_consumer_description($key).'</h3><ul>';
		foreach($services as $k => $v)
			echo '			<li><input type="checkbox" '.($values[$k] && count($values[$k]) ? 'checked="checked"' : '').' name="services['.htmlentities($key).']['.htmlentities($k).']" /> '.$k.'</li>';
		echo '		</ul>';
	}//end foreach
	echo '		<p><input type="submit" name="save" value="Save &raquo;" /></p>';
	echo '	</form>';
	echo '</div>';
}//end function oauth_services_render
function oauth_services_tab($s) {
	add_submenu_page('profile.php', 'Services', 'Services', 1, __FILE__, 'oauth_services_render');
	return $s;
}//end function
add_action('admin_menu', 'oauth_services_tab');

?>
