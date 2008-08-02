<?php
/*
Plugin Name: WP-OAuth
Plugin URI: http://singpolyma.net/plugins/oauth/
Description: Enables OAuth services on your Wordpress blog.
Version: 0.13
Author: Stephen Paul Weber
Author URI: http://singpolyma.net/
*/

//Licensed under an MIT-style licence

/**
 * Register XRDS Services.
 */
function oauth_xrds_service($xrds) {

	$xrds = xrds_add_service($xrds, 'main', 'OAuth Dummy Service', array(
		'Type' => array( array('content' => 'http://oauth.net/discovery/1.0') ),
		'URI' => array( array('content' => '#oauth' ) ),
	) );

	$xrds = xrds_add_service($xrds, 'oauth', 'OAuth Request Token', array(
		'Type' => array( 
			array('content' => 'http://oauth.net/core/1.0/endpoint/request'),
			array('content' => 'http://oauth.net/core/1.0/parameters/uri-query'),
			array('content' => 'http://oauth.net/core/1.0/signature/HMAC-SHA1'),
		),
		'URI' => array( array('content' => get_option('siteurl').'/?_oauth_endpoint=request' ) ),
	) );

	$xrds = xrds_add_service($xrds, 'oauth', 'OAuth Authorize Token', array(
		'Type' => array( 
			array('content' => 'http://oauth.net/core/1.0/endpoint/authorize'),
			array('content' => 'http://oauth.net/core/1.0/parameters/uri-query'),
		),
		'URI' => array( array('content' => get_option('siteurl').'/?_oauth_endpoint=authorize' ) ),
	) );
	
	$xrds = xrds_add_service($xrds, 'oauth', 'OAuth Access Token', array(
		'Type' => array( 
			array('content' => 'http://oauth.net/core/1.0/endpoint/access'),
			array('content' => 'http://oauth.net/core/1.0/parameters/uri-query'),
			array('content' => 'http://oauth.net/core/1.0/signature/HMAC-SHA1'),
		),
		'URI' => array( array('content' => get_option('siteurl').'/?_oauth_endpoint=access' ) ),
	) );
	
	$xrds = xrds_add_service($xrds, 'oauth', 'OAuth Resources', array(
		'Type' => array( 
			array('content' => 'http://oauth.net/core/1.0/endpoint/resource'),
			array('content' => 'http://oauth.net/core/1.0/parameters/uri-query'),
			array('content' => 'http://oauth.net/core/1.0/signature/HMAC-SHA1'),
		),
	) );
	
	$xrds = xrds_add_service($xrds, 'oauth', 'OAuth Static Token', array(
		'Type' => array( 
			array('content' => 'http://oauth.net/discovery/1.0/consumer-identity/static'),
		),
		'LocalID' => array( array('content' => 'DUMMYKEY' ) ),
	) );

	return $xrds;
}
add_filter('xrds_simple', 'oauth_xrds_service');

function oauth_basic_services($services) {
	$services['Post Comments'] = array('wp-comments-post.php');
	$services['Edit and Create Entries and Categories'] = array('wp-app.php');

	return $services;
}
add_filter('oauth_services', 'oauth_basic_services');

function oauth_accept() {

	set_include_path( dirname(__FILE__) . PATH_SEPARATOR . get_include_path() );

	require_once dirname(__FILE__).'/common.inc.php';
	
	$services = array();
	$services = apply_filters('oauth_services', $services);

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

}//end function oauth_accept
//if(!$NO_oauth)
	//oauth_accept();

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
	echo '		<dd>'.get_option('siteurl').'/?_oauth_endpoint=request</dd>';
	echo '	<dt>Authorize token endpoint</dt>';
	echo '		<dd>'.get_option('siteurl').'/?_oauth_endpoint=authorize</dd>';
	echo '	<dt>Access token endpoint</dt>';
	echo '		<dd>'.get_option('siteurl').'/?_oauth_endpoint=access</dd>';
	echo '</dl>';
	$anid = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='post' ORDER BY post_date DESC LIMIT 1");
	echo '<a href="http://singpolyma.net/oauth/example/wp_auto_client3.php?'
.'&amp;blog='.urlencode(get_bloginfo('home').'/')
.'&amp;api_endpoint='.urlencode(get_bloginfo('wpurl').'/wp-comments-post.php')
.'&amp;post_id='.$anid
.'">Click here for a test page &raquo;</a>';
}//end function oauth_page

function oauth_tab($s) {
	add_submenu_page('options-general.php', 'OAuth', 'OAuth', 1, 'wp-oauth', 'oauth_page');
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

function oauth_parse_request($wp) {
	if (array_key_exists('_oauth_endpoint', $_REQUEST) && function_exists('oauth_endpoint_' . $_REQUEST['_oauth_endpoint'])) {
		call_user_func('oauth_endpoint_' . $_REQUEST['_oauth_endpoint']);
	}
}
add_action('parse_request', 'oauth_parse_request');

function oauth_init_server() {
	require_once dirname(__FILE__).'/common.inc.php';

	$store = new OAuthWordpressStore();
	$server = new OAuthServer($store);
	$sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
	$plaintext_method = new OAuthSignatureMethod_PLAINTEXT();
	$server->add_signature_method($sha1_method);
	$server->add_signature_method($plaintext_method);

	return $server;
}

function oauth_endpoint_request() {
	$server = oauth_init_server();

	try {
	  $req = OAuthRequest::from_request();
	  $token = $server->fetch_request_token($req);
	  print $token;
	  //print $token.'&xoauth_token_expires='.urlencode($store->token_expires($token));
	  exit;
	} catch (OAuthException $e) {
	  header('Content-type: text/plain;', true, 400);
	  print($e->getMessage() . "\n\n");
	  var_dump($req);
	  die;
	}
}

function oauth_endpoint_authorize() {
	global $wpdb;

	if(!$_REQUEST['oauth_token'] && !$_POST['authorize']) die('No token passed');

	$NO_oauth = true;
	require_once dirname(__FILE__).'/common.inc.php';
	$store = new OAuthWordpressStore();

	if(!$_POST['authorize']) {
		$token = $wpdb->escape($_REQUEST['oauth_token']);
		$consumer_key = $store->lookup_token('','request',$token);//verify token
		if(!$consumer_key) die('Invalid token passed');
	}//end if ! POST authorize

	$user = wp_get_current_user();

	if(!$user->ID) {
		$redirect_to = urlencode(get_option('siteurl').'/?_oauth_endpoint=authorize&oauth_token='.urlencode($_REQUEST['oauth_token']).'&oauth_callback='.urlencode($_REQUEST['oauth_callback']));
		header('Location: '.get_option('siteurl').'/wp-login.php?redirect_to='.$redirect_to,true,303);
		exit;
	}//end if ! userdata->ID

	if($_POST['authorize']) {
		session_start();
		$_REQUEST['oauth_callback'] = $_SESSION['oauth_callback']; unset($_SESSION['oauth_callback']);
		$token = $_SESSION['oauth_token']; unset($_SESSION['oauth_token']);
		$consumer_key = $_SESSION['oauth_consumer_key']; unset($_SESSION['oauth_consumer_key']);
		if($_POST['authorize'] != 'Ok') {
			if($_REQUEST['oauth_callback']) {
				header('Location: '.$_REQUEST['oauth_callback'],true,303);
			} else {
				get_header();
				echo '<h2 style="text-align:center;">You chose to cancel authorization.  You may now close this window.</h2>';
				get_footer();
			}//end if-else callback
			exit;
		}//cancel authorize
		$consumers = get_usermeta($user->ID, 'oauth_consumers');
		if (!$consumers) $consumers = array();
		$services = apply_filters('oauth_services', array());
		$yeservices = array();
		foreach($services as $k => $v)
			if(in_array($k, array_keys($_REQUEST['services'])))
				$yeservices[$k] = $v;
		$consumers[$consumer_key] = array_merge(array('authorized' => true), $yeservices);//it's an array so that more granular data about permissions could go in here
		update_usermeta($user->ID, 'oauth_consumers', $consumers);
	}//end if authorize

	$oauth_consumers = get_usermeta($user->ID, 'oauth_consumers');
	if($oauth_consumers && in_array($consumer_key,array_keys($oauth_consumers))) {
		$store->authorize_request_token($consumer_key, $token, $user->ID);
		if($_REQUEST['oauth_callback']) {
			header('Location: '.$_REQUEST['oauth_callback'],true,303);
		} else {
			get_header();
			echo '<h2 style="text-align:center;">Authorized!  You may now close this window.</h2>';
			get_footer();
		}//end if-else callback
		exit;
	} else {
		session_start();//use a session to prevent the consumer from tricking the user into posting the Yes answer
		$_SESSION['oauth_token'] = $token;
		$_SESSION['oauth_callback'] = $_REQUEST['oauth_callback'];
		$_SESSION['oauth_consumer_key'] = $consumer_key;
		get_header();
		$description = $store->lookup_consumer_description($consumer_key);
		if($description) $description = 'Allow '.$description.' to access your Wordpress account and...';
			else $description = 'Allow the service you came from to access your Wordpress account and...';
		?>
		<div style="text-align:center;">
			<h2><?php echo $description; ?></h2>
			<form method="post" action="?_oauth_endpoint=authorize"><div>
				<div style="text-align:left;width:15em;margin:0 auto;">
					<ul style="padding:0px;">
				<?php
					$services = apply_filters('oauth_services', array());
					foreach($services as $k => $v)
						echo '<li><input type="checkbox" checked="checked" name="services['.htmlentities($k).']" /> '.$k.'</li>';
				?>
					</ul>
					<br />
					<input type="submit" name="authorize" value="Ok" />
					<input type="submit" name="authorize" value="No" />
				</div>
			</div></form>
		</div>
		<?php
		get_footer();
	}//end if user has authorized this consumer

	exit;
}

function oauth_endpoint_access() {
	$server = oauth_init_server();

	try {
	  $req = OAuthRequest::from_request();
	  $token = $server->fetch_access_token($req);
	  print $token;
	  //print $token.'&xoauth_token_expires='.urlencode($store->token_expires($token));
	  exit;
	} catch (OAuthException $e) {
	  header('Content-type: text/plain;', true, 400);
	  print($e->getMessage() . "\n\n");
	  var_dump($req);
	  die;
	}
}

?>
