<?php
/*
Plugin Name: OAuth
Plugin URI: http://wordpress.org/extend/plugins/oauth
Description: Enables OAuth services on your Wordpress blog.
Version: trunk
Author: DiSo Development Team
Author URI: http://diso-project.org/
*/

add_action('xrds_simple', 'oauth_xrds_service');
add_filter('parse_request', 'oauth_parse_request');
add_action('query_vars', 'oauth_query_vars');
add_action('generate_rewrite_rules', 'oauth_rewrite_rules');
register_activation_hook('oauth/oauth.php', 'oauth_activate_plugin');
register_deactivation_hook('oauth/oauth.php', 'oauth_deactivate_plugin');

//if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
	add_filter('authenticate', 'oauth_authenticate', 9);
//}

require_once dirname(__FILE__) . '/admin_panels.php';

function oauth_activate_plugin() {
	global $wp_rewrite;

	$wp_rewrite->flush_rules();

	add_option('oauth_servers', array());
	add_option('oauth_server_tokens', array());
	add_option('oauth_consumers', array());
	add_option('oauth_consumer_tokens', array());
}

function oauth_deactivate_plugin() {
}


function oauth_query_vars($vars) {
	$vars[] = 'oauth';
	return $vars;
}


function oauth_rewrite_rules($wp_rewrite) {
	$oauth_rules = array( 
		oauth_service_url('oauth', '(.*)', null, false) => 'index.php?oauth=$matches[1]',
	);

	$wp_rewrite->rules = $oauth_rules + $wp_rewrite->rules;
}


function oauth_authenticate($user) {
	require_once dirname(__FILE__) . '/lib/OAuthRequestVerifier.php';

	if (OAuthRequestVerifier::requestIsSigned()) {
		$store = oauth_store();

		try {
			$req = new OAuthRequestVerifier();
			$user_id = $req->verify();
			$user = wp_set_current_user($user_id);
		} catch (OAuthException $e) {
			error_log($e->getMessage());
		}
	}

	return $user;
}

/**
 * Get the OAuth signature methods supported by the current PHP environment.
 *
 * @return array supported methods
 */
function oauth_supported_signature_methods() {
	return array('HMAC-SHA1', 'PLAINTEXT');
}



/**
 * Get singleton OAuthServer instance.
 *
 * @return OAuthServer
 */
function oauth_server() {
	static $server;

	if (!$server) {
		$store = oauth_store();

		require_once dirname(__FILE__) . '/lib/OAuthServer.php';
		$server = new OAuthServer();
	}

	return $server;
}


/**
 * Get singleton OAuthStore instance.
 *
 * @return OAuthStore
 */
function oauth_store() {
	static $store;

	if (!$store) {
		require_once dirname(__FILE__) . '/lib/OAuthStore.php';
		$store = OAuthStore::instance('WordPress', array());
	}

	return $store;
}


/**
 * Parse WordPress request, and handle any OAuth related requests.
 *
 * @param WP $wp WP request object
 */
function oauth_parse_request($wp) {
	if (!array_key_exists('oauth', $wp->query_vars)) return;

	do_action('oauth_endpoint', $wp->query_vars['oauth']);
	$server = oauth_server();

	switch ($wp->query_vars['oauth']) {
		case 'request': 
			$server->requestToken();
			break;

		case 'authorize': 
			session_start();

			if (!is_user_logged_in()) {
				auth_redirect();
			}

			$user = wp_get_current_user();

			if (@$_REQUEST['authorize']) { // submitted auth form
				check_admin_referer('oauth_authorize_token');
				$server->authorizeFinish($_REQUEST['authorize'], $user->ID);

				// TODO display instructions to close window or return to application
			} else {
				try {
					$server->authorizeVerify();
				} catch (OAuthException $e) {
					header('HTTP/1.1 400 Bad Request');
					header('Content-Type: text/plain');

					// echo "Failed OAuth Request: " . $e->getMessage();
				}

				oauth_authorize_token();
			}

			break;

		case 'access': 
			$server->accessToken();
			break;

		default:
			header('HTTP/1.1 500 Internal Server Error');
			header('Content-Type: text/plain');
			echo "Unknown request";
			break;
	}

	exit;
}


function oauth_get_user_access_tokens($user_id) {
	$store = oauth_store();
	return $store->listServerTokens($user_id);
}


function oauth_delete_token($token, $user_id) {
	$store = oauth_store();
	$store->deleteConsumerAccessToken($token, $user_id);
}


/**
 * Require OAuth authentication.  If the request is signed with an authorized 
 * OAuth token, set the current WordPress user.  Otherwise, deny access.
 */
function oauth_require_auth() {
	$authorized = false;
	$server = oauth_server();
	try {
		if ($server->verifyIfSigned()) {   
			$authorized = true;
		}   
	} catch (OAuthException $e) {
	}

	if (!$authorized) {
		header('HTTP/1.1 401 Unauthorized');
		header('Content-Type: text/plain');
		header('WWW-Authenticate: OAuth realm="' . get_option('siteurl') . '"');

		echo "OAuth Verification Failed: " . $e->getMessage();
		die;
	}

	$t = $server->getParam('oauth_token', true);
	$tokens = get_option('oauth_consumer_tokens');
	if (array_key_exists($t, $tokens)) {
		wp_set_current_user($token[$t]['user']);
	}
}


/**
 * Register XRDS Services.
 */
function oauth_xrds_service($xrds) {

	$parameter_methods = array(
		'http://oauth.net/core/1.0/parameters/auth-header',
		'http://oauth.net/core/1.0/parameters/uri-query',
	);

	$signature_methods = oauth_supported_signature_methods();
	$signature_types = array();
	foreach ($signature_methods as $method) {
		$signature_types[] = 'http://oauth.net/core/1.0/signature/' . $method;
	}

	xrds_add_simple_service($xrds, 'OAuth Dummy Service', 'http://oauth.net/discovery/1.0', '#oauth');

	$service = new XRDS_Service( array_merge(array('http://oauth.net/core/1.0/endpoint/request'), $parameter_methods, $signature_types) );
	$service->uri[] = new XRDS_URI(oauth_service_url('oauth', 'request', 'login_post'));
	xrds_add_service($xrds, 'oauth', $service);

	$service = new XRDS_Service( array('http://oauth.net/core/1.0/endpoint/authorize', 'http://oauth.net/core/1.0/parameters/uri-query') );
	$service->uri[] = new XRDS_URI(oauth_service_url('oauth', 'authorize', 'login_post'));
	xrds_add_service($xrds, 'oauth', $service);

	$service = new XRDS_Service( array_merge(array('http://oauth.net/core/1.0/endpoint/access'), $parameter_methods, $signature_types) );
	$service->uri[] = new XRDS_URI(oauth_service_url('oauth', 'access', 'login_post'));
	xrds_add_service($xrds, 'oauth', $service);

	$service = new XRDS_Service( array_merge(array('http://oauth.net/core/1.0/endpoint/resource'), $parameter_methods, $signature_types) );
	xrds_add_service($xrds, 'oauth', $service);

	$store = oauth_store();
	$static_consumer = $store->getConsumerStatic();

	$service = new XRDS_Service('http://oauth.net/discovery/1.0/consumer-identity/static');
	$service->local_id[] = new XRDS_LocalID($static_consumer['key']);
	xrds_add_service($xrds, 'oauth', $service);
}


function oauth_service_url($name, $value, $scheme = null, $absolute = true) {
	global $wp_rewrite;
	if (!$wp_rewrite) $wp_rewrite = new WP_Rewrite();

	if ($absolute) {
		$url = site_url('/', $scheme);
	} else {
		$site_url = get_option('siteurl');
		$home_url = get_option('home');

		if ($site_url != $home_url) {
			$url = substr(trailingslashit($site_url), strlen($home_url)+1);
		} else {
			$url = '';
		}
	}

	if ($wp_rewrite->using_permalinks()) {
		if ($wp_rewrite->using_index_permalinks()) {
			$url .= 'index.php/';
		}
		$url .= $name . '/' . $value;
	} else {
		$url .= '?' . $name . '=' . $value;
	}

	return $url;
}

?>
