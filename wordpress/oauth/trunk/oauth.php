<?php
/*
Plugin Name: OAuth
Plugin URI: http://wordpress.org/extend/plugins/oauth
Description: Enables OAuth services on your Wordpress blog.
Version: trunk
Author: DiSo Development Team
Author URI: http://diso-project.org/
*/

define('OAUTH_STATIC_CONSUMER_KEY', 'DUMMY_KEY');

add_filter('xrds_simple', 'oauth_xrds_service');
add_filter('parse_request', 'oauth_parse_request');
add_action('query_vars', 'oauth_query_vars');
add_action('generate_rewrite_rules', 'oauth_rewrite_rules');


function oauth_activate_plugin() {
	global $wp_rewrite;

	$wp_rewrite->flush_rules();

	add_option('oauth_servers', array());
	add_option('oauth_server_tokens', array());
	add_option('oauth_consumers', array());
	add_option('oauth_consumer_tokens', array());
}


function oauth_query_vars($vars) {
	$vars[] = 'oauth';
	return $vars;
}


function oauth_rewrite_rules($wp_rewrite) {
	$url_parts = parse_url(get_option('siteurl'));
	$site_root = substr(trailingslashit($url_parts['path']), 1);

	$openid_rules = array( 
		$site_root . 'oauth/(.*)' => 'index.php?oauth=$matches[1]',
	);

	$wp_rewrite->rules = $openid_rules + $wp_rewrite->rules;
}


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
		'URI' => array( array('content' => site_url('/oauth/request') ) ),
	) );

	$xrds = xrds_add_service($xrds, 'oauth', 'OAuth Authorize Token', array(
		'Type' => array( 
			array('content' => 'http://oauth.net/core/1.0/endpoint/authorize'),
			array('content' => 'http://oauth.net/core/1.0/parameters/uri-query'),
		),
		'URI' => array( array('content' => site_url('/oauth/authorize') ) ),
	) );
	
	$xrds = xrds_add_service($xrds, 'oauth', 'OAuth Access Token', array(
		'Type' => array( 
			array('content' => 'http://oauth.net/core/1.0/endpoint/access'),
			array('content' => 'http://oauth.net/core/1.0/parameters/uri-query'),
			array('content' => 'http://oauth.net/core/1.0/signature/HMAC-SHA1'),
		),
		'URI' => array( array('content' => site_url('/oauth/access') ) ),
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
		'LocalID' => array( array('content' => OAUTH_STATIC_CONSUMER_KEY ) ),
	) );

	return $xrds;
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


add_action('admin_menu', 'oauth_admin_menu');
function oauth_admin_menu() {
	$hookname = add_options_page('OAuth', 'OAuth', 8, 'oauth', 'oauth_options_page');

	$hookname = add_users_page(__('Your Services', 'oauth'), __('Your Services', 'oauth'),
		'read', 'oauth_services', 'oauth_profile_panel' );

}


/**
 * Handle OAuth user profile page.
 */
function oauth_profile_panel() {
	$user = wp_get_current_user();

	if (@$_POST['action'] == 'delete') {
		$tokens = oauth_get_user_access_tokens($user->ID);
		$count = 0;
		foreach ($_POST['delete'] as $token_hash) {
			foreach ($tokens as $token) {
				if (md5($token['token']) == $token_hash) {
					oauth_delete_token($token['token'], $user->ID);
					$count++;
				}
			}
		}
		if ($count) {
			echo '<div class="updated"><p>'.__('Deleted '.$count.' service' . ($count>1 ? 's' : '') . '.').'</p></div>';
		}

	}

 	if (function_exists('screen_icon')) {
		screen_icon('openid');
	}

?>
	<div class="wrap">
		<form action="<?php printf('%s?page=%s', $_SERVER['PHP_SELF'], $_REQUEST['page']); ?>" method="post">

			<h2><?php _e('Your Services', 'oauth'); ?></h2>

		<div class="tablenav">
            <div class="alignleft">
                <input type="submit" value="<?php _e('Delete'); ?>" name="deleteit" class="button-secondary delete" />
                <input type="hidden" name="action" value="delete" />
                <?php wp_nonce_field('oauth-delete_services'); ?>
            </div>
        </div>

		<br class="clear" />

        <table class="widefat">
            <thead>
                <tr>
                    <th scope="col" class="check-column"><input type="checkbox" /></th>
                    <th scope="col"><?php _e('Service', 'oauth'); ?></th>
                </tr>
            </thead>
            <tbody>

            <?php
                $tokens = oauth_get_user_access_tokens($user->ID);

                if (empty($tokens)) {
                    echo '<tr><td colspan="2">'.__('No Services.', 'oauth').'</td></tr>';
                } else {
                    foreach ($tokens as $token) {
                        echo '
                        <tr>
                            <th scope="row" class="check-column"><input type="checkbox" name="delete[]" value="'.md5($token['token']).'" /></th>
                            <td>'.($token['token']).'</td>
                        </tr>';
                    }   
                }   
            ?>  

            </tbody>
            </table>
        </form>
	</div>
<?php
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


function oauth_options_page() {
	$store = oauth_store();

	if (false) { // clear out tokens
		$tokens = get_option('oauth_consumer_tokens');
		foreach ($tokens as $key => $token) {
			$store->deleteConsumerRequestToken($key);
		}
	}

	if (false) { // register consumer
		$data = array(
			'requester_name' => 'Default Consumer',
			'requester_email' => 'will@willnorris.com',
		);
		$store->updateConsumer($data, 2, true);
	}

	if (false) { // full token flow
		$result = $store->addConsumerRequestToken('foo');

		$tokens = get_option('oauth_consumer_tokens');
		echo '<pre>';
		print_r($tokens);
		echo '</pre>';

		$store->authorizeConsumerRequestToken($result['token'], 2);
		$store->exchangeConsumerRequestForAccessToken($result['token']);
	}


	$consumers = get_option('oauth_consumers');
	echo '<pre> Consumers = ';
	print_r($consumers);
	echo "\n\n</pre>";
	
	$tokens = get_option('oauth_consumer_tokens');
	echo "<pre> Consumer Tokens = ";
	print_r($tokens);
	echo "\n\n</pre>";

	$servers = get_option('oauth_servers');
	echo '<pre> Servers = ';
	print_r($servers);
	echo "\n\n</pre>";
	
	$tokens = get_option('oauth_server_tokens');
	echo "<pre> Server Tokens = ";
	print_r($tokens);
	echo "\n\n</pre>";
}

function oauth_authorize_token() {
	$server = oauth_server();
	$token = $server->getParam('oauth_token', true);

	ob_start();
?>

	<form method="get">
		<h1><?php _e('An application would like to connect to your account'); ?></h1>
		<p><?php printf(__('Would you like to connect <strong>%s</strong> to your account? ' 
			. 'Connecting this application will allow it to read, modify, and delete WordPress content.'), 'foo'); ?></p>

		<label for="authorize_yes"><input type="radio" id="authorize_yes" name="authorize" value="1" /> Yes</label><br />
		<label for="authorize_no"><input type="radio" id="authorize_no" name="authorize" value="" /> No</label><br />
		
		<?php wp_nonce_field('oauth_authorize_token'); ?>
		<input type="hidden" name="_oauth_endpoint" value="<?php echo $_REQUEST['_oauth_endpoint']; ?>" />
		<input type="hidden" name="oauth_token" value="<?php echo $token ?>" />

		<p class="submit">
			<input type="submit" name="submit" value="Continue" />
		</p>
	</form>

<?php

	$html = ob_get_contents();
	ob_end_clean();
	wp_die($html);
}


?>
