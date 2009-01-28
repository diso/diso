<?php
/*
 Plugin Name: OAuth Test
 Description: Test OAuth XML-RPC calls.
 Author: Will Norris
 Author URI: http://willnorris.com/
 Version: trunk
 */

register_activation_hook('oauth/test-consumer.php', 'oauth_test_activate_plugin');

function oauth_test_activate_plugin() {
	add_option('oauth_test_url', site_url('/xmlrpc.php'));
}

add_action('admin_menu', 'oauth_test_admin_menu');
function oauth_test_admin_menu() {
	$hookname = add_options_page('OAuth Test', 'OAuth Test', 8, 'oauth_test', 'oauth_test_options_page');
	add_action("load-$hookname", 'oauth_test_options_load');
	add_contextual_help($hookname, oauth_test_help_text());

	register_setting('oauth_test', 'oauth_test_url');
}

function oauth_test_help_text() {
	ob_start();
?>

	<ol>
		<li>Until the changes are committed to WordPress trunk, apply the patch attached to <a href="http://trac.wordpress.org/ticket/8941">#8941</a>.</li>
		<li>Click each of the links at the bottom of this page, starting with <em>Register OAuth Server</em>.  After each one, you can look at the <a href="?page=oauth">OAuth Options</a> page to see the result.</li>
		<li>If all goes well on each of the links below, the final link should make a successful XML-RPC call using OAuth.</li>
	</ol>
	

<?php
	$text = ob_get_contents();
	ob_end_clean();
	return $text;
}

function oauth_test_options_load() {
	wp_reset_vars( array('action') );

	$oauth_path = WP_CONTENT_DIR . '/plugins/oauth/lib';
	set_include_path($oauth_path . PATH_SEPARATOR . get_include_path());

	require_once 'OAuthRequester.php';

	$oauth_store = oauth_store();
	$user = wp_get_current_user();

	$server_url = get_option('oauth_test_url');

	global $action;
	switch ($action) {
		case 'register':

			if (empty($server_url)) {
				echo 'Must provide URL.';
				break;
			}


			$url = trailingslashit(dirname($server_url));
			$oauth_server = oauth_test_discovery($url);

			if ($oauth_server) {
				$user = wp_get_current_user();
				$oauth_store->updateServer($oauth_server, $user->ID);
			}
			break;

		case 'request':
			$server = $oauth_store->getServerForUri($server_url, $user->ID);
			if ($server) {
				$token = @OAuthRequester::requestRequestToken($server['consumer_key'], $user->ID);
				$token['consumer_key'] = $server['consumer_key'];
				update_option('oauth_test_request_token', $token);
			}
			break;

		case 'authorize':
			$token = get_option('oauth_test_request_token');

			$callback = add_query_arg('action', null, admin_url($GLOBALS['pagenow']));
			$callback = add_query_arg('page', $_REQUEST['page'], $callback);

			$authorize_url = $token['authorize_uri'];
			$authorize_url = add_query_arg('oauth_token', rawurlencode($token['token']), $authorize_url);
			$authorize_url = add_query_arg('oauth_callback', rawurlencode($callback), $authorize_url);

			wp_redirect($authorize_url); exit;
			break;

		case 'access':
			$request_token = get_option('oauth_test_request_token');
			try {
				$access_token = @OAuthRequester::requestAccessToken($request_token['consumer_key'], $request_token['token'], $user->ID);
			}
			catch (OAuthException $e) {
				error_log('OAuthException - ' . var_export($e, true));
				// Something wrong with the oauth_token.
				// Could be:
				// 1. Was already ok
				// 2. We were not authorized
			}

			break;

	}
}

function oauth_test_discovery($server_url) {
	$xrds_path = WP_PLUGIN_DIR . '/xrds-simple/lib';
	set_include_path($xrds_path . PATH_SEPARATOR . get_include_path());
	require_once 'XRDS.php';
	require_once 'XRDS/Discovery.php';

	$disco = new XRDS_Discovery();
	$xrds = $disco->discover($server_url);

	if (is_a($xrds, 'XRDS')) {
		$oauth_server = array(
			'server_uri' => $server_url,
			'signature_methods' => array(),
		);

		$service = $xrds->getService('http://oauth.net/core/1.0/endpoint/request');
		foreach ($service->type as $type) {
			if (strpos($type, 'http://oauth.net/core/1.0/signature') === 0) {
				$oauth_server['signature_methods'][] = basename($type);
			}
		}

		$oauth_server['request_token_uri'] = (string) $service->uri[0];
		$oauth_server['authorize_uri'] = (string) $xrds->getServiceURI('http://oauth.net/core/1.0/endpoint/authorize');
		$oauth_server['access_token_uri'] = (string) $xrds->getServiceURI('http://oauth.net/core/1.0/endpoint/access');
		
		if ($static_service = $xrds->getService('http://oauth.net/discovery/1.0/consumer-identity/static')) {
			$key = (string) $static_service->local_id[0];
			$oauth_server['consumer_key'] = $key;
			$oauth_server['consumer_secret'] = '';
		}

		return $oauth_server;
	} else {
		echo '<div class="error"><p><strong>Unable to discovery OAuth Server at ' . $server_url . '</strong></p></div>';
	}
}

function oauth_test_options_page() {

	$oauth_store = oauth_store();
	$user = wp_get_current_user();

	screen_icon('oauth');
?>
	<style type="text/css"> #icon-oauth { background-image: url("<?php echo plugins_url('oauth/icon.png'); ?>"); } </style>
	<div class="wrap">
		<h2>OAuth Test</h2>

<?php
	if (!function_exists('xrds_add_xrd')) {
		echo '<div class="error"><p><strong>Please install and activate <a href="http://diso.googlecode.com/svn/wordpress/xrds-simple/trunk/">XRDS-Simple</a>.</strong></p></div>';
	}
?>
		<form method="post" action="options.php">

		<table class="form-table optiontable editform">
			<tr valign="top">
				<th scope="row"><?php _e('Website URL') ?></th>
				<td>
					<p><input type="text" name="oauth_test_url" id="oauth_test_url" value="<?php echo get_option('oauth_test_url'); ?>" size="50" /></p>
				</td>
			</tr>
		</table>

		<?php settings_fields('oauth_test'); ?>
		<p class="submit"><input type="submit" name="info_update" value="<?php _e('Update Options') ?> &raquo;" /></p>

		</form>

		<hr />

		<?php 
			try {
				$request_token = get_option('oauth_test_request_token');
				$token = $oauth_store->getServerToken(get_option('oauth_test_consumer_key'), $request_token['token'], $user->ID);
				if ($token) {
					echo '<pre>OAuth Token = ' . print_r($token, true) . '</pre>';
					echo '<hr />';
				}
			} catch (OAuthException $e) {
			}
		?>

		<p><a href="<?php echo add_query_arg('action', 'register'); ?>">Register OAuth Server</a></p>
		<p><a href="<?php echo add_query_arg('action', 'request'); ?>">Get Request Token</a></p>
		<p><a href="<?php echo add_query_arg('action', 'authorize'); ?>">Authorize Token</a></p>
		<p><a href="<?php echo add_query_arg('action', 'access'); ?>">Get Access Token</a></p>
		<p><a href="<?php echo add_query_arg('action', 'make_call'); ?>">Make OAuth Authenticated Call</a></p>
	</div>


<?php
	if (@$_REQUEST['action'] == 'make_call') {
		oauth_test_oauth_getUserInfo();
	}
}

function oauth_test_oauth_getUserInfo() {
	$user = wp_get_current_user();
	$url = get_option('oauth_test_url');
	if (empty($url)) return;

	try {
		$oauth_req = new OAuthRequester($url, 'POST');
		$oauth_req->sign($user->ID);
		$auth_header = $oauth_req->getAuthorizationHeader();
	} catch (OAuthException $e) {
		echo $e->getMessage();
		return;
	}

	require_once( ABSPATH . WPINC . '/class-IXR.php' );
	$xmlrpc = new IXR_Client($url);
	$xmlrpc->debug = true;
	list($header_name, $header_value) = split(':', $auth_header, 2);
	$xmlrpc->headers[$header_name] = $header_value;
	$result = $xmlrpc->query('blogger.getUserInfo', '', '', '');

	echo '<style type="text/css"> .ixr_request, .ixr_response { margin: 3em 0; } </style>';
}

?>
